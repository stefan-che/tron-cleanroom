<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Recipients;

use App\Models\Recipient\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class BlockChainTypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Blockchain types';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['blockchain_type'];
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
        return $builder->where('blockchain_type', $this->request->get('blockchain_type'));
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('blockchain_type')
                ->options(Wallet::blockChainTypesNames())
                ->empty('All')
                ->value($this->request->get('blockchain_type'))
                ->title('Blockchain type'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name() . ': ' . Direction::getTypes()[$this->request->get('type')] ?? '';
    }
}
