<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Wallets\Statistics;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class PeriodFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Period';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['period'];
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('period')
                ->options([
                    'today' => 'Today',
                    'week' => 'Week',
                    'month' => 'Month',
                    '3months' => '3 months',
                    'year' => '1 year',
                ])
                ->empty('All time')
                ->value($this->request->get('period', 'month'))
                ->title('Period')
                ->help('Select the period to show statistics for'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $period = $this->request->get('period', 'month');
        $periods = [
            'today' => 'Today',
            'week' => 'Week',
            'month' => 'Month',
            '3months' => '3 months',
            'year' => '1 year',
        ];

        return $this->name() . ': ' . ($periods[$period] ?? 'Month');
    }

    public function run(Builder $builder): Builder
    {
        return $builder;
    }
}
