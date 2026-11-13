<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Reports;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\DateTimer;

/**
 * Report period by transaction date. Filtering happens in AiAmlChecksReport;
 * this class only defines the fields and the query string.
 */
class AiAmlPeriodFilter extends Filter
{
    public function name(): string
    {
        return 'Period';
    }

    /**
     * @return string[]
     */
    public function parameters(): array
    {
        return ['date_from', 'date_to'];
    }

    public function display(): array
    {
        return [
            DateTimer::make('date_from')
                ->title('Date from')
                ->format('Y-m-d')
                ->value($this->request->get('date_from')),

            DateTimer::make('date_to')
                ->title('Date to')
                ->format('Y-m-d')
                ->value($this->request->get('date_to')),
        ];
    }

    public function value(): string
    {
        $from = $this->request->get('date_from') ?: '…';
        $to = $this->request->get('date_to') ?: '…';

        return $this->name() . ": {$from} — {$to}";
    }

    public function run(Builder $builder): Builder
    {
        return $builder;
    }
}
