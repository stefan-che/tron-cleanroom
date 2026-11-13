<?php

namespace App\Http\Controllers\Telegram;

use App\Helpers\TelegramHelper;
use App\Http\Controllers\Controller;
use App\Models\Recipient\TgUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Telegram\Bot\Laravel\Facades\Telegram;

class BaseTelegramController extends Controller
{
    const string BOT_NAME = '';

    public function handleWebhook()
    {
        $update = Telegram::getWebhookUpdate();

        // Duplicate protection: on a slow response Telegram retries with the same update_id.
        // Mark the update as processed; repeated calls are ignored.
        $updateId = $update->getUpdateId();
        if ($updateId && !$this->markUpdateProcessed($updateId)) {
            return;
        }

        if ($this->checkAccess($update)) {
            $this->handleUpdate($update);
        }
    }

    /**
     * Atomically marks the update_id as processed.
     * Returns true if the record is new (process it), false if it already existed (skip it).
     */
    private function markUpdateProcessed(int $updateId): bool
    {
        $key = 'tg_update:' . static::BOT_NAME . ':' . $updateId;
        $set = Redis::set($key, 1, 'EX', 86400, 'NX');
        return $set !== null;
    }

    protected function checkAccess($update): bool
    {
        $userId = null;
        $username = '';

        if ($update->isType('callback_query')) {
            $userId = $update->getCallbackQuery()->getFrom()->getId();
            $username = $update->getCallbackQuery()->getFrom()->getUsername() ?? 'No username';
        } elseif ($update->isType('message')) {
            $userId = $update->getMessage()->getFrom()->getId();
            $username = $update->getMessage()->getFrom()->getUsername() ?? 'No username';
        }

        if (!$userId) {
            Log::channel('security')->error('🚨 Bot access attempt with an empty userId', [
                'bot' => static::BOT_NAME,
            ]);
            return false;
        }

        if ($this->isUserAllowed($userId)) {
            return true;
        }

        Log::channel('security')->error('🚨 Unauthorized bot access attempt', [
            'User' => $username,
            'ID' => $userId,
            'bot' => static::BOT_NAME,
        ]);

        TelegramHelper::sendMessage(sprintf(
            "🚨 Unauthorized bot access attempt!\n\n" .
            "👤 User: @%s\n" .
            "🆔 ID: %s\n" .
            "⏰ Time: %s",
            $username,
            $userId,
            now()->format('d.m.Y H:i:s')
        ));

        return false;
    }

    /**
     * Checks whether the user is in the allowlist.
     * Overridden in subclasses for other bots.
     */
    protected function isUserAllowed(int $userId): bool
    {
        return TgUser::findByTelegramId($userId) !== null;
    }
}
