<?php
/**
 * php artisan alert:energy-wallets-expired-by-user
 */

namespace App\Console\Commands\Alerts\Energy;

use App\Helpers\TelegramHelper;
use App\Models\Energy\EnergyWallet;
use App\Models\Recipient\TgUser;
use App\Reports\ExpiredEnergyWalletsReport;
use Illuminate\Console\Command;

class AlertEnergyWalletsExpiredByUser extends Command
{
    protected $signature = 'alert:energy-wallets-expired-by-user';
    protected $description = 'Personal reminder to each user about enabled auto-top-up wallets of their recipients that have expired';

    public function handle(): void
    {
        $users = TgUser::with('recipients')->get();

        $notified = 0;

        foreach ($users as $user) {
            $recipientIds = $user->recipients->pluck('id')->all();

            if (empty($recipientIds)) {
                continue;
            }

            $expired = EnergyWallet::query()
                ->where('auto_replenishment_enabled', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->where(function ($q) use ($recipientIds, $user) {
                    $q->orWhere('telegram_chat_id', $user->telegram_id);
                    if (!empty($recipientIds)) {
                        $q->orWhereHas('recipientWallet', fn($q2) => $q2->whereIn('recipient_id', $recipientIds));
                    }
                })
                ->with('recipient')
                ->get();

            if ($expired->isEmpty()) {
                continue;
            }

            $message = app(ExpiredEnergyWalletsReport::class)->buildAlertMessage($expired);

            TelegramHelper::sendMessage($message, $user->telegram_id);

            $notified++;
            $this->line("Sent to user {$user->telegram_id}: {$expired->count()} wallets.");
            sleep(1);
        }

        if ($notified === 0) {
            $this->info('No user has expired enabled wallets.');
        } else {
            $this->warn("Notifications sent to {$notified} users.");
        }
    }
}
