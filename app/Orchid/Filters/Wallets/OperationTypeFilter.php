<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Wallets;

use App\Enums\WalletOperationsTypes;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class OperationTypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Operation type';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['operation_type'];
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
        if ($operationType = $this->request->get('operation_type')) {
            return $builder->where('operation_type', (int)$operationType);
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('operation_type')
                ->options(WalletOperationsTypes::names())
                ->empty('All')
                ->value($this->request->get('operation_type'))
                ->title('Operation type'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name() . ': ' . (WalletOperationsTypes::names()[$this->request->get('operation_type')] ?? '');
    }
}
