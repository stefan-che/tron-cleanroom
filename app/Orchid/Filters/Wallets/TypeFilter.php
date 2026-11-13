<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Wallets;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;
use \App\Models\Recipient\Wallet;


class TypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Wallet type';
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
        if ($type = $this->request->get('type')) {
            return $builder->where('type', $type);
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('type')
                ->options(Wallet::allTypes())
                ->empty('All')
                ->value($this->request->get('type'))
                ->title('Wallet types'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name() . ': ' . Wallet::allTypes()[$this->request->get('type')] ?? '';
    }
}
