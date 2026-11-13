<?php
/**
 * php artisan wh-info-telegram:run
 */

namespace App\Console\Commands\Telegram;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class GetWebHookInfoTelegram extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wh-info-telegram:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show webhooks registered in Telegram and their delivery errors';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->showInfo(
            'main',
            config('telegram.bots.' . config('telegram.default') . '.token'),
            route('telegram.webhook')
        );

        $this->showInfo(
            'operations',
            config('telegram.bots.operations.token'),
            route('telegram.operations.webhook')
        );
    }

    private function showInfo(string $name, ?string $token, string $expectedUrl): void
    {
        $this->newLine();
        $this->line("=== {$name} ===");

        if (!$token) {
            $this->warn('Token not set, bot skipped');

            return;
        }

        $response = Http::get("https://api.telegram.org/bot{$token}/getWebhookInfo");

        if (!$response->successful()) {
            $this->error('Request error: ' . $response->status() . ' ' . $response->body());

            return;
        }

        $info = $response->json('result', []);

        $this->table(['Field', 'Value'], [
            ['url', $info['url'] ?? '(not set)'],
            ['expected', $expectedUrl],
            ['ip_address', $info['ip_address'] ?? '—'],
            ['pending_update_count', $info['pending_update_count'] ?? 0],
            ['max_connections', $info['max_connections'] ?? '—'],
            ['has_custom_certificate', ($info['has_custom_certificate'] ?? false) ? 'yes' : 'no'],
            ['last_error_date', $this->formatDate($info['last_error_date'] ?? null)],
            ['last_error_message', $info['last_error_message'] ?? '—'],
            ['last_synchronization_error_date', $this->formatDate($info['last_synchronization_error_date'] ?? null)],
        ]);

        // A url mismatch with what route() generates is the most common cause
        // of a silent bot: the webhook still has the old scheme or domain,
        // so updates go to the wrong place.
        if (($info['url'] ?? null) !== $expectedUrl) {
            $this->warn('url does not match route() — run php artisan set-wh-telegram:run');
        }

        if (!empty($info['last_error_message'])) {
            $this->error('Last delivery error: ' . $info['last_error_message']);
        }

        if (($info['pending_update_count'] ?? 0) > 0) {
            $this->warn('Pending updates: ' . $info['pending_update_count']);
        }
    }

    private function formatDate(?int $timestamp): string
    {
        if (!$timestamp) {
            return '—';
        }

        return Carbon::createFromTimestamp($timestamp)->toDateTimeString()
            . ' (' . Carbon::createFromTimestamp($timestamp)->diffForHumans() . ')';
    }

}
