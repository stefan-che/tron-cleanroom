<?php

namespace App\Console\Commands;

use App\Services\Ledger\LedgerService;
use Illuminate\Console\Command;

// Manual ledger API check. Examples: test:ledger --health | --groups --tg-id=... | --accounts --user-id=...
// | --set-wallet --account-id=<uuid> --wallet=T...  Contract: docs/LEDGER_INTEGRATION.md.
class TestLedgerService extends Command
{
    protected $signature = 'test:ledger
                            {--health       : Check /health}
                            {--groups       : List groups}
                            {--accounts     : List accounts}
                            {--set-wallet   : Bind a wallet address to an account}
                            {--page=1       : Page number}
                            {--per-page=20  : Records per page}
                            {--tg-id=       : Filter by Telegram chat_id (for --groups)}
                            {--user-id=     : Filter by user ID (for --accounts)}
                            {--group-id=    : Filter by group UUID (for --accounts)}
                            {--account-id=  : Account UUID (for --set-wallet)}
                            {--wallet=      : Wallet address (for --set-wallet; omit = null)}';

    protected $description = 'Debug ledger API requests';

    private LedgerService $service;

    public function handle(): int
    {
        $this->service = app(LedgerService::class);

        $this->info('Ledger API — debug');
        $this->line('URL: ' . (config('services.ledger.url') ?: '<not set>'));
        $this->line('Token: ' . (config('services.ledger.token') ? '***' . substr(config('services.ledger.token'), -4) : '<not set>'));
        $this->separator();

        if (!$this->service->isConfigured()) {
            $this->warn('Integration is not configured: set LEDGER_API_URL and LEDGER_API_TOKEN.');
            return 1;
        }

        if ($this->option('health')) {
            return $this->runHealth();
        }

        if ($this->option('groups')) {
            return $this->runGroups();
        }

        if ($this->option('accounts')) {
            return $this->runAccounts();
        }

        if ($this->option('set-wallet')) {
            return $this->runSetWallet();
        }

        $this->line('Specify one of the options: --health, --groups, --accounts, --set-wallet');
        $this->line('');
        $this->line('Examples:');
        $this->line('  php artisan test:ledger --health');
        $this->line('  php artisan test:ledger --groups');
        $this->line('  php artisan test:ledger --accounts --user-id=123456789');
        $this->line('  php artisan test:ledger --set-wallet --account-id=<uuid> --wallet=TRx...');

        return 0;
    }

    private function runHealth(): int
    {
        $this->info('GET /health');
        $result = $this->service->health();
        $this->printResult($result);

        return $result['ok'] ? 0 : 1;
    }

    private function runGroups(): int
    {
        $page    = (int) $this->option('page');
        $perPage = (int) $this->option('per-page');
        $tgId    = $this->option('tg-id') !== null ? (int) $this->option('tg-id') : null;

        $this->info("GET /api/groups  page={$page} per_page={$perPage}" . ($tgId ? " tg_id={$tgId}" : ''));

        $result = $this->service->getGroups($page, $perPage, $tgId);
        $this->printResult($result);

        if ($result['ok'] && is_array($result['body'])) {
            $data = $result['body']['data'] ?? [];
            $pagination = $result['body']['pagination'] ?? [];

            $this->line('');
            $this->line('Total: ' . ($pagination['total'] ?? '?') . ' / pages: ' . ($pagination['total_pages'] ?? '?'));
            $this->line('');

            if (empty($data)) {
                $this->line('No groups.');
            } else {
                $this->table(
                    ['id', 'tg_id', 'title', 'status', 'chat_type'],
                    array_map(fn($g) => [
                        $g['id'],
                        $g['tg_id'],
                        $g['title'],
                        $g['status'],
                        $g['chat_type'],
                    ], $data)
                );
            }
        }

        return $result['ok'] ? 0 : 1;
    }

    private function runAccounts(): int
    {
        $page    = (int) $this->option('page');
        $perPage = (int) $this->option('per-page');
        $userId  = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $groupId = $this->option('group-id') ?: null;

        $this->info("GET /api/accounts  page={$page} per_page={$perPage}"
            . ($userId ? " user_id={$userId}" : '')
            . ($groupId ? " group_id={$groupId}" : ''));

        $result = $this->service->getAccounts($page, $perPage, $userId, $groupId);
        $this->printResult($result);

        if ($result['ok'] && is_array($result['body'])) {
            $data       = $result['body']['data'] ?? [];
            $pagination = $result['body']['pagination'] ?? [];

            $this->line('');
            $this->line('Total: ' . ($pagination['total'] ?? '?') . ' / pages: ' . ($pagination['total_pages'] ?? '?'));
            $this->line('');

            if (empty($data)) {
                $this->line('No accounts.');
            } else {
                $this->table(
                    ['id', 'group_id', 'user_id', 'name', 'currency', 'amount', 'wallets'],
                    array_map(fn($a) => [
                        $a['id'],
                        $a['group_id'],
                        $a['user_id'],
                        $a['name'],
                        $a['currency'],
                        $a['amount'],
                        implode(', ', array_column($a['wallets'] ?? [], 'wallet_address')) ?: ($a['wallet_address'] ?? '—'),
                    ], $data)
                );
            }
        }

        return $result['ok'] ? 0 : 1;
    }

    private function runSetWallet(): int
    {
        $accountId = $this->option('account-id');

        if (!$accountId) {
            $this->error('Specify --account-id=<uuid>');
            return 1;
        }

        $wallet = $this->option('wallet') ?: null;

        $this->info("POST /api/accounts/{$accountId}/wallets  wallet_address=" . ($wallet ?? 'null'));

        $result = $this->service->updateAccountWallet($accountId, $wallet);
        $this->printResult($result);

        if ($result['ok'] && is_array($result['body'])) {
            $a = $result['body'];
            $this->line('');
            $this->table(
                ['id', 'account_id', 'wallet_address', 'network'],
                [[$a['id'] ?? '—', $a['account_id'] ?? '—', $a['wallet_address'] ?? '—', $a['network'] ?? '—']]
            );
        }

        return $result['ok'] ? 0 : 1;
    }

    private function printResult(array $result): void
    {
        $status = $result['status'];
        $color  = $result['ok'] ? 'green' : 'red';

        $this->line("<fg={$color}>HTTP {$status}</>");

        if (!$result['ok']) {
            $body = is_array($result['body'])
                ? json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : $result['body'];

            $this->line("<fg=red>{$body}</>");
        }
    }

    private function separator(): void
    {
        $this->line('────────────────────────────────────────────────────');
    }
}
