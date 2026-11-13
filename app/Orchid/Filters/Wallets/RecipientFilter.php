<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Wallets;

use App\Models\Recipient\Recipient;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class RecipientFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Recipients';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['recipient'];
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
        if ($recipient = $this->request->get('recipient')) {
            return $builder->where('recipient_id', $recipient);
        }

        return $builder;
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('recipient')
                ->fromModel(Recipient::class, 'name')
                ->empty('All')
                ->value($this->request->get('recipient'))
                ->title('Recipients'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return '';
    }
}
