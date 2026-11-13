<?php

namespace App\Http\Controllers\Telegram;

use App\Services\Telegram\MainBotService;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends BaseTelegramController
{

    public function __construct(
        private readonly MainBotService $botService,
    ) {}


    public function handleUpdate($update): void
    {
        if (!$this->checkAccess($update)) {
            return;
        }

        if ($update->isType('callback_query')) {
            $this->handleCallback($update->getCallbackQuery());
        } elseif ($update->isType('message')) {
            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();

            if ($message->isType('text')) {
                $text = $message->getText();
                if (str_starts_with($text, '/')) {
                    $this->handleCommand($chatId, $text);
                } else {
                    $this->handleTextMessage($chatId, $text, $message);
                }
            } else {
                Log::channel('security')->error('🚨 Sorry, I can only process text messages.', [
                    'message' => $message,
                    'chatId' => $chatId,
                    'bot' => MainBotService::BOT_NAME,
                ]);
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Sorry, I can only process text messages.',
                ]);
            }
        }
    }

    private function handleCommand(int $chatId, string $text): void
    {
        $command = strtok($text, '@');

        switch ($command) {
            case '/start':
                $this->botService->showStartMessage($chatId);
                $this->botService->clearState($chatId);
                break;
            case '/recipients':
                $this->botService->showRecipients($chatId);
                $this->botService->clearState($chatId);
                break;
            case '/report':
                $this->botService->sendWalletsReport($chatId);
                $this->botService->clearState($chatId);
                break;
            case '/costs':
                $this->botService->sendResourceCostsReport($chatId);
                $this->botService->clearState($chatId);
                break;
            default:
                Log::channel('security')->error('🚨 unknown command', [
                    'command' => $command,
                    'chatId' => $chatId,
                    'bot' => MainBotService::BOT_NAME,
                ]);
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Sorry, I do not understand this command. Please use the available commands.',
                ]);
        }
    }

    private function handleReplyButton(int $chatId, string $text): bool
    {
        switch ($text) {
            case '🏢 Recipients':
                $this->botService->showRecipients($chatId);
                $this->botService->clearState($chatId);
                return true;
            case '📊 Report':
                $this->botService->sendWalletsReport($chatId);
                $this->botService->clearState($chatId);
                return true;
            case '⚡ Smart Energy':
                $this->botService->showAutoReplenishment($chatId);
                $this->botService->clearState($chatId);
                return true;
        }
        return false;
    }

    private function handleCallback($callbackQuery): void
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $data = $callbackQuery->getData();
        $username = $callbackQuery->getFrom()->getUsername() ?? 'unknown';

        match (true) {
            // System
            $data === 'noop' => null,

            // Smart Energy - wallet pool
            $data === 'energy_add' => $this->botService->askForNewEnergyAddress($chatId),
            $data === 'energy_search' => $this->botService->askForEnergySearch($chatId),
            $data === 'energy_expired' => $this->botService->showExpiredEnergyWallets($chatId, $messageId),
            str_starts_with($data, 'energy_list_') => $this->cbEnergyList($chatId, $messageId, $data),
            str_starts_with($data, 'energy_wallet_') => $this->cbEnergyWallet($chatId, $messageId, $data),
            str_starts_with($data, 'pool_toggle_') => $this->cbPoolToggle($chatId, $messageId, $data, $username),
            str_starts_with($data, 'pool_remove_') => $this->cbPoolRemove($chatId, $messageId, $data, $username),

            // Smart Energy - from the recipient wallet edit screen
            str_starts_with($data, 'replenishment_toggle_') => $this->cbReplenishmentToggle($chatId, $data, $username),
            str_starts_with($data, 'replenishment_add_') => $this->botService->addToPoolFromEdit($chatId, (int)substr($data, 18), $username),

            // Recipients and wallets
            $data === 'recipients_list' => $this->botService->showRecipients($chatId),
            $data === 'new_recipient' => $this->botService->askForRecipientName($chatId),
            str_starts_with($data, 'add_tg_user_') => $this->botService->askForRecipientTgUser($chatId, (int)substr($data, 12)),
            str_starts_with($data, 'recipient_') => $this->botService->showBlockchainTypes($chatId, $messageId, (int)substr($data, 10)),
            str_starts_with($data, 'blockchain_') => $this->cbBlockchain($chatId, $messageId, $data),
            str_starts_with($data, 'wallet_type_') => $this->cbWalletType($chatId, $messageId, $data),
            str_starts_with($data, 'add_wallet_') => $this->cbAddWallet($chatId, $messageId, $data),
            str_starts_with($data, 'edit_wallet_') => $this->botService->editWallet($chatId, (int)substr($data, 12)),
            str_starts_with($data, 'change_'),
            str_starts_with($data, 'set_') => $this->dispatchWalletChange($chatId, $data),

            // Ledger: link a wallet to an account (optional integration)
            str_starts_with($data, 'ledger_list_') => $this->botService->showLedgerAccounts($chatId, (int)substr($data, 12)),
            str_starts_with($data, 'ledger_pick_') => $this->cbLedger($chatId, $data, 12, 'ledgerPickAccount'),
            str_starts_with($data, 'ledger_confirm_') => $this->cbLedger($chatId, $data, 15, 'ledgerConfirmLink'),
            str_starts_with($data, 'ledger_unlink_') => $this->cbLedger($chatId, $data, 14, 'ledgerConfirmUnlink'),

            default => null,
        };
    }

    //Energy pool
    private function cbEnergyList(int $chatId, int $messageId, string $data): void
    {
        $this->botService->showAutoReplenishment($chatId, (int)substr($data, 12), $messageId);
    }

    private function cbEnergyWallet(int $chatId, int $messageId, string $data): void
    {
        [$poolId, $page] = explode('_', substr($data, 14), 2) + [0 => 0, 1 => '1'];
        $this->botService->showPoolWallet($chatId, (int)$poolId, (int)$page, $messageId);
    }

    private function cbPoolToggle(int $chatId, int $messageId, string $data, string $username): void
    {
        [$poolId, $backPage] = explode('_', substr($data, 12), 2) + [0 => 0, 1 => '1'];
        $this->botService->togglePoolWallet($chatId, (int)$poolId, $username, (int)$backPage, $messageId);
    }

    private function cbPoolRemove(int $chatId, int $messageId, string $data, string $username): void
    {
        [$poolId, $backPage] = explode('_', substr($data, 12), 2) + [0 => 0, 1 => '1'];
        $this->botService->removePoolWallet($chatId, (int)$poolId, $username, (int)$backPage, $messageId);
    }

    private function cbReplenishmentToggle(int $chatId, string $data, string $username): void
    {
        // replenishment_toggle_{poolId}_{walletId}
        [, , $poolId, $walletId] = explode('_', $data);
        $this->botService->toggleReplenishmentFromEdit($chatId, (int)$poolId, $username, (int)$walletId);
    }

    //Recipients / wallets

    private function cbBlockchain(int $chatId, int $messageId, string $data): void
    {
        [, $recipientId, $blockchainType] = explode('_', $data);
        $this->botService->showWalletTypes($chatId, $messageId, (int)$recipientId, (int)$blockchainType);
    }

    private function cbWalletType(int $chatId, int $messageId, string $data): void
    {
        [, , $recipientId, $blockchainType, $walletType] = explode('_', $data);
        $this->botService->showWallets($chatId, $messageId, (int)$recipientId, (int)$blockchainType, (int)$walletType);
    }

    private function cbAddWallet(int $chatId, int $messageId, string $data): void
    {
        [, , $recipientId, $blockchainType, $walletType] = explode('_', $data);
        $this->botService->askForNewAddress($chatId, $messageId, (int)$recipientId, (int)$blockchainType, (int)$walletType);
    }

    private function handleTextMessage(int $chatId, string $text, $message): void
    {
        // Handle reply-keyboard buttons first
        if ($this->handleReplyButton($chatId, $text)) {
            return;
        }

        $state = $this->botService->getState($chatId);
        $username = $message->getFrom()->getUsername() ?? 'No username';

        if (str_starts_with((string)$state, 'waiting_edit_address_for_')) {
            $walletId = (int)substr($state, 25);
            $this->botService->handleEditAddressInput($chatId, $walletId, $text, $username);
            return;
        }

        if (str_starts_with((string)$state, 'waiting_new_address_for_')) {
            $parts = explode('_', substr($state, 24), 4);
            if (count($parts) === 4) {
                [$messageId, $recipientId, $blockchainType, $walletType] = $parts;
                $this->botService->handleNewAddressInput($chatId, (int)$messageId, (int)$recipientId, (int)$blockchainType, (int)$walletType, $text, $username);
            }
            return;
        }

        if ($state === 'waiting_recipient_name') {
            $this->botService->handleNewRecipientInput($chatId, $text);
            return;
        }

        if (str_starts_with((string)$state, 'waiting_tg_user_for_')) {
            $this->botService->handleRecipientTgUserInput($chatId, (int)substr($state, 20), $text);
            return;
        }

        if ($state === 'waiting_energy_address') {
            $this->botService->handleNewEnergyAddressInput($chatId, $text, $username);
            return;
        }

        if ($state === 'waiting_energy_search') {
            $this->botService->handleEnergySearchInput($chatId, $text);
            return;
        }

        Log::channel('security')->error('❌ unrecognized message', [
            'text' => $text,
            'chatId' => $chatId,
            'username' => $username,
            'bot' => MainBotService::BOT_NAME,
        ]);
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Sorry, I do not understand this message. Please use the commands or menu buttons.',
        ]);
    }

    // ledger_{pick|confirm|unlink}_{accountId}_{walletId}
    private function cbLedger(int $chatId, string $data, int $prefixLength, string $method): void
    {
        [$accountId, $walletId] = explode('_', substr($data, $prefixLength), 2) + [0 => 0, 1 => 0];
        $this->botService->{$method}($chatId, (int)$accountId, (int)$walletId);
    }

    private function dispatchWalletChange(int $chatId, string $data): void
    {
        $parts = explode('_', $data);
        $walletId = (int)array_pop($parts);
        $action = implode('_', $parts);

        match ($action) {
            'change_address' => $this->botService->askForEditAddress($chatId, $walletId),
            default => Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Unknown action.']),
        };
    }
}
