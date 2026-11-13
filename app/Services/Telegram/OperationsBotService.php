<?php

namespace App\Services\Telegram;

use App\Enums\WalletStatuses;
use App\Helpers\TelegramHelper;
use App\Jobs\Wallet\ActivateWallet;
use App\Jobs\Wallet\AddEnergy;
use App\Jobs\Wallet\SendUsdt;
use App\Jobs\Wallet\Trx\AddTrxWallet;
use App\Jobs\Wallet\Trx\UpdateWalletsBalance;
use App\Models\Aml\AmlService;
use App\Models\Energy\EnergyService;
use App\Models\Tron\TronNode;
use App\Models\Setting;
use App\Models\Wallet\TransactionAmlCheck;
use App\Models\Wallet\Wallet;
use App\Models\Recipient\Wallet as RecipientWalletModel;
use App\Repositories\Recipient\RecipientWalletRepository;
use App\Repositories\WalletRepository;
use App\Services\Tron\TronService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class OperationsBotService
{
    /**
     * Bot name as defined in the config
     */
    const string BOT_NAME = 'operations';

    public function __construct(
        private readonly WalletRepository $walletRepository,
        private readonly WalletService    $walletService,
    )
    {
    }

    // MAIN MENU

    public function showStartMessage(int $chatId): void
    {
        $replyKeyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->row([
                Keyboard::button(['text' => '🔍 Find payment']),
                Keyboard::button(['text' => '❌ Errors']),
            ])
            ->row([
                Keyboard::button(['text' => '⚙️ Settings']),
            ]);

        Telegram::bot(self::BOT_NAME)->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Welcome to the operations bot! Choose an action:',
            'reply_markup' => $replyKeyboard,
        ]);
    }

    // WALLET SEARCH

    public function askForWalletNumber(int $chatId): void
    {
        Telegram::bot(self::BOT_NAME)->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Please enter the payment number (ID) or wallet address:',
        ]);

        $this->saveState($chatId, 'waiting_wallet_number');
    }

    public function refreshWallet(int $chatId, int $messageId, int $walletId): void
    {
        $wallet = $this->walletRepository->getById($walletId);
        if ($wallet) {
            $this->searchAndShowWallet($chatId, $wallet->external_id, $messageId);
        }
    }

    public function searchAndShowWallet(int $chatId, string $searchValue, ?int $messageId = null): void
    {
        if (!$searchValue) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Invalid payment number.']);
            return;
        }

        $result = $this->walletRepository->findByAddressOrExternalId($searchValue);
        $wallet = $result['wallet'];
        $searchType = $result['searchType'];

        if (!$wallet) {
            Log::channel('telegram')->error('❌ Payment not found', [
                'searchValue' => $searchValue,
                'searchType' => $searchType,
                'chatId' => $chatId,
                'bot' => self::BOT_NAME,
            ]);
            Telegram::bot(self::BOT_NAME)->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ Payment not found by {$searchType}: {$searchValue}.",
            ]);
            return;
        }

        $wallet->refresh();
        $wallet->load('transactions');

        $this->clearState($chatId);
        $this->showWalletInfo($chatId, $wallet, $messageId);
    }

    // WALLET INFO

    public function showWalletInfo(int $chatId, Wallet $wallet, ?int $messageId = null, ?string $backCallback = null): void
    {
        $text = $this->formatWalletInfo($wallet);
        $keyboard = $this->buildWalletInfoKeyboard($wallet->id, $backCallback);

        if ($messageId !== null) {
            try {
                Telegram::bot(self::BOT_NAME)->editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'reply_markup' => $keyboard,
                    'parse_mode' => 'HTML',
                ]);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'message is not modified')) {
                    Telegram::bot(self::BOT_NAME)->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "✅ Information is up to date\n\n" . $text,
                        'reply_markup' => $keyboard,
                        'parse_mode' => 'HTML',
                    ]);
                } else {
                    Log::channel('telegram')->error('Failed to update the Telegram message', [
                        'bot' => self::BOT_NAME,
                        'walletId' => $wallet->id,
                        'chatId' => $chatId,
                        'error' => $e->getMessage(),
                    ]);
                    Telegram::bot(self::BOT_NAME)->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $text,
                        'reply_markup' => $keyboard,
                        'parse_mode' => 'HTML',
                    ]);
                }
            }
        } else {
            Telegram::bot(self::BOT_NAME)->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => $keyboard,
                'parse_mode' => 'HTML',
            ]);
        }
    }

    public function backToWalletInfo(int $chatId, int $messageId, int $walletId): void
    {
        $wallet = $this->walletRepository->getById($walletId);
        if (!$wallet) {
            return;
        }

        Telegram::bot(self::BOT_NAME)->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $this->formatWalletInfo($wallet),
            'reply_markup' => $this->buildWalletInfoKeyboard($walletId),
            'parse_mode' => 'HTML',
        ]);
    }

    private function buildWalletInfoKeyboard(int $walletId, ?string $backCallback = null): Keyboard
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row([Keyboard::inlineButton(['text' => '⚙️ Actions', 'callback_data' => "wallet_{$walletId}"])])
            ->row([
                Keyboard::inlineButton(['text' => '🔄 Refresh', 'callback_data' => "refresh_wallet_{$walletId}"]),
                Keyboard::inlineButton(['text' => '🔍 Find another payment', 'callback_data' => 'search_wallet']),
            ]);

        if ($backCallback) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => '🔙 Back to errors', 'callback_data' => $backCallback]),
            ]);
        }

        return $keyboard;
    }

    private function formatWalletInfo(Wallet $wallet): string
    {
        $testLabel = $wallet->isTestWallet() ? ' [TEST]' : '';
        $statusName = $wallet->status_name ?? 'Unknown';
        $recipientName = $wallet->recipient->name ?? 'Not specified';

        $info = "<b>Payment #{$wallet->id} {$testLabel}</b>\n\n";
        $info .= "ID: <code>{$wallet->id}</code>\n";
        $info .= "External ID: {$wallet->external_id_format}\n";
        $info .= "Status: <b>{$statusName}</b>\n";
        $info .= "Recipient: {$recipientName}\n";
        $addressLink = $wallet->tronscan_address_link
            ? "<a href='{$wallet->tronscan_address_link}'>{$wallet->address}</a>"
            : "<code>{$wallet->address}</code>";
        $info .= "Wallet address: {$addressLink}\n";

        $addressTo = $wallet->address_to ?: '------';
        $info .= "Funds will be sent to: <code>{$addressTo}</code>\n";
        $info .= "Amount: {$wallet->amount} USDT\n";
        $info .= "TRX: {$wallet->amount_trx}\n";

        if ($wallet->type) {
            $info .= "Type: {$wallet->type_name}\n";
        }
        if ($wallet->amount_energy) {
            $info .= "Energy: {$wallet->amount_energy}\n";
        }
        if ($wallet->comment) {
            $info .= "Comment: {$wallet->comment}\n";
        }
        if ($wallet->has_error) {
            $info .= "Error: {$wallet->error}\n";
        }

        $transactions = $wallet->transactions;
        if ($transactions && is_countable($transactions) && count($transactions) > 0) {
            $info .= "\n\n <b>Transactions:</b>\n";
            foreach ($transactions as $index => $transaction) {
                $num = $index + 1;
                $amount = number_format($transaction->amount, 2, '.', ' ');

                $info .= "\n{$num}. {$amount} USDT";

                /**
                 * @var TransactionAmlCheck $check
                 */
                $amlChecks = $transaction->getLatestAmlChecks();
                $completedChecks = $amlChecks;
                if ($completedChecks->isNotEmpty()) {
                    $info .= "\n   AML:";

                    foreach ($completedChecks as $check) {
                        if ($check->isCompleted()) {
                            $risk = $check->risk_score_normalize ?? '—';
                            $info .= "\n      {$check->service->name}: {$risk}";
                        } else {
                            $info .= "\n      {$check->service->name}: 🔄 in progress";
                        }
                    }
                } else {
                    $info .= "\n   AML: not checked";
                }

                $info .= "\n   Hash: <code>{$transaction->tx_id}</code>";

                if ($transaction->from_address) {
                    $info .= "\n   From: <code>{$transaction->from_address}</code>";
                }

                $info .= "\n   Created at: " . $transaction->created_at->format('d.m.Y H:i');
            }
        }

        $info .= "\n\n Payment created: " . $wallet->created_at->format('d.m.Y H:i');

        return $info;
    }

    // ERROR WALLETS

    public function showErrorWallets(int $chatId, ?int $messageId = null): void
    {
        $wallets = Wallet::where('has_error', 1)
            ->orderByDesc('created_at')
            ->whereNotIn('status', [WalletStatuses::COMPLETED->value])
            ->limit(10)
            ->get();

        if ($wallets->isEmpty()) {
            $text = '✅ No wallets with errors.';
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'back_to_start']),
            ]);
        } else {
            $text = "❌ <b>Wallets with errors</b> ({$wallets->count()}):\n\n";
            foreach ($wallets as $wallet) {
                $text .= "• #{$wallet->id} {$wallet->external_id_format} - {$wallet->created_at->format('d.m.Y H:i')}\n";
            }

            $keyboard = Keyboard::make()->inline();
            foreach ($wallets as $wallet) {
                $keyboard->row([
                    Keyboard::inlineButton([
                        'text' => "#{$wallet->id} {$wallet->external_id_format} - {$wallet->amount}",
                        'callback_data' => "error_wallet_{$wallet->id}",
                    ]),
                ]);
            }
            $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'back_to_start'])]);
        }

        TelegramHelper::editOrSend(self::BOT_NAME, $chatId, $messageId, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard,
            'parse_mode' => 'HTML',
        ]);
    }

    public function showErrorWalletById(int $chatId, int $walletId, ?int $messageId = null): void
    {
        $wallet = $this->walletRepository->getById($walletId);
        if (!$wallet) {
            return;
        }

        $wallet->refresh();
        $wallet->load('transactions');
        $this->showWalletInfo($chatId, $wallet, $messageId, 'error_wallets');
    }

    // WALLET ACTIONS

    public function showWalletActions(int $chatId, int $messageId, int $walletId): void
    {
        $wallet = $this->walletRepository->getById($walletId);

        if (!$wallet) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Payment not found.']);
            return;
        }

        $isNotCompleted = !$wallet->isCompleted();
        $isNotTest = !$wallet->isTestWallet();
        $keyboard = Keyboard::make()->inline();

        if (!$wallet->address_to && $isNotCompleted && $isNotTest) {
            $recipientWalletRepo = new RecipientWalletRepository();
            $whiteWallet = $recipientWalletRepo->getRecipientWalletByType($wallet->recipient_id, RecipientWalletModel::TYPE_WHITE);
            $grayWallet = $recipientWalletRepo->getRecipientWalletByType($wallet->recipient_id, RecipientWalletModel::TYPE_GRAY);
            $yellowWallet = $recipientWalletRepo->getRecipientWalletByType($wallet->recipient_id, RecipientWalletModel::TYPE_YELLOW);

            $whiteLabel = '🟢 WHITE address' . ($whiteWallet ? ' (…' . substr($whiteWallet->address, -6) . ')' : '');
            $grayLabel = '⚪️ GRAY address' . ($grayWallet ? ' (…' . substr($grayWallet->address, -6) . ')' : '');
            $yellowLabel = '🟡 YELLOW address' . ($yellowWallet ? ' (…' . substr($yellowWallet->address, -6) . ')' : '');

            $keyboard->row([Keyboard::inlineButton(['text' => $whiteLabel, 'callback_data' => "action_set_white_{$walletId}"])]);
            $keyboard->row([Keyboard::inlineButton(['text' => $grayLabel, 'callback_data' => "action_set_gray_{$walletId}"])]);
            $keyboard->row([Keyboard::inlineButton(['text' => $yellowLabel, 'callback_data' => "action_set_yellow_{$walletId}"])]);
        }

        if ($isNotTest) {
            $keyboard->row([Keyboard::inlineButton(['text' => '🔄 Refresh TRX balance', 'callback_data' => "action_refresh_trx_{$walletId}"])]);
            $keyboard->row([Keyboard::inlineButton(['text' => '🔄 Refresh USDT balance', 'callback_data' => "action_refresh_usdt_{$walletId}"])]);
            $keyboard->row([Keyboard::inlineButton(['text' => '🔄 Refresh energy', 'callback_data' => "action_refresh_energy_{$walletId}"])]);
            $keyboard->row([Keyboard::inlineButton(['text' => 'Add extra TRX', 'callback_data' => "action_add_trx_{$walletId}"])]);
        }

        if (!$wallet->isNew()) {
            $keyboard->row([Keyboard::inlineButton(['text' => '📝 Set status "New"', 'callback_data' => "action_set_status_new_{$walletId}"])]);
        }

        if ($isNotCompleted && $isNotTest && !$wallet->amount_trx) {
            $keyboard->row([Keyboard::inlineButton(['text' => '🔄 Activate wallet (add TRX)', 'callback_data' => "action_activate_{$walletId}"])]);
        }

        if ($wallet->amount_trx && $isNotCompleted && $isNotTest) {
            $keyboard->row([Keyboard::inlineButton(['text' => '⚡ Request energy', 'callback_data' => "action_request_energy_{$walletId}"])]);
        }

        //todo move these checks into a button visibility decorator
        if ($wallet->address_to && $wallet->amount && $isNotCompleted && $isNotTest) {
            $keyboard->row([Keyboard::inlineButton(['text' => '💸 Send USDT', 'callback_data' => "action_send_usdt_{$walletId}"])]);
        }

        if ($isNotCompleted) {
            $keyboard->row([Keyboard::inlineButton(['text' => '📝 Transaction check finished', 'callback_data' => "action_set_status_check_transaction_finish_{$walletId}"])]);
        }

        if ($isNotCompleted && !$wallet->isTransferMoneyProcessFinish()) {
            $keyboard->row([Keyboard::inlineButton(['text' => '📝 Set status "Funds transfer finished"', 'callback_data' => "action_set_status_transfer_money_process_finish_{$walletId}"])]);
        }

        if ($isNotCompleted) {
            $keyboard->row([Keyboard::inlineButton(['text' => '✅ Set status "Completed"', 'callback_data' => "action_set_status_completed_{$walletId}"])]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back to details', 'callback_data' => "back_to_wallet_{$walletId}"])]);

        Telegram::bot(self::BOT_NAME)->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "Choose an action for payment #{$walletId}:",
            'reply_markup' => $keyboard,
        ]);
    }

    public function executeAction(int $chatId, int $messageId, string $action, int $walletId): void
    {
        $wallet = $this->walletRepository->getById($walletId);

        if (!$wallet) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Payment not found.']);
            return;
        }

        $actionName = $this->getActionName($action);

        try {
            switch ($action) {
                case 'request_energy':
                    AddEnergy::dispatch($walletId);
                    break;
                case 'activate':
                    ActivateWallet::dispatch($walletId);
                    break;
                case 'send_usdt':
                    SendUsdt::dispatch($walletId);
                    break;
                case 'set_white':
                    $this->walletService->setWhiteWallet($wallet);
                    break;
                case 'set_gray':
                    $this->walletService->setGrayWallet($wallet);
                    break;
                case 'set_yellow':
                    $this->walletService->setYellowWallet($wallet);
                    break;
                case 'refresh_trx':
                    UpdateWalletsBalance::dispatch($walletId);
                    break;
                case 'add_trx':
                    AddTrxWallet::dispatch($walletId, Wallet::ADDITIONAL_TRX);
                    break;
                case 'refresh_usdt':
                    $this->walletService->reCalcUsdtBalance($wallet);
                    break;
                case 'refresh_energy':
                    $energy = app(TronService::class)->getAvailableEnergy($wallet->address);
                    $wallet->update(['amount_energy' => max($energy, 0)]);
                    break;
                case 'set_status_new':
                    $this->walletService->setStatusNew($wallet);
                    break;
                case 'set_status_check_transaction_finish':
                    $this->walletService->setStatusCheckTransactionFinish($wallet);
                    break;
                case 'set_status_completed':
                    $this->walletService->setStatusCompleted($wallet);
                    break;
                case 'set_status_transfer_money_process_finish':
                    $this->walletService->setStatusTransferMoneyProcessFinish($wallet);
                    break;
                default:
                    Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => "❌ Unknown action: {$action}"]);
                    return;
            }

            Telegram::bot(self::BOT_NAME)->sendMessage([
                'chat_id' => $chatId,
                'text' => "✅ Action '{$actionName}' for payment #{$walletId} started successfully!",
            ]);
        } catch (\Exception $e) {
            Log::channel('telegram')->error('Failed to execute action via the operations bot', [
                'bot' => self::BOT_NAME,
                'action' => $action,
                'walletId' => $walletId,
                'chatId' => $chatId,
                'error' => $e->getMessage(),
            ]);
            Telegram::bot(self::BOT_NAME)->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ Failed to execute the action: {$e->getMessage()}",
            ]);
        }
    }

    private function getActionName(string $action): string
    {
        return [
            'request_energy' => 'Request energy',
            'activate' => 'Activate wallet',
            'send_usdt' => 'Send USDT',
            'refresh_trx' => 'Refresh TRX balance',
            'refresh_usdt' => 'Refresh USDT balance',
            'refresh_energy' => 'Refresh energy',
            'set_status_new' => 'Set status "New"',
            'set_status_check_transaction_finish' => 'Set status "Transaction check finished"',
            'set_status_completed' => 'Set status "Completed"',
            'set_status_transfer_money_process_finish' => 'Set status "Funds transfer finished"',
            'set_white' => 'Set WHITE destination address',
            'set_gray' => 'Set GRAY destination address',
            'set_yellow' => 'Set YELLOW destination address',
        ][$action] ?? $action;
    }

    // SETTINGS

    private function getBotEditableSettings(): array
    {
        return [
            Setting::AML_SUM_CHECK => 'Min. amount for AML check',
            Setting::AI_AML_CHECK_ENABLED => 'AI transaction check (1 - yes, 0 - no)',
            Setting::AUTO_SEND_SELL_ENABLED => 'Auto-send for payout (sell) operations (1 - yes, 0 - no)',
        ];
    }

    public function showSettingsMenu(int $chatId, ?int $messageId = null): void
    {
        $keyboard = Keyboard::make()->inline()
            ->row([Keyboard::inlineButton(['text' => '🔧 Settings', 'callback_data' => 'settings_list'])])
            ->row([Keyboard::inlineButton(['text' => '🛡 AML services', 'callback_data' => 'aml_services'])])
            ->row([Keyboard::inlineButton(['text' => '⚡ Energy services', 'callback_data' => 'energy_services'])])
            ->row([Keyboard::inlineButton(['text' => '🌐 TRON nodes', 'callback_data' => 'tron_nodes'])])
            ->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'back_to_start'])]);

        TelegramHelper::editOrSend(self::BOT_NAME, $chatId, $messageId, [
            'chat_id' => $chatId,
            'text' => "⚙️ <b>Settings management</b>\n\nChoose a section:",
            'reply_markup' => $keyboard,
            'parse_mode' => 'HTML',
        ]);
    }

    public function showSettingsList(int $chatId, ?int $messageId = null): void
    {
        $editableSettings = $this->getBotEditableSettings();
        $text = "🔧 <b>Settings</b>\n\n";
        $keyboard = Keyboard::make()->inline();

        foreach ($editableSettings as $key => $label) {
            $setting = Setting::where('key', $key)->first();
            $currentValue = $setting?->value ?? '-';
            $description = $setting?->description ?? '';

            $text .= "• <b>{$label}</b>\n";
            $text .= "  Value: <code>{$currentValue}</code>\n";
            if ($description) {
                $text .= "  <i>{$description}</i>\n";
            }
            $text .= "\n";

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => "✏️ {$label}: {$currentValue}",
                    'callback_data' => "setting_edit_{$key}",
                ]),
            ]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'settings_menu'])]);

        TelegramHelper::editOrSend(self::BOT_NAME, $chatId, $messageId, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard,
            'parse_mode' => 'HTML',
        ]);
    }

    public function askForSettingValue(int $chatId, string $key): void
    {
        $editableSettings = $this->getBotEditableSettings();

        if (!array_key_exists($key, $editableSettings)) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Setting not found.']);
            return;
        }

        $setting = Setting::where('key', $key)->first();
        $label = $editableSettings[$key];
        $currentValue = $setting?->value ?? '-';

        Telegram::bot(self::BOT_NAME)->sendMessage([
            'chat_id' => $chatId,
            'text' => "✏️ <b>{$label}</b>\n\nCurrent value: <code>{$currentValue}</code>\n\nEnter a new value:",
            'parse_mode' => 'HTML',
        ]);

        $this->saveState($chatId, "waiting_setting_value:{$key}");
    }

    public function saveSettingValue(int $chatId, string $key, string $value): void
    {
        $editableSettings = $this->getBotEditableSettings();

        if (!array_key_exists($key, $editableSettings)) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Setting not found.']);
            $this->clearState($chatId);
            return;
        }

        try {
            Setting::where('key', $key)->update(['value' => $value]);
            $this->clearState($chatId);

            $label = $editableSettings[$key];
            Telegram::bot(self::BOT_NAME)->sendMessage([
                'chat_id' => $chatId,
                'text' => "✅ <b>{$label}</b> updated.\nNew value: <code>{$value}</code>",
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::channel('telegram')->error('Failed to save a setting via the operations bot', [
                'bot' => self::BOT_NAME,
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            Telegram::bot(self::BOT_NAME)->sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ Failed to save: {$e->getMessage()}",
            ]);
        }
    }

    // AML SERVICES

    public function showAmlServices(int $chatId, ?int $messageId = null): void
    {
        $services = AmlService::all();
        $text = "🛡 <b>AML services</b>\n\n";
        $keyboard = Keyboard::make()->inline();

        foreach ($services as $service) {
            $icon = $service->is_enabled ? '🟢' : '🔴';
            $text .= "{$icon} <b>{$service->name}</b> (<code>{$service->code}</code>)\n";

            $toggleLabel = $service->is_enabled ? '🟢 Turn off' : '🔴 Turn on';
            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => "{$toggleLabel} {$service->name}",
                    'callback_data' => "toggle_aml_{$service->id}",
                ]),
            ]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'settings_menu'])]);

        TelegramHelper::editOrSend(self::BOT_NAME, $chatId, $messageId, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard,
            'parse_mode' => 'HTML',
        ]);
    }

    public function toggleAmlService(int $chatId, ?int $messageId, int $serviceId): void
    {
        $service = AmlService::find($serviceId);

        if (!$service) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ AML service not found.']);
            return;
        }

        $service->update(['is_enabled' => !$service->is_enabled]);
        $this->showAmlServices($chatId, $messageId);
    }

    // ENERGY SERVICES

    public function showEnergyServices(int $chatId, ?int $messageId = null): void
    {
        $services = EnergyService::orderBy('priority')->get();
        $text = "⚡ <b>Energy services</b>\n\n";
        $keyboard = Keyboard::make()->inline();

        foreach ($services as $service) {
            $icon = $service->is_enabled ? '🟢' : '🔴';
            $text .= "{$icon} <b>{$service->name}</b> (priority: {$service->priority})\n";

            $toggleLabel = $service->is_enabled ? '🟢 Turn off' : '🔴 Turn on';
            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => "{$toggleLabel} {$service->name}",
                    'callback_data' => "toggle_energy_{$service->id}",
                ]),
            ]);
        }

        $text .= "\n<i>Only one service can be active at a time.</i>";
        $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'settings_menu'])]);

        TelegramHelper::editOrSend(self::BOT_NAME, $chatId, $messageId, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard,
            'parse_mode' => 'HTML',
        ]);
    }

    public function toggleEnergyService(int $chatId, ?int $messageId, int $serviceId): void
    {
        $service = EnergyService::find($serviceId);

        if (!$service) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Energy service not found.']);
            return;
        }

        $newStatus = !$service->is_enabled;

        if ($newStatus) {
            EnergyService::where('id', '!=', $serviceId)
                ->where('is_enabled', true)
                ->update(['is_enabled' => false]);
        }

        $service->update(['is_enabled' => $newStatus]);
        $this->showEnergyServices($chatId, $messageId);
    }

    // TRON NODES

    public function showTronNodes(int $chatId, ?int $messageId = null): void
    {
        $nodes = TronNode::byPriority()->get();
        $text = "🌐 <b>TRON nodes</b>\n\n";
        $keyboard = Keyboard::make()->inline();

        foreach ($nodes as $node) {
            $icon = match (true) {
                !$node->is_enabled => '⚪️',
                $node->is_available => '🟢',
                default => '🔴',
            };

            $text .= "{$icon} <b>{$node->name}</b> (priority: {$node->priority})\n";
            $text .= "  <code>{$node->full_node}</code>\n";
            $text .= '  Status: ' . $node->getStatusLabel();

            if ($node->last_block) {
                $text .= ", block: {$node->last_block}";
            }

            $text .= "\n";

            if (!$node->is_available && $node->last_error) {
                $text .= "  <i>{$node->last_error}</i>\n";
            }

            $text .= "\n";

            $toggleLabel = $node->is_enabled ? '🟢 Turn off' : '🔴 Turn on';
            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => "{$toggleLabel} {$node->name}",
                    'callback_data' => "toggle_node_{$node->id}",
                ]),
            ]);
        }

        $text .= "<i>The first enabled and available node by priority is used.</i>";

        $keyboard->row([Keyboard::inlineButton(['text' => '↩️ Back', 'callback_data' => 'settings_menu'])]);

        TelegramHelper::editOrSend(self::BOT_NAME, $chatId, $messageId, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard,
            'parse_mode' => 'HTML',
        ]);
    }

    public function toggleTronNode(int $chatId, ?int $messageId, int $nodeId): void
    {
        $node = TronNode::find($nodeId);

        if (!$node) {
            Telegram::bot(self::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Node not found.']);
            return;
        }

        $node->update(['is_enabled' => !$node->is_enabled]);
        $this->showTronNodes($chatId, $messageId);
    }

    // STATE

    public function saveState(int $chatId, string $state): void
    {
        $key = "telegram_operations_state:{$chatId}";
        Redis::set($key, $state);
        Redis::expire($key, 3600);
    }

    public function getState(int $chatId): ?string
    {
        $key = "telegram_operations_state:{$chatId}";
        return Redis::get($key);
    }

    public function clearState(int $chatId): void
    {
        $key = "telegram_operations_state:{$chatId}";
        Redis::del($key);
    }
}
