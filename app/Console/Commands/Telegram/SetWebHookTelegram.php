<?php
/**
 * php artisan set-wh-telegram:run
 * php artisan set-wh-telegram:run --force          # no confirmation (deploy)
 * php artisan set-wh-telegram:run --keep-pending   # keep the pending update queue
 */

namespace App\Console\Commands\Telegram;

use App\Helpers\Development;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SetWebHookTelegram extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'set-wh-telegram:run
                            {--force : Do not ask for confirmation}
                            {--keep-pending : Do not drop pending updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Telegram webhooks (with preview and confirmation)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeStart = microtime(true);

        $plans = array_filter([
            $this->plan('main', config('telegram.default'), 'telegram.webhook'),
            $this->plan('operations', 'operations', 'telegram.operations.webhook'),
        ]);

        if (!$plans) {
            $this->error('No bot has a token set — nothing to install');

            return self::FAILURE;
        }

        $this->showConfigState();
        $this->showPlans($plans);

        if (!$this->option('force') && !$this->confirm('Set webhooks with these parameters?')) {
            $this->warn('Cancelled, nothing changed');

            return self::SUCCESS;
        }

        foreach ($plans as $i => $plan) {
            if ($i > 0) {
                sleep(1);
            }

            $this->setWebhook($plan);
        }

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));

        return self::SUCCESS;
    }

    /**
     * Build the request parameters without sending anything.
     */
    private function plan(string $name, string $bot, string $route): ?array
    {
        $token = config("telegram.bots.{$bot}.token");

        if (!$token) {
            return null;
        }

        return [
            'name'   => $name,
            'token'  => $token,
            'params' => [
                'url'                  => route($route),
                'secret_token'         => config("telegram.bots.{$bot}.secret_token"),
                'max_connections'      => 40,
                'drop_pending_updates' => !$this->option('keep-pending'),
            ],
        ];
    }

    /**
     * Config cache state. The command takes the url from route(), i.e. from APP_URL
     * and APP_DOMAIN; if the config was cached before .env was edited, the values
     * are stale and the webhook points to the wrong place. Only a date comparison
     * catches this — artisan itself stays silent.
     */
    private function showConfigState(): void
    {
        $cache = base_path('bootstrap/cache/config.php');
        $env   = app()->environmentFilePath();

        $this->line('APP_URL: ' . config('app.url'));

        if (!file_exists($cache)) {
            $this->line('Config cache: absent, values are read from .env');

            return;
        }

        $cachedAt = filemtime($cache);
        $envAt    = file_exists($env) ? filemtime($env) : 0;

        $this->line('Config cache: built ' . date('Y-m-d H:i:s', $cachedAt));

        if ($envAt > $cachedAt) {
            $this->warn(
                '.env modified AFTER the cache (' . date('Y-m-d H:i:s', $envAt) . ') — '
                . 'values below are stale, run php artisan config:cache first'
            );
        }
    }

    private function showPlans(array $plans): void
    {
        foreach ($plans as $plan) {
            $params = $plan['params'];

            $this->newLine();
            $this->line("=== {$plan['name']} ===");

            $this->table(['Parameter', 'Value'], [
                ['url', $params['url']],
                ['token', $plan['token']],
                ['secret_token', $params['secret_token'] ? 'set' : 'NOT SET'],
                ['max_connections', $params['max_connections']],
                ['drop_pending_updates', $params['drop_pending_updates'] ? 'yes' : 'no'],
                ['currently set', $this->currentUrl($plan['token'])],
            ]);

            // Telegram accepts webhooks over https only.
            if (!str_starts_with($params['url'], 'https://')) {
                $this->error('url is not https — Telegram will reject this webhook');
            }

            if (!$params['secret_token']) {
                $this->warn('secret_token is empty: TgSecretTokenCheck will reject Telegram requests');
            }

            if ($params['drop_pending_updates']) {
                $this->warn('pending updates will be dropped (use --keep-pending to keep them)');
            }
        }
    }

    /**
     * What is registered in Telegram right now, to show what exactly
     * is changing.
     */
    private function currentUrl(string $token): string
    {
        // The preview must not fail when api.telegram.org is unreachable:
        // the other parameters are still worth showing.
        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getWebhookInfo");
        } catch (ConnectionException $e) {
            return '(cannot reach api.telegram.org: ' . $e->getMessage() . ')';
        }

        if (!$response->successful()) {
            return '(failed to fetch: ' . $response->status() . ')';
        }

        $url   = $response->json('result.url');
        $error = $response->json('result.last_error_message');

        return ($url ?: '(not set)') . ($error ? " [error: {$error}]" : '');
    }

    private function setWebhook(array $plan): void
    {
        $response = Http::post(
            "https://api.telegram.org/bot{$plan['token']}/setWebhook",
            $plan['params']
        );

        if ($response->successful()) {
            $this->info("[{$plan['name']}] Webhook set successfully");
            $this->info('Response: ' . $response->body());
        } else {
            $this->error("[{$plan['name']}] Failed to set webhook");
            $this->error('Status: ' . $response->status());
            $this->error('Response: ' . $response->body());
        }
    }

}
