<?php

declare(strict_types=1);

namespace App\Reports\AiAml;

use App\Helpers\DateHelper;
use App\Models\Aml\AmlService;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\TransactionAmlCheck;
use App\Models\Wallet\WalletAiAmlCheck;
use App\Services\Aml\AmlColorResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Report on transactions of wallets that have a completed AI AML check.
 * A report row is a transaction: scores of all AML services + the overall AI verdict + a separate CoinKYT verdict.
 */
class AiAmlChecksReport
{
    /**
     * Key prefix of the AML service score column: aml_amlbot, aml_coinkyt, etc.
     */
    public const string SERVICE_COLUMN_PREFIX = 'aml_';

    /**
     * Key prefix of the color derived from service data: color_amlbot, etc.
     * Differs from SERVICE_COLUMN_PREFIX so that colors are not included in the score spread calculation.
     */
    public const string SERVICE_COLOR_PREFIX = 'color_';

    /**
     * Key prefix of the color explanation: note_amlbot, etc.
     * Does not overlap with SERVICE_COLOR_PREFIX for the same reason.
     */
    public const string SERVICE_COLOR_NOTE_PREFIX = 'note_';

    /**
     * Mapping between the AI color and the wallet type chosen by the operator.
     */
    public const array COLOR_TO_WALLET_TYPE = [
        WalletAiAmlCheck::COLOR_WHITE => RecipientWallet::TYPE_WHITE,
        WalletAiAmlCheck::COLOR_YELLOW => RecipientWallet::TYPE_YELLOW,
        WalletAiAmlCheck::COLOR_GREY => RecipientWallet::TYPE_GRAY,
        WalletAiAmlCheck::COLOR_BLACK => RecipientWallet::TYPE_BLACK,
    ];

    private static ?Collection $servicesCache = null;

    public function __construct(
        private readonly ?Carbon $dateFrom = null,
        private readonly ?Carbon $dateTo = null,
    ) {
    }

    public static function forPeriod(?string $dateFrom, ?string $dateTo): self
    {
        return new self(
            $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null,
            $dateTo ? Carbon::parse($dateTo)->endOfDay() : null,
        );
    }

    /**
     * All AML services; they define the set of score columns.
     * @return Collection<int, AmlService>
     */
    public static function services(): Collection
    {
        return self::$servicesCache ??= AmlService::query()->orderBy('id')->get();
    }

    /**
     * Transactions of wallets with a completed AI check for the period.
     */
    public function query(): Builder
    {
        return Transaction::query()
            ->with([
                'wallet',
                'checks.service',
                'wallet.aiAmlChecks' => fn($query) => $query->where('status', WalletAiAmlCheck::STATUS_COMPLETED),
            ])
            ->whereHas(
                'wallet.aiAmlChecks',
                fn($query) => $query->where('status', WalletAiAmlCheck::STATUS_COMPLETED)
            )
            ->when($this->dateFrom, fn($query, $date) => $query->where('transactions.created_at', '>=', $date))
            ->when($this->dateTo, fn($query, $date) => $query->where('transactions.created_at', '<=', $date))
            ->orderByDesc('transactions.id');
    }

    /**
     * @param Collection<int, Transaction> $transactions
     * @return Collection<int, array>
     */
    public function rows(Collection $transactions): Collection
    {
        return $transactions->map(fn(Transaction $transaction) => $this->row($transaction))->values();
    }

    public function row(Transaction $transaction): array
    {
        /** @var ?WalletAiAmlCheck $aiCheck */
        $aiCheck = $transaction->wallet?->aiAmlChecks->first();

        $row = [
            'id' => $transaction->id,
            'created_at' => DateHelper::toDisplayTime($transaction->created_at)->format('Y-m-d H:i:s'),
            'wallet_external_id' => $transaction->wallet?->external_id_format ?? '-',
            'wallet_address' => $transaction->wallet?->address ?? '-',
            'tx_hash' => $transaction->tx_id ?? '-',
            'ai_wallet_color' => $aiCheck?->wallet_color,
            'ai_risk' => $aiCheck?->risk,
            'ai_confidence' => $aiCheck?->confidence,
            'coinkyt_wallet_color' => $aiCheck?->coinkyt_wallet_color,
            'coinkyt_risk' => $aiCheck?->coinkyt_risk,
            // The operator's final decision: the wallet type the funds were sent to
            'operator_wallet_type' => $transaction->wallet?->type !== null
                ? (int) $transaction->wallet->type
                : null,
        ];

        $amlChecks = $transaction->getLatestAmlChecks();

        foreach (self::services() as $service) {
            /** @var ?TransactionAmlCheck $check */
            $check = $amlChecks->get($service->code);

            // The amount is needed for the dollar thresholds (D_usd, S_usd); they do not fire without it
            $verdict = AmlColorResolver::forCheck($check, $transaction->amount);

            $row[self::SERVICE_COLUMN_PREFIX . $service->code] = $check?->isCompleted()
                ? $check->risk_score_normalize
                : null;
            $row[self::SERVICE_COLOR_PREFIX . $service->code] = $verdict?->color;
            $row[self::SERVICE_COLOR_NOTE_PREFIX . $service->code] = $verdict?->describe();
        }

        return $row + $this->dispersion($row);
    }

    /**
     * Score spread between AML services for one transaction.
     * Shows how far the services diverged when scoring the same transaction.
     */
    private function dispersion(array $row): array
    {
        $scores = array_values(self::scoresFromRow($row));
        $count = count($scores);

        if ($count === 0) {
            return [
                'services_count' => 0,
                'score_avg' => null,
                'score_spread' => null,
                'score_std_dev' => null,
            ];
        }

        $avg = array_sum($scores) / $count;
        $variance = $count > 1
            ? array_sum(array_map(fn(float $score) => ($score - $avg) ** 2, $scores)) / $count
            : 0.0;

        return [
            'services_count' => $count,
            'score_avg' => round($avg, 1),
            'score_spread' => round(max($scores) - min($scores), 1),
            'score_std_dev' => round(sqrt($variance), 1),
        ];
    }

    /**
     * Whether the operator's decision matched the AI verdict.
     * Null means the operator has not decided yet, so there is nothing to compare.
     */
    public static function isDecisionMatched(array $row): ?bool
    {
        $operatorType = $row['operator_wallet_type'] ?? null;

        if ($operatorType === null) {
            return null;
        }

        $aiColor = $row['ai_wallet_color'] ?? null;

        return $aiColor !== null
            && (self::COLOR_TO_WALLET_TYPE[$aiColor] ?? null) === $operatorType;
    }

    /**
     * Service scores from a report row: ['amlbot' => 10.0, 'coinkyt' => 37.3].
     * Services without a completed check are excluded.
     */
    public static function scoresFromRow(array $row): array
    {
        $scores = [];

        foreach ($row as $key => $value) {
            if ($value === null || !str_starts_with($key, self::SERVICE_COLUMN_PREFIX)) {
                continue;
            }

            $scores[substr($key, strlen(self::SERVICE_COLUMN_PREFIX))] = (float) $value;
        }

        return $scores;
    }

    /**
     * CSV columns: row key => header. Single source of truth
     * for headers and values so the columns stay aligned.
     */
    public function csvColumns(): array
    {
        $columns = [
            'id' => 'Transaction ID',
            'created_at' => 'Date/Time',
            'wallet_external_id' => 'Wallet external ID',
            'wallet_address' => 'Wallet address',
            'tx_hash' => 'Transaction hash',
            'ai_wallet_color' => 'AI: color',
            'ai_risk' => 'AI: risk',
            'ai_confidence' => 'AI: confidence, %',
            'coinkyt_wallet_color' => 'CoinKYT AI: color',
            'coinkyt_risk' => 'CoinKYT AI: risk',
            'operator_wallet_type' => 'Operator decision',
        ];

        foreach (self::services() as $service) {
            $columns[self::SERVICE_COLUMN_PREFIX . $service->code] = $service->name . ' (score)';
            $columns[self::SERVICE_COLOR_PREFIX . $service->code] = $service->name . ' (color)';
            $columns[self::SERVICE_COLOR_NOTE_PREFIX . $service->code] = $service->name . ' (why)';
        }

        return $columns + [
            'services_count' => 'Services with a result',
            'score_avg' => 'Average score',
            'score_spread' => 'Spread (max-min)',
            'score_std_dev' => 'Std. deviation',
            'decision_matched' => 'Matches AI',
        ];
    }

    /**
     * CSV headers in the same order as the rows.
     */
    public function csvHeader(): array
    {
        return array_values($this->csvColumns());
    }

    public function csvRow(array $row): array
    {
        $matched = self::isDecisionMatched($row);

        $row['operator_wallet_type'] = self::walletTypeName($row['operator_wallet_type'] ?? null);
        $row['decision_matched'] = $matched === null ? 'no decision' : ($matched ? 'yes' : 'no');

        return array_map(
            fn(string $key) => $row[$key] ?? '',
            array_keys($this->csvColumns())
        );
    }

    /**
     * Human-readable wallet type name (the operator's decision).
     */
    public static function walletTypeName(?int $type): ?string
    {
        return $type !== null
            ? (RecipientWallet::allTypes()[$type] ?? null)
            : null;
    }

    public function fileName(): string
    {
        $from = $this->dateFrom?->format('Y-m-d') ?? 'all';
        $to = $this->dateTo?->format('Y-m-d') ?? now()->format('Y-m-d');

        return "ai-aml-report_{$from}_{$to}.csv";
    }
}
