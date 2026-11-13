<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Reports;

use App\Reports\AiAml\AiAmlDeviationStats;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\Repository;
use Orchid\Screen\TD;

/**
 * Pairwise AML service deviations: which checks disagree most often.
 */
class AiAmlDeviationTable extends Table
{
    /**
     * @var string
     */
    public $target = 'deviations';
    public $title = 'Deviations between AML checks';


    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('pair', 'Service pair')
                ->width('260')
                ->popover('Two AML services whose scores are compared with each other. '
                    . 'Pairs are built automatically from all services in the report.'),

            TD::make('count', 'Comparisons')
                ->width('110')
                ->align(TD::ALIGN_CENTER)
                ->popover('Number of transactions where both services returned a completed result. '
                    . 'Only such transactions are compared: if one of the services '
                    . 'did not run the check, the case is skipped.'),

            TD::make('avg_delta', 'Average deviation')
                ->width('150')
                ->align(TD::ALIGN_CENTER)
                ->popover('Average score difference for this pair across all comparisons, '
                    . 'in points on the 0-100 scale. Shows systematic disagreement between services.'),

            TD::make('max_delta', 'Maximum')
                ->width('130')
                ->align(TD::ALIGN_CENTER)
                ->popover('The largest score difference seen for this pair '
                    . 'in the period. Helps find transactions where the services diverged sharply.'),

            TD::make('deviations', 'Significant deviations')
                ->width('180')
                ->align(TD::ALIGN_CENTER)
                ->popover('How many times the score difference exceeded the threshold of '
                    . AiAmlDeviationStats::PAIR_DEVIATION_THRESHOLD . ' points, and what share '
                    . 'of all comparisons for the pair that was. The table is sorted by this share: '
                    . 'pairs that disagree most often are at the top.')
                ->render(fn(Repository $row) => sprintf(
                    '%d <span class="text-muted small">(%s%%, threshold %s points)</span>',
                    (int) $row->get('deviations'),
                    $row->get('deviations_percent'),
                    AiAmlDeviationStats::PAIR_DEVIATION_THRESHOLD
                )),
        ];
    }
}
