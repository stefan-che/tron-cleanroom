<?php

declare(strict_types=1);

namespace App\Reports\AiAml;

use App\Models\Wallet\Transaction;
use Illuminate\Support\Collection;

/**
 * Summary statistics over AI check report rows:
 * AI agreement with the operator's decision, score spread between AML services
 * and pairwise divergence between services.
 *
 * Computed from the same rows as the report table (AiAmlChecksReport::row()),
 * so the summary and table numbers always match.
 */
class AiAmlDeviationStats
{
    /**
     * Score divergence between two services from which a case counts as significant.
     */
    public const float PAIR_DEVIATION_THRESHOLD = 25.0;

    private int $total = 0;
    private int $matched = 0;
    private int $mismatched = 0;
    private int $withoutDecision = 0;

    /** Mismatches by operator decision: [wallet type => count] */
    private array $mismatchByOperator = [];

    /** Mismatches by AI verdict: [color => count] */
    private array $mismatchByAi = [];

    /** Score spreads per row, for the average dispersion */
    private array $spreads = [];

    /** Pairwise service statistics: ['amlbot|coinkyt' => ['count','sum','max','deviations']] */
    private array $pairs = [];

    /**
     * Accumulate statistics for a report row.
     */
    public function add(array $row): void
    {
        $this->total++;

        $this->addDecision($row);
        $this->addDispersion($row);
        $this->addPairs($row);
    }

    /**
     * Collect statistics over all report transactions.
     */
    public static function build(AiAmlChecksReport $report): self
    {
        $stats = new self();

        $report->query()->chunkById(200, function (Collection $transactions) use ($report, $stats) {
            foreach ($transactions as $transaction) {
                /** @var Transaction $transaction */
                $stats->add($report->row($transaction));
            }
        }, 'transactions.id', 'id');

        return $stats;
    }

    private function addDecision(array $row): void
    {
        $matched = AiAmlChecksReport::isDecisionMatched($row);

        if ($matched === null) {
            $this->withoutDecision++;
            return;
        }

        if ($matched) {
            $this->matched++;
            return;
        }

        $this->mismatched++;

        $aiColor = $row['ai_wallet_color'] ?? null;
        $operatorName = AiAmlChecksReport::walletTypeName($row['operator_wallet_type']) ?? '-';
        $this->mismatchByOperator[$operatorName] = ($this->mismatchByOperator[$operatorName] ?? 0) + 1;

        $aiName = $aiColor ?? 'no verdict';
        $this->mismatchByAi[$aiName] = ($this->mismatchByAi[$aiName] ?? 0) + 1;
    }

    private function addDispersion(array $row): void
    {
        $spread = $row['score_spread'] ?? null;

        if ($spread !== null) {
            $this->spreads[] = (float) $spread;
        }
    }

    /**
     * Pairwise score divergence for every pair of services that both have results.
     */
    private function addPairs(array $row): void
    {
        $scores = AiAmlChecksReport::scoresFromRow($row);
        $codes = array_keys($scores);

        foreach ($codes as $i => $left) {
            foreach (array_slice($codes, $i + 1) as $right) {
                $key = $left . '|' . $right;
                $delta = abs($scores[$left] - $scores[$right]);

                $pair = $this->pairs[$key] ?? ['count' => 0, 'sum' => 0.0, 'max' => 0.0, 'deviations' => 0];

                $pair['count']++;
                $pair['sum'] += $delta;
                $pair['max'] = max($pair['max'], $delta);

                if ($delta >= self::PAIR_DEVIATION_THRESHOLD) {
                    $pair['deviations']++;
                }

                $this->pairs[$key] = $pair;
            }
        }
    }

    /**
     * Key numbers: how many decisions matched the AI.
     */
    public function summary(): array
    {
        $withDecision = $this->matched + $this->mismatched;

        return [
            'total' => $this->total,
            'matched' => $this->matched,
            'mismatched' => $this->mismatched,
            'without_decision' => $this->withoutDecision,
            'matched_percent' => $this->percent($this->matched, $withDecision),
            'mismatched_percent' => $this->percent($this->mismatched, $withDecision),
            'mismatch_by_operator' => $this->sortedDesc($this->mismatchByOperator),
            'mismatch_by_ai' => $this->sortedDesc($this->mismatchByAi),
            'avg_spread' => $this->average($this->spreads),
            'variance' => $this->variance($this->spreads),
            'std_dev' => $this->stdDev($this->spreads),
        ];
    }

    /**
     * Pairwise service divergence, most divergent pairs first.
     *
     * @return Collection<int, array>
     */
    public function pairRows(): Collection
    {
        $names = AiAmlChecksReport::services()->keyBy('code');

        return collect($this->pairs)
            ->map(function (array $pair, string $key) use ($names) {
                [$left, $right] = explode('|', $key);

                return [
                    'pair' => sprintf(
                        '%s ↔ %s',
                        $names->get($left)?->name ?? $left,
                        $names->get($right)?->name ?? $right
                    ),
                    'count' => $pair['count'],
                    'avg_delta' => round($pair['sum'] / $pair['count'], 1),
                    'max_delta' => round($pair['max'], 1),
                    'deviations' => $pair['deviations'],
                    'deviations_percent' => $this->percent($pair['deviations'], $pair['count']),
                ];
            })
            ->sortByDesc('deviations_percent')
            ->values();
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }

    private function average(array $values): float
    {
        return $values ? round(array_sum($values) / count($values), 1) : 0.0;
    }

    /**
     * Dispersion of score spreads: how unstable the divergence between services is.
     */
    private function variance(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        $sum = array_sum(array_map(fn(float $value) => ($value - $mean) ** 2, $values));

        return round($sum / count($values), 1);
    }

    private function stdDev(array $values): float
    {
        return round(sqrt($this->variance($values)), 1);
    }

    private function sortedDesc(array $counters): array
    {
        arsort($counters);

        return $counters;
    }
}
