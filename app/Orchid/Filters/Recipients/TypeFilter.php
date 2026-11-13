<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Recipients;

use App\Models\Recipient\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class TypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Wallet types';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['type'];
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
        return $builder->where('type', $this->request->get('type'));
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('type')
                ->options(Wallet::getTypesWithoutBlack())
                ->empty('All')
                ->value($this->request->get('type'))
                ->title('Wallet type'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name() . ': ' . Wallet::getTypesWithoutBlack()[$this->request->get('type')] ?? '';
    }
}
