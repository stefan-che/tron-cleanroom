<?php

/**
 * php artisan telegram:poll
 */

namespace App\Console\Commands\Telegram;

use App\Http\Controllers\Telegram\TelegramController;
use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = '';

    public function handle()
    {
        $this->info('Starting telegram listener...');

        $controller = app(TelegramController::class);
        $offset = 0;

        while (true) {
            $updates = Telegram::getUpdates(['offset' => $offset, 'limit' => 100]);

            foreach ($updates as $update) {
                $controller->handleUpdate($update);
                $offset = $update->getUpdateId() + 1;
            }

            sleep(1);
        }
    }
}
