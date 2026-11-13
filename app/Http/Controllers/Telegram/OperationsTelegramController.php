<?php

namespace App\Http\Controllers\Telegram;

use App\Helpers\StringHelper;
use App\Models\Setting;
use App\Models\Wallet\Wallet;
use App\Services\Telegram\OperationsBotService;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class OperationsTelegramController extends BaseTelegramController
{

    public function __construct(private readonly OperationsBotService $botService)
    {
    }

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
                Log::channel('telegram')->error('🚨 Sorry, I can only process text messages.', [
                    'message' => $message,
                    'chatId' => $chatId,
                    'bot' => OperationsBotService::BOT_NAME,
                ]);

                Telegram::bot(OperationsBotService::BOT_NAME)->sendMessage([
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

            case '/search':
                $this->botService->askForWalletNumber($chatId);
                break;

            default:
                if (str_starts_with($command, '/' . Wallet::PREFIX_EXTERNAL_ID)) {
                    $externalId = StringHelper::getExternalId($command);
                    $this->botService->searchAndShowWallet($chatId, $externalId);
                    break;
                }

                Log::channel('telegram')->error('🚨 unknown command', [
                    'command' => $command,
                    'chatId' => $chatId,
                    'bot' => OperationsBotService::BOT_NAME,
                ]);
                Telegram::bot(OperationsBotService::BOT_NAME)->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Sorry, I do not understand this command. Use /start or /search to find a payment.',
                ]);
        }
    }

    private function handleCallback($callbackQuery): void
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $data = $callbackQuery->getData();

        match (true) {
            str_starts_with($data, 'wallet_') => $this->botService->showWalletActions($chatId, $messageId, (int)substr($data, 7)),
            str_starts_with($data, 'back_to_wallet_') => $this->botService->backToWalletInfo($chatId, $messageId, (int)substr($data, 15)),
            str_starts_with($data, 'refresh_wallet_') => $this->botService->refreshWallet($chatId, $messageId, (int)substr($data, 15)),
            str_starts_with($data, 'action_') => $this->dispatchWalletAction($chatId, $messageId, $data),
            str_starts_with($data, 'error_wallet_') => $this->botService->showErrorWalletById($chatId, (int)substr($data, 13), $messageId),
            str_starts_with($data, 'setting_edit_') => $this->botService->askForSettingValue($chatId, substr($data, 13)),
            str_starts_with($data, 'toggle_aml_') => $this->botService->toggleAmlService($chatId, $messageId, (int)substr($data, 11)),
            str_starts_with($data, 'toggle_energy_') => $this->botService->toggleEnergyService($chatId, $messageId, (int)substr($data, 14)),
            str_starts_with($data, 'toggle_node_') => $this->botService->toggleTronNode($chatId, $messageId, (int)substr($data, 12)),
            $data === 'search_wallet' => $this->botService->askForWalletNumber($chatId),
            $data === 'error_wallets' => $this->botService->showErrorWallets($chatId, $messageId),
            $data === 'back_to_start' => $this->botService->showStartMessage($chatId),
            $data === 'settings_menu' => $this->botService->showSettingsMenu($chatId, $messageId),
            $data === 'settings_list' => $this->botService->showSettingsList($chatId, $messageId),
            $data === 'aml_services' => $this->botService->showAmlServices($chatId, $messageId),
            $data === 'energy_services' => $this->botService->showEnergyServices($chatId, $messageId),
            $data === 'tron_nodes' => $this->botService->showTronNodes($chatId, $messageId),
            default => null,
        };
    }

    private function handleReplyButton(int $chatId, string $text): bool
    {
        switch ($text) {
            case '🔍 Find payment':
                $this->botService->askForWalletNumber($chatId);
                return true;
            case '❌ Errors':
                $this->botService->showErrorWallets($chatId);
                return true;
            case '⚙️ Settings':
                $this->botService->showSettingsMenu($chatId);
                return true;
        }
        return false;
    }

    private function handleTextMessage(int $chatId, string $text, $message): void
    {
        if ($this->handleReplyButton($chatId, $text)) {
            return;
        }

        $state = $this->botService->getState($chatId);
        $username = $message->getFrom()->getUsername() ?? 'No username';

        if (str_starts_with((string)$state, 'waiting_setting_value:')) {
            $key = substr($state, 22);
            $this->botService->saveSettingValue($chatId, $key, trim($text));
            return;
        }

        if ($state === 'waiting_wallet_number') {
            $searchValue = trim($text);

            if (!$searchValue) {
                Telegram::bot(OperationsBotService::BOT_NAME)->sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ Invalid payment number. Please enter a payment ID or address.',
                ]);
                $this->botService->askForWalletNumber($chatId);
                return;
            }

            $this->botService->clearState($chatId);
            $this->botService->searchAndShowWallet($chatId, $searchValue);
            return;
        }

        Log::channel('telegram')->error('❌ unrecognized message', [
            'text' => $text,
            'chatId' => $chatId,
            'username' => $username,
            'bot' => OperationsBotService::BOT_NAME,
        ]);

        Telegram::bot(OperationsBotService::BOT_NAME)->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Sorry, I do not understand this message. Use the commands or menu buttons.',
        ]);
    }

    private function dispatchWalletAction(int $chatId, int $messageId, string $data): void
    {
        $parts = explode('_', substr($data, 7)); // strip 'action_'

        if (count($parts) < 2) {
            Log::channel('telegram')->error('❌ Invalid callback_data format for action', [
                'data' => $data,
                'chatId' => $chatId,
                'bot' => OperationsBotService::BOT_NAME,
            ]);
            Telegram::bot(OperationsBotService::BOT_NAME)->sendMessage(['chat_id' => $chatId, 'text' => '❌ Failed to process the action.']);
            return;
        }

        $walletId = (int)array_pop($parts);
        $action = implode('_', $parts);

        $this->botService->executeAction($chatId, $messageId, $action, $walletId);
    }

    protected function isUserAllowed(int $userId): bool
    {
        return in_array((string)$userId, Setting::getOperationsBotTelegramUsers(), true);
    }
}
