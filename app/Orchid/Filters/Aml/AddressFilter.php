<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Aml;

use App\Helpers\StringHelper;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class AddressFilter extends Filter
{

    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Address';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['address'];
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
        $address = StringHelper::trim($this->request->get('address'));

        return $builder->where('address', 'like', '%' . $address . '%');
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('address')
                ->value($this->request->get('address'))
                ->title('Address'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name() . ': ' . $this->request->get('code');
    }
}
