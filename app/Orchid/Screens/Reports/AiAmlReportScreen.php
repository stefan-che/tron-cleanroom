<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Reports;

use App\Models\Role;
use App\Models\Wallet\Transaction;
use App\Orchid\Layouts\Reports\AiAmlDeviationTable;
use App\Orchid\Layouts\Reports\HintedMetricsLayout;
use App\Orchid\Layouts\Reports\AiAmlPeriodFilterLayout;
use App\Orchid\Layouts\Reports\AiAmlReportTable;
use App\Reports\AiAml\AiAmlChecksReport;
use App\Reports\AiAml\AiAmlDeviationStats;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Repository;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAmlReportScreen extends Screen
{
    private const int PER_PAGE = 50;

    public function query(Request $request): iterable
    {
        $report = $this->report($request);
        $stats = AiAmlDeviationStats::build($report);
        $summary = $stats->summary();

        return [
            'rows' => $report->query()
                ->paginate(self::PER_PAGE)
                ->through(fn(Transaction $transaction) => new Repository($report->row($transaction))),
            'deviations' => $stats->pairRows()->map(fn(array $pair) => new Repository($pair)),
            'metrics' => $this->metrics($summary),
        ];
    }

    /**
     * Summary tiles: agreement with operators, mismatch breakdown, score spread.
     */
    private function metrics(array $summary): array
    {
        $byOperator = $summary['mismatch_by_operator'];

        return [
            'total' => ['value' => $summary['total']],
            'matched' => ['value' => sprintf('%d (%s%%)', $summary['matched'], $summary['matched_percent'])],
            'mismatched' => ['value' => sprintf('%d (%s%%)', $summary['mismatched'], $summary['mismatched_percent'])],
            'without_decision' => ['value' => $summary['without_decision']],

            'mismatch_white' => ['value' => $byOperator['White'] ?? 0],
            'mismatch_yellow' => ['value' => $byOperator['Yellow'] ?? 0],
            'mismatch_gray' => ['value' => $byOperator['Gray'] ?? 0],
            'mismatch_black' => ['value' => $byOperator['Black'] ?? 0],

            'avg_spread' => ['value' => $summary['avg_spread']],
            'variance' => ['value' => $summary['variance']],
            'std_dev' => ['value' => $summary['std_dev']],
        ];
    }

    public function name(): ?string
    {
        return 'AI AML check report';
    }

    public function description(): ?string
    {
        return '';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_AML,
        ];
    }

    /**
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Download CSV')
                ->icon('bs.download')
                ->rawClick()
                ->method('export'),
        ];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            AiAmlPeriodFilterLayout::class,

            HintedMetricsLayout::make([
                'Total decisions' => 'metrics.total',
                'Matched AI' => 'metrics.matched',
                'Did not match' => 'metrics.mismatched',
                'No decision' => 'metrics.without_decision',
            ], [
                'Total decisions' => 'Number of transactions with a completed AI check in the selected period.',
                'Matched AI' => 'The operator sent funds to a wallet of the same risk tier the AI recommended. The percentage counts only transactions with a decision.',
                'Did not match' => 'The operator chose a wallet of a different risk tier than the AI recommended. These cases are the material for a reconciliation session.',
                'No decision' => 'Transactions where the operator has not chosen a wallet yet. Excluded from match percentages.',
            ])->title('AI and operator agreement'),

            HintedMetricsLayout::make([
                'White' => 'metrics.mismatch_white',
                'Yellow' => 'metrics.mismatch_yellow',
                'Gray' => 'metrics.mismatch_gray',
                'Black' => 'metrics.mismatch_black',
            ], [
                'White' => 'How many times the operator chose a WHITE wallet against the AI recommendation.',
                'Yellow' => 'How many times the operator chose a YELLOW wallet against the AI recommendation.',
                'Gray' => 'How many times the operator chose a GRAY wallet against the AI recommendation.',
                'Black' => 'How many times the operator chose a BLACK wallet against the AI recommendation.',
            ])->title('Mismatches by operator decision'),

            HintedMetricsLayout::make([
                'Average score spread' => 'metrics.avg_spread',
                'Variance' => 'metrics.variance',
                'Standard deviation' => 'metrics.std_dev',
            ], [
                'Average score spread' => 'Report average of the difference between the highest and lowest AML provider score within one transaction. The higher the value, the more often providers rate the same transaction differently.',
                'Variance' => 'How unstable the spread itself is. Low variance: providers diverge about equally on all transactions; high variance: they sometimes agree almost exactly and sometimes diverge a lot.',
                'Standard deviation' => 'Square root of the variance, in the same 0-100 scale points. Shows how many points the spread typically deviates from the average.',
            ])->title('Score spread across AML providers'),

            AiAmlDeviationTable::class,

            AiAmlReportTable::class,
        ];
    }

    /**
     * Exports the report for the selected period as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $report = $this->report($request);

        return response()->streamDownload(function () use ($report) {
            $csv = fopen('php://output', 'wb');

            // UTF-8 BOM so Excel opens the file correctly
            fwrite($csv, "\xEF\xBB\xBF");
            fputcsv($csv, $report->csvHeader());

            $report->query()->chunkById(200, function ($transactions) use ($csv, $report) {
                foreach ($transactions as $transaction) {
                    fputcsv($csv, $report->csvRow($report->row($transaction)));
                }
            }, 'transactions.id', 'id');

            fclose($csv);
        }, $report->fileName());
    }

    private function report(Request $request): AiAmlChecksReport
    {
        return AiAmlChecksReport::forPeriod(
            $request->get('date_from'),
            $request->get('date_to')
        );
    }
}
