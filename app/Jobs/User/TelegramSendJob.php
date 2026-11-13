<?php

namespace App\Jobs\User;

use App\Helpers\TelegramHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TelegramSendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(readonly string $telegramId, readonly string $message)
    {
    }

    public function handle(): void
    {
        TelegramHelper::sendMessage($this->message, $this->telegramId);
    }
}
