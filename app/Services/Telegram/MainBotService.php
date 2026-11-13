<?php

namespace App\Services\Telegram;

use App\Helpers\TelegramHelper;
use App\Models\Recipient\Recipient;
use App\Models\Recipient\TgUser;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Repositories\Energy\EnergyWalletRepository;
use App\Repositories\Recipient\RecipientWalletRepository;
use App\Repositories\RecipientRepository;
use App\Reports\ExpiredEnergyWalletsReport;
use App\Reports\ResourceCostsReport;
use App\Reports\WalletsReport;
use App\Models\Recipient\LedgerAccount;
use App\Models\Recipient\LedgerAccountWallet;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class MainBotService
{
    /**
     * Bot name as defined in the config
     */
    const string BOT_NAME = 'default';

    public function __construct(
        private readonly RecipientWalletRepository $recipientWalletRepository,
        private readonly RecipientRepository $recipientRepository,
        private readonly EnergyWalletRepository $energyWalletRepository,
        private readonly LedgerService $ledgerService,
    ) {}

    //REPORT

    public function sendWalletsReport(int $chatId): void
    {
        $report = app(WalletsReport::class);
        $message = $report->build($chatId);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode'   => 'HTML',
        ]);
    }

    public function sendResourceCostsReport(int $chatId): void
    {
        $report = app(ResourceCostsReport::class);
        $message = $report->build($chatId);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }

    //MAIN MENU

    public function showStartMessage(int $chatId): void
    {
        $replyKeyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->row([
                Keyboard::button(['text' => '🏢 Recipients']),
                Keyboard::button(['text' => '📊 Report']),
            ])
            ->row([
                Keyboard::button(['text' => '⚡ Smart Energy']),
            ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Welcome! Choose an action:',
            'reply_markup' => $replyKeyboard,
        ]);
    }

    //RECIPIENTS

    public function showRecipients(int $chatId): void
    {
        $recipientIds = TgUser::getRecipientIds($chatId);
        $canCreate = (bool) TgUser::findByTelegramId($chatId)?->can_create_recipients;

        $recipients = empty($recipientIds) ? collect() : Recipient::whereIn('id', $recipientIds)->get();

        if ($recipients->isEmpty() && !$canCreate) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'No Recipients are assigned to you. Please contact the administrator.',
            ]);
            return;
        }

        $keyboard = Keyboard::make()->inline();
        foreach ($recipients as $recipient) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => $recipient->name, 'callback_data' => 'recipient_' . $recipient->id]),
            ]);
        }

        if ($canCreate) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => '➕ New Recipient', 'callback_data' => 'new_recipient']),
            ]);
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $recipients->isEmpty() ? 'No Recipients yet.' : 'Choose a Recipient:',
            'reply_markup' => $keyboard,
        ]);
    }

    public function askForRecipientName(int $chatId): void
    {
        if (!TgUser::findByTelegramId($chatId)?->can_create_recipients) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ You are not allowed to create Recipients.']);
            return;
        }

        Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Enter the new Recipient name:']);
        $this->saveState($chatId, 'waiting_recipient_name');
    }

    public function handleNewRecipientInput(int $chatId, string $text): void
    {
        $this->clearState($chatId);

        $tgUser = TgUser::findByTelegramId($chatId);
        if (!$tgUser?->can_create_recipients) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ You are not allowed to create Recipients.']);
            return;
        }

        $name = trim($text);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Name must be 2 to 100 characters long.']);
            $this->askForRecipientName($chatId);
            return;
        }

        $recipient = $this->recipientRepository->store(['name' => $name]);
        if (!$recipient) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Failed to create the Recipient.']);
            return;
        }

        $tgUser->recipients()->syncWithoutDetaching([$recipient->id]);

        Log::channel('security')->info('Recipient created via bot', [
            'recipient_id' => $recipient->id,
            'chatId' => $chatId,
            'bot' => self::BOT_NAME,
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => sprintf("✅ Recipient created: %s\nAPI ID: %s", $recipient->name, $recipient->external_id),
        ]);

        $this->showRecipients($chatId);
    }

    //RECIPIENT TG USERS

    public function askForRecipientTgUser(int $chatId, int $recipientId): void
    {
        if (!$this->canManageRecipient($chatId, $recipientId)) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ You are not allowed to manage this Recipient.']);
            return;
        }

        Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Enter the user Telegram ID, then a space and the name:']);
        $this->saveState($chatId, "waiting_tg_user_for_{$recipientId}");
    }

    public function handleRecipientTgUserInput(int $chatId, int $recipientId, string $text): void
    {
        $this->clearState($chatId);

        if (!$this->canManageRecipient($chatId, $recipientId)) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ You are not allowed to manage this Recipient.']);
            return;
        }

        [$telegramId, $name] = array_pad(preg_split('/\s+/', trim($text), 2), 2, null);

        if (!preg_match('/^\d{3,20}$/', (string) $telegramId)) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Telegram ID must contain digits only.']);
            $this->askForRecipientTgUser($chatId, $recipientId);
            return;
        }

        $user = TgUser::withTrashed()->firstOrNew(['telegram_id' => $telegramId]);
        if ($name) {
            $user->name = mb_substr($name, 0, 255);
        }
        $user->deleted_at = null;
        $user->save();
        $user->recipients()->syncWithoutDetaching([$recipientId]);

        Log::channel('security')->info('Recipient tg user added via bot', [
            'recipient_id' => $recipientId,
            'tg_user_id' => $user->id,
            'chatId' => $chatId,
            'bot' => self::BOT_NAME,
        ]);

        Telegram::sendMessage(['chat_id' => $chatId, 'text' => "✅ User {$user->selectLabel} added to the Recipient."]);
    }

    /**
     * Only a member of the Recipient with the can_create_recipients flag can manage it (add users).
     */
    private function canManageRecipient(int $chatId, int $recipientId): bool
    {
        $tgUser = TgUser::findByTelegramId($chatId);

        return $tgUser?->can_create_recipients
            && in_array($recipientId, array_map('intval', TgUser::getRecipientIds($chatId)), true);
    }

    //BLOCKCHAIN / WALLET TYPES

    public function showBlockchainTypes(int $chatId, int $messageId, int $recipientId): void
    {
        $keyboard = Keyboard::make()->inline();

        foreach (RecipientWallet::blockChainTypeTrc20Names() as $type => $name) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => $name, 'callback_data' => "blockchain_{$recipientId}_{$type}"]),
            ]);
        }

        if ($this->canManageRecipient($chatId, $recipientId)) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => '👤 Add user', 'callback_data' => "add_tg_user_{$recipientId}"]),
            ]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'recipients_list'])]);

        $this->safeEdit([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'Choose a blockchain type:',
            'reply_markup' => $keyboard,
        ]);
    }

    public function showWalletTypes(int $chatId, int $messageId, int $recipientId, int $blockchainType): void
    {
        $keyboard = Keyboard::make()->inline();

        foreach (RecipientWallet::getTypesWithoutBlack() as $type => $name) {
            $icon = RecipientWallet::typesIcons()[$type] ?? '';
            $keyboard->row([
                Keyboard::inlineButton(['text' => sprintf('%s %s', $icon, $name), 'callback_data' => "wallet_type_{$recipientId}_{$blockchainType}_{$type}"]),
            ]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'recipient_' . $recipientId])]);

        $this->safeEdit([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'Choose a wallet type:',
            'reply_markup' => $keyboard,
        ]);
    }

    public function showWallets(int $chatId, int $messageId, int $recipientId, int $blockchainType, int $walletType): void
    {
        $wallets = RecipientWallet::where('recipient_id', $recipientId)
            ->where('blockchain_type', $blockchainType)
            ->where('type', $walletType)
            ->get();

        $keyboard = Keyboard::make()->inline();
        foreach ($wallets as $wallet) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => $wallet->address, 'callback_data' => "edit_wallet_{$wallet->id}"]),
            ]);
        }

        $keyboard->row([
            Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => "blockchain_{$recipientId}_{$blockchainType}"]),
        ]);

        if (!$wallets->count()) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => 'Add wallet ➕', 'callback_data' => "add_wallet_{$recipientId}_{$blockchainType}_{$walletType}"]),
            ]);
        }

        $this->safeEdit([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'Choose a wallet to edit:',
            'reply_markup' => $keyboard,
        ]);
    }

    //WALLET EDIT

    public function editWallet(int $chatId, int $walletId): void
    {
        /** @var RecipientWallet $wallet */
        $wallet = RecipientWallet::find($walletId);

        if (!$wallet) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ This wallet does not exist in the system.']);
            return;
        }

        $recipientName = $wallet->recipient->name ?? 'Not specified';
        $isTrc20 = $wallet->blockchain_type === RecipientWallet::BLOCKCHAIN_TYPE_TRC_20;

        $text = "Editing wallet:\nRecipient: {$recipientName}\nAddress: {$wallet->address}\nType: {$wallet->type_name}\nBlockchain: {$wallet->blockchain_type_name}";

        $keyboard = Keyboard::make()->inline();

        if ($isTrc20) {
            $poolEntry = $this->energyWalletRepository->findByAddressOrRecipientWallet($wallet->address, $wallet->id);

            if ($poolEntry) {
                $poolStatus = $poolEntry->auto_replenishment_enabled ? '✅ Enabled' : '❌ Disabled';
                $text .= "\nEnergy auto-replenishment: {$poolStatus}";

                $toggleText = $poolEntry->auto_replenishment_enabled ? '⚡ Disable auto-replenishment' : '⚡ Enable auto-replenishment';
                $keyboard->row([
                    Keyboard::inlineButton(['text' => $toggleText, 'callback_data' => "replenishment_toggle_{$poolEntry->id}_{$walletId}"]),
                ]);
            } else {
                $text .= "\nEnergy auto-replenishment: - None";
                $keyboard->row([
                    Keyboard::inlineButton(['text' => '⚡ Add to auto-replenishment', 'callback_data' => "replenishment_add_{$walletId}"]),
                ]);
            }
        }

        if ($this->canManageLedger($chatId, $wallet)) {
            $keyboard->row([
                Keyboard::inlineButton([
                    'text'          => '🗂 Manage accounts',
                    'callback_data' => "ledger_list_{$walletId}",
                ]),
            ]);
        }

        $text .= "\n\nChoose what you want to do:";

        $keyboard->row([
            Keyboard::inlineButton(['text' => 'Change address', 'callback_data' => "change_address_{$walletId}"]),
            Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => "blockchain_{$wallet->recipient_id}_{$wallet->blockchain_type}"]),
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard,
        ]);
    }

    //LEDGER ACCOUNTS (optional integration, docs/LEDGER_INTEGRATION.md)

    // Account button and callbacks: integration is configured, the user has the flag, the Recipient has a ledger ID
    private function canManageLedger(int $chatId, ?RecipientWallet $wallet): bool
    {
        if (!$wallet || !$this->ledgerService->isConfigured() || !$wallet->recipient?->ledger_user_id) {
            return false;
        }

        return (bool) TgUser::findByTelegramId($chatId)?->can_manage_ledger_accounts;
    }

    public function showLedgerAccounts(int $chatId, int $walletId): void
    {
        $wallet = RecipientWallet::find($walletId);

        if (!$wallet) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Wallet not found.']);
            return;
        }

        if (!$this->canManageLedger($chatId, $wallet)) {
            return;
        }

        $accounts = LedgerAccount::where('recipient_id', $wallet->recipient_id)
            ->where('is_show_bot', true)
            ->get();

        if ($accounts->isEmpty()) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '⚠️ No accounts found.']);
            return;
        }

        $keyboard = Keyboard::make()->inline();

        foreach ($accounts as $account) {
            $isLinked = (int)$account->wallet_id === $walletId;
            $btnText  = $isLinked
                ? "✅ {$account->name} ({$account->currency})"
                : "🔗 {$account->name} ({$account->currency})";

            $keyboard->row([
                Keyboard::inlineButton([
                    'text'          => $btnText,
                    'callback_data' => "ledger_pick_{$account->id}_{$walletId}",
                ]),
            ]);
        }

        $keyboard->row([
            Keyboard::inlineButton([
                'text'          => '↩️ Back',
                'callback_data' => "edit_wallet_{$walletId}",
            ]),
        ]);

        Telegram::sendMessage([
            'chat_id'      => $chatId,
            'text'         => "Manage wallet accounts:\n{$wallet->address}",
            'reply_markup' => $keyboard,
        ]);
    }

    public function ledgerPickAccount(int $chatId, int $accountId, int $walletId): void
    {
        $account = LedgerAccount::find($accountId);
        $wallet  = RecipientWallet::find($walletId);

        if (!$account || !$wallet || (int)$account->recipient_id !== (int)$wallet->recipient_id) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Account or wallet not found.']);
            return;
        }

        if (!$this->canManageLedger($chatId, $wallet)) {
            return;
        }

        $isLinked = (int)$account->wallet_id === $walletId;

        if ($isLinked) {
            $text     = "Unlink the wallet from the account?\n\nAccount: {$account->name} ({$account->currency})\nWallet: {$wallet->address}\n\nAre you sure?";
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '✅ Yes', 'callback_data' => "ledger_unlink_{$accountId}_{$walletId}"]),
                Keyboard::inlineButton(['text' => '❌ No', 'callback_data' => "ledger_list_{$walletId}"]),
            ]);
        } else {
            $text     = "Link the wallet to the account?\n\nAccount: {$account->name} ({$account->currency})\nWallet: {$wallet->address}\n\nAre you sure?";
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '✅ Yes', 'callback_data' => "ledger_confirm_{$accountId}_{$walletId}"]),
                Keyboard::inlineButton(['text' => '❌ No', 'callback_data' => "ledger_list_{$walletId}"]),
            ]);
        }

        Telegram::sendMessage([
            'chat_id'      => $chatId,
            'text'         => $text,
            'reply_markup' => $keyboard,
        ]);
    }

    public function ledgerConfirmUnlink(int $chatId, int $accountId, int $walletId): void
    {
        $account = LedgerAccount::find($accountId);
        $wallet  = RecipientWallet::find($walletId);

        if (!$account || !$wallet || (int)$account->recipient_id !== (int)$wallet->recipient_id) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Account not found.']);
            return;
        }

        if (!$this->canManageLedger($chatId, $wallet)) {
            return;
        }

        $ledgerWallet = LedgerAccountWallet::where('ledger_account_id', $account->id)->first();

        if ($ledgerWallet) {
            $result = $this->ledgerService->deleteAccountWallet($account->ledger_id, $ledgerWallet->ledger_wallet_id);
        } else {
            $result = $this->ledgerService->updateAccountWallet($account->ledger_id, null);
        }

        if (!$result['ok']) {
            Log::channel('ledger')->error('ledgerConfirmUnlink failed', ['ledger_account_id' => $account->id, 'response' => $result]);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Failed to unlink the wallet in the ledger.']);
            return;
        }

        $ledgerWallet?->delete();

        $account->update([
            'wallet_id'      => null,
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text'    => "✅ Wallet unlinked from account '{$account->name}'.",
        ]);

        sleep(1);
        $this->showLedgerAccounts($chatId, $walletId);
    }

    public function ledgerConfirmLink(int $chatId, int $accountId, int $walletId): void
    {
        $account = LedgerAccount::find($accountId);
        $wallet  = RecipientWallet::find($walletId);

        if (!$account || !$wallet || (int)$account->recipient_id !== (int)$wallet->recipient_id) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Account or wallet not found.']);
            return;
        }

        if (!$this->canManageLedger($chatId, $wallet)) {
            return;
        }

        $result = $this->ledgerService->updateAccountWallet($account->ledger_id, $wallet->address);

        if (!$result['ok']) {
            Log::channel('ledger')->error('ledgerConfirmLink failed', ['ledger_account_id' => $account->id, 'response' => $result]);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Failed to link the wallet in the ledger.']);
            return;
        }

        $body = $result['body'];
        if (is_array($body) && !empty($body['id'])) {
            LedgerAccountWallet::updateOrCreate(
                ['ledger_account_id' => $account->id, 'wallet_address' => $wallet->address],
                ['ledger_wallet_id' => $body['id']]
            );
        }

        $account->update([
            'wallet_id'      => $walletId,
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text'    => "✅ Wallet linked to account '{$account->name}'.",
        ]);

        sleep(1);
        $this->showLedgerAccounts($chatId, $walletId);
    }

    public function toggleReplenishmentFromEdit(int $chatId, int $poolId, string $username, int $walletId): void
    {
        $poolEntry = $this->energyWalletRepository->find($poolId);

        if (!$poolEntry) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Pool entry not found.']);
            return;
        }

        $this->energyWalletRepository->toggle($poolEntry, "tg:{$chatId}:@{$username}");

        $this->editWallet($chatId, $walletId);
    }

    public function addToPoolFromEdit(int $chatId, int $walletId, string $username): void
    {
        $wallet = RecipientWallet::find($walletId);

        if (!$wallet) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Wallet not found.']);
            return;
        }

        if ($this->energyWalletRepository->existsByAddress($wallet->address)) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '⚠️ This address is already in the pool.']);
            $this->editWallet($chatId, $walletId);
            return;
        }

        $this->energyWalletRepository->createFromRecipientWallet($wallet, "tg:{$chatId}:@{$username}", $chatId);

        $this->editWallet($chatId, $walletId);
    }

    public function askForEditAddress(int $chatId, int $walletId): void
    {
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Please enter the wallet address:']);
        $this->saveState($chatId, "waiting_edit_address_for_{$walletId}");
    }

    public function askForNewAddress(int $chatId, int $messageId, int $recipientId, int $blockchainType, int $walletType): void
    {
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Please enter the new wallet address:']);
        $this->saveState($chatId, "waiting_new_address_for_{$messageId}_{$recipientId}_{$blockchainType}_{$walletType}");
    }

    private function safeEdit(array $params): void
    {
        try {
            Telegram::editMessageText($params);
        } catch (\Exception $e) {
            if (!str_contains($e->getMessage(), 'message is not modified')) {
                throw $e;
            }
        }
    }

    //STATE

    public function saveState(int $chatId, string $state): void
    {
        $key = "telegram_state:{$chatId}";
        Redis::set($key, $state);
        Redis::expire($key, 3600);
    }

    public function getState(int $chatId): ?string
    {
        $key = "telegram_state:{$chatId}";
        return Redis::get($key);
    }

    public function clearState(int $chatId): void
    {
        $key = "telegram_state:{$chatId}";
        Redis::del($key);
    }

    //TEXT HANDLERS

    public function handleEditAddressInput(int $chatId, int $walletId, string $text, string $username): void
    {
        /** @var RecipientWallet $wallet */
        $wallet = RecipientWallet::find($walletId);

        if (!$wallet) {
            Log::channel('security')->error('❌ This wallet does not exist in the system.', [
                'bot' => self::BOT_NAME,
                'walletId' => $walletId,
                'chatId' => $chatId,
                'username' => $username,
            ]);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ This wallet does not exist in the system.']);
            return;
        }

        if (!preg_match($wallet->regexp, $text)) {
            Log::channel('security')->error('❌ Wallet validation failed', [
                'address' => $text,
                'chatId' => $chatId,
                'username' => $username,
                'bot' => self::BOT_NAME,
            ]);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ The address does not match the required format. Please check it and try again.',
            ]);
            $this->askForEditAddress($chatId, $walletId);
            return;
        }

        if ($this->recipientWalletRepository->getByAddress($text)) {
            Log::channel('security')->error('❌ This wallet address already exists!', [
                'bot' => self::BOT_NAME,
                'address' => $text,
                'chatId' => $chatId,
                'username' => $username,
            ]);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ This wallet address already exists!']);
            return;
        }

        $oldAddress = $wallet->address;

        // changedBy is passed to RecipientWalletObserver via a model property
        $wallet->changedBy = "tg:{$chatId}:@{$username}";
        $wallet->changedById = $chatId;
        $wallet->address = $text;
        $isSuccess = $wallet->save();

        $recipientName = $wallet->recipient->name ?? 'Not specified';
        $typeName = $wallet->type_name;
        $messageText = sprintf("✅ Wallet address updated successfully! \nRecipient: {$recipientName}\nNew address: %s", $wallet->address);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $isSuccess ? $messageText : '❌ An error occurred while updating the address!',
        ]);

        sleep(1);

        TelegramHelper::sendMessage($isSuccess
            ? sprintf("✅ Wallet address updated successfully! \nRecipient: %s\nWallet type: %s", $recipientName, $typeName)
            : '❌ An error occurred while updating the address! See the logs'
        );

        $this->clearState($chatId);
        $this->editWallet($chatId, $walletId);

        Log::channel('security')->error($messageText, [
            'old_address' => $oldAddress,
            'new_address' => $text,
            'chatId' => $chatId,
            'username' => $username,
            'bot' => self::BOT_NAME,
        ]);
    }

    //AUTO REPLENISHMENT

    const int POOL_PER_PAGE = 10;

    public function showAutoReplenishment(int $chatId, int $page = 1, ?int $messageId = null): void
    {
        $recipientIds = TgUser::getRecipientIds($chatId);

        $perPage = self::POOL_PER_PAGE;
        $total = $this->energyWalletRepository->total(onlyEnabled: false, recipientIds: $recipientIds, chatId: $chatId);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));

        $wallets = $this->energyWalletRepository->getPage($page, $perPage, onlyEnabled: false, recipientIds: $recipientIds, chatId: $chatId);

        $keyboard = Keyboard::make()->inline();

        foreach ($wallets as $entry) {
            $icon = $entry->auto_replenishment_enabled ? '✅' : '❌';
            $label = $entry->label ? " · {$entry->label}" : '';
            $short = substr($entry->address, 0, 6) . '...' . substr($entry->address, -4);
            $short = $entry->address;

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => "{$icon} {$short}{$label}",
                    'callback_data' => "energy_wallet_{$entry->id}_{$page}",
                ]),
            ]);
        }

        // Pagination
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 1) {
                $navRow[] = Keyboard::inlineButton(['text' => '⬅️', 'callback_data' => 'energy_list_' . ($page - 1)]);
            }
            $navRow[] = Keyboard::inlineButton(['text' => "{$page} / {$totalPages}", 'callback_data' => 'noop']);
            if ($page < $totalPages) {
                $navRow[] = Keyboard::inlineButton(['text' => '➡️', 'callback_data' => 'energy_list_' . ($page + 1)]);
            }
            $keyboard->row($navRow);
        }

        $keyboard->row([
            Keyboard::inlineButton(['text' => '➕ Add address', 'callback_data' => 'energy_add']),
            Keyboard::inlineButton(['text' => '🔍 Search', 'callback_data' => 'energy_search']),
        ]);

        $expiredCount = $this->energyWalletRepository->countExpired(recipientIds: $recipientIds);
        $expiredLabel = $expiredCount > 0
            ? "⏰ Expired ({$expiredCount})"
            : '✅ None expired';
        $keyboard->row([
            Keyboard::inlineButton(['text' => $expiredLabel, 'callback_data' => 'energy_expired']),
        ]);

        $text = $total === 0
            ? "⚡ *Smart Energy*\n\nThe pool is empty. Add the first address:"
            : "⚡ *Smart Energy* · Wallet pool\n✅ - enabled, ❌ - disabled\nChoose a wallet:";

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ];

        if ($messageId) {
            $this->safeEdit(array_merge($params, ['message_id' => $messageId]));
        } else {
            Telegram::sendMessage($params);
        }
    }

    public function showExpiredEnergyWallets(int $chatId, ?int $messageId = null): void
    {
        $recipientIds = TgUser::getRecipientIds($chatId);
        $expired = $this->energyWalletRepository->getExpired(recipientIds: $recipientIds);

        $text = app(ExpiredEnergyWalletsReport::class)->buildBotMessage($expired);

        $keyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'energy_list_1']),
        ]);

        $params = [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => $keyboard,
        ];

        if ($messageId) {
            $this->safeEdit(array_merge($params, ['message_id' => $messageId]));
        } else {
            Telegram::sendMessage($params);
        }
    }

    public function showPoolWallet(int $chatId, int $poolId, int $backPage = 1, ?int $messageId = null): void
    {
        $entry = $this->energyWalletRepository->findWithRecipient($poolId);

        if (!$entry) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Wallet not found in the pool.']);
            return;
        }

        $statusText = $entry->auto_replenishment_enabled ? '✅ Enabled' : '❌ Disabled';
        $recipient = $entry->recipientWallet?->recipient?->name ?? '-';
        $label = $entry->label ?? '-';
        $toggleText = $entry->auto_replenishment_enabled ? '⚡ Disable' : '⚡ Enable';

        $text = "⚡ *Smart Energy · Wallet*\n\n"
            . "📌 `{$entry->address}`\n"
            . "🏷 Label: {$label}\n"
            . "🏢 Recipient: {$recipient}\n"
            . "⚡ Auto-replenishment: {$statusText}";

        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => $toggleText, 'callback_data' => "pool_toggle_{$poolId}_{$backPage}"]),
            ])
            ->row([
                Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => "energy_list_{$backPage}"]),
            ]);

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ];

        if ($messageId) {
            $this->safeEdit(array_merge($params, ['message_id' => $messageId]));
        } else {
            Telegram::sendMessage($params);
        }
    }

    public function askForNewEnergyAddress(int $chatId): void
    {
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "Enter a TRC-20 wallet address to add to the pool:\n\n_Example: TRxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx_",
            'parse_mode' => 'Markdown',
        ]);
        $this->saveState($chatId, 'waiting_energy_address');
    }

    public function askForEnergySearch(int $chatId): void
    {
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '🔍 Enter part of an address or label to search:',
        ]);
        $this->saveState($chatId, 'waiting_energy_search');
    }

    public function handleEnergySearchInput(int $chatId, string $query): void
    {
        $this->clearState($chatId);

        $recipientIds = TgUser::getRecipientIds($chatId);
        $results = $this->energyWalletRepository->search($query, recipientIds: $recipientIds);

        if ($results->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "🔍 Nothing found for '{$query}'.",
            ]);
            $this->showAutoReplenishment($chatId, 1);
            return;
        }

        $keyboard = Keyboard::make()->inline();

        foreach ($results as $entry) {
            $icon = $entry->auto_replenishment_enabled ? '✅' : '❌';
            $label = $entry->label ? " · {$entry->label}" : '';
            $short = substr($entry->address, 0, 6) . '...' . substr($entry->address, -4);
            $short = $entry->address;

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => "{$icon} {$short}{$label}",
                    'callback_data' => "energy_wallet_{$entry->id}_1",
                ]),
            ]);
        }

        $keyboard->row([
            Keyboard::inlineButton(['text' => '↩️ Back to list', 'callback_data' => 'energy_list_1']),
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "🔍 Search results for '{$query}': {$results->count()}",
            'reply_markup' => $keyboard,
        ]);
    }

    public function handleNewEnergyAddressInput(int $chatId, string $text, string $username): void
    {
        $trc20Regexp = RecipientWallet::blockChainTypesRegexp()[RecipientWallet::BLOCKCHAIN_TYPE_TRC_20];

        if (!preg_match($trc20Regexp, $text)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ The address does not match the TRC-20 format. Try again:',
            ]);
            return;
        }

        if ($this->energyWalletRepository->existsByAddress($text)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '⚠️ This address is already in the pool.',
            ]);
            $this->clearState($chatId);
            $this->showAutoReplenishment($chatId, 1);
            return;
        }

        // If the address belongs to a Recipient wallet, link it and take the label from it
        $recipientWallet = $this->recipientWalletRepository->getByAddress($text);
        $label = $recipientWallet
            ? (($recipientWallet->recipient?->name ? $recipientWallet->recipient->name . ' / ' : '') . $recipientWallet->type_name)
            : null;

        $recipientIds = TgUser::getRecipientIds($chatId);

        $this->energyWalletRepository->create([
            'recipient_id' => !empty($recipientIds) ? head($recipientIds) : null,
            'recipient_wallet_id' => $recipientWallet?->id,
            'address' => $text,
            'label' => $label,
            'auto_replenishment_enabled' => true,
            'telegram_chat_id' => (int)$chatId,
        ], "tg:{$chatId}:@{$username}");

        $this->clearState($chatId);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '✅ Address added to the pool and enabled.',
        ]);

        $this->showAutoReplenishment($chatId, 1);
    }

    public function togglePoolWallet(int $chatId, int $poolId, string $username, int $backPage = 1, ?int $messageId = null): void
    {
        $entry = $this->energyWalletRepository->find($poolId);

        if (!$entry) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Wallet not found in the pool.']);
            return;
        }

        $this->energyWalletRepository->toggle($entry, "tg:{$chatId}:@{$username}");

        $this->showPoolWallet($chatId, $poolId, $backPage, $messageId);
    }

    public function removePoolWallet(int $chatId, int $poolId, string $username, int $backPage = 1, ?int $messageId = null): void
    {
        $entry = $this->energyWalletRepository->find($poolId);

        if (!$entry) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ Wallet not found in the pool.']);
            return;
        }

        $this->energyWalletRepository->remove($entry, "tg:{$chatId}:@{$username}");

        $this->showAutoReplenishment($chatId, $backPage, $messageId);
    }

    public function handleNewAddressInput(int $chatId, int $messageId, int $recipientId, int $blockchainType, int $walletType, string $text, string $username): void
    {
        $regexp = RecipientWallet::blockChainTypesRegexp()[$blockchainType] ?? '';

        if (!preg_match($regexp, $text)) {
            Log::channel('security')->error('❌ The address does not match the required format', [
                'text' => $text,
                'recipient_id' => $recipientId,
                'type' => $walletType,
                'blockchain_type' => $blockchainType,
                'chatId' => $chatId,
                'username' => $username,
                'bot' => self::BOT_NAME,
            ]);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ The address does not match the required format. Please check it and try again.',
            ]);
            $this->askForNewAddress($chatId, $messageId, $recipientId, $blockchainType, $walletType);
            return;
        }

        $exist = $this->recipientWalletRepository->query()
            ->where('recipient_id', $recipientId)
            ->where('type', $walletType)
            ->where('blockchain_type', $blockchainType)
            ->exists();

        if ($exist) {
            Log::channel('security')->error('❌ This Recipient already has a wallet of this type!', [
                'recipient_id' => $recipientId,
                'type' => $walletType,
                'blockchain_type' => $blockchainType,
                'chatId' => $chatId,
                'username' => $username,
                'bot' => self::BOT_NAME,
            ]);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ This Recipient already has a wallet of this type!']);
            return;
        }

        if ($this->recipientWalletRepository->getByAddress($text)) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ This wallet address already exists!']);
            return;
        }

        $wallet = $this->recipientWalletRepository->store([
            'recipient_id' => $recipientId,
            'blockchain_type' => $blockchainType,
            'type' => $walletType,
            'address' => $text,
        ]);

        if (!$wallet) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ An error occurred while adding the wallet!']);
            $this->clearState($chatId);
            return;
        }

        $this->clearState($chatId);
        $this->editWallet($chatId, $wallet->id);
    }
}
