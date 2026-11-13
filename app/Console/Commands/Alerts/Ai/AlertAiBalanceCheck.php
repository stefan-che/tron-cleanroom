<?php
/**
 * php artisan alert-ai-balance-check:run
 */

declare(strict_types=1);

namespace App\Console\Commands\Alerts\Ai;

use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use App\Services\Ai\AiAmlServiceDispatcher;
use App\Services\Ai\Dto\AiServiceBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Alert on the remaining balance of AI service accounts.
 *
 * One command for all services: the dispatcher iterates over enabled ai_services records,
 * each service knows how to fetch its own spend (OpenAI - Admin API,
 * a future Claude service - its own method). Top-up amount and threshold live in the service card.
 */
class AlertAiBalanceCheck extends Command
{
    protected $signature = 'alert-ai-balance-check:run';

    protected $description = 'Checks AI service account balances and alerts when one drops below the threshold';

    public function handle(): void
    {
        $timeStart = microtime(true);

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function check(): void
    {
        $balances = (new AiAmlServiceDispatcher())->balances();

        if ($balances->isEmpty()) {
            $this->info('No enabled AI services, skipping the check.');
            return;
        }

        foreach ($balances as $balance) {
            $this->info($balance->describe());
        }

        $alerts = $this->alertLines($balances);

        if ($alerts->isEmpty()) {
            $this->info('AI services balance is OK');
            return;
        }

        $text = "❌ AI services balance\n" . $alerts->implode("\n");

        $this->alert($text);

        if (!Development::isProduction()) {
            return;
        }

        TelegramHelper::sendMessage($text);
    }

    /**
     * Alert lines: balance below threshold and broken Admin API access.
     * Services without balance monitoring configured are skipped - that is not a failure.
     *
     * @param Collection<int, AiServiceBalance> $balances
     * @return Collection<int, string>
     */
    private function alertLines(Collection $balances): Collection
    {
        return $balances
            ->filter(fn(AiServiceBalance $balance) => $balance->configured
                && (!$balance->isKnown() || $balance->isBelowThreshold()))
            ->map(fn(AiServiceBalance $balance) => ($balance->isKnown() ? '🔴' : '⚠️')
                . ' ' . $balance->describe())
            ->values();
    }
}
