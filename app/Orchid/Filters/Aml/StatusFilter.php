<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Aml;

use App\Enums\AmlAddressStatuses;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class StatusFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Status';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['status'];
    }

    /**
     * Apply to a given Eloquent query builder.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run(Builder $builder): Builder
    {
        return $builder->where('status', $this->request->get('status'));
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('status')
                ->options(AmlAddressStatuses::options())
                ->empty('All')
                ->value($this->request->get('status'))
                ->title('Statuses'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name();
    }
}
