<?php

/**
 * php artisan operations-telegram:poll
 */

namespace App\Console\Commands\Telegram;

use App\Http\Controllers\Telegram\OperationsTelegramController;
use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class OperationsTelegramPollCommand extends Command
{
    protected $signature = 'operations-telegram:poll';
    protected $description = 'Polling for the operations bot (local development)';

    public function handle()
    {
        $this->info('Starting telegram listener for the operations bot...');

        $controller = app(OperationsTelegramController::class);
        $offset = 0;

        while (true) {
            $updates = Telegram::bot('operations')->getUpdates(['offset' => $offset, 'limit' => 100]);

            foreach ($updates as $update) {
                $controller->handleUpdate($update);
                $offset = $update->getUpdateId() + 1;
            }

            sleep(1);
        }
    }
}
