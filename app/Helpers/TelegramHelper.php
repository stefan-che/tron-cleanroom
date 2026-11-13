<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramHelper
{

    public static function copyTelegramStyle(string $value): string
    {
        return '<code>' . htmlspecialchars($value, ENT_QUOTES) . '</code>';
    }

    public static function sendMessage(string $message, string $chatId = ''): void
    {
        $chat = $chatId ?: config('telegram.bots.' . config('telegram.default') . '.chat_id');
        if (!$chat) {
            return;
        }

        try {
            Telegram::sendMessage([
                'chat_id' => $chat,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage error: ' . $e->getMessage());
        }
    }

    public static function editOrSend(string $bot, int $chatId, ?int $messageId, array $params): void
    {
        if ($messageId !== null) {
            try {
                Telegram::bot($bot)->editMessageText(array_merge($params, ['message_id' => $messageId]));
            } catch (\Exception $e) {
                if (!str_contains($e->getMessage(), 'message is not modified')) {
                    Telegram::bot($bot)->sendMessage($params);
                }
            }
        } else {
            Telegram::bot($bot)->sendMessage($params);
        }
    }
}
