<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Wallets;

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

        if (!$address) {
            return $builder;
        }
        $address = StringHelper::getExternalId($address);
        // A 64-character string is a transaction hash
        if (strlen($address) === 64) {
            return $builder->whereHas('transactions', function ($q) use ($address) {
                $q->where('tx_id', $address);
            });
        }

        if (strlen($address) < 30) {
            return $builder
                ->where('external_id', 'like', '%' . $address . '%');
        }

        return $builder->where(function ($q) use ($address) {
            $q->whereHas('transactions', function ($q) use ($address) {
                $q->where('from_address', $address);
            })->orWhere('address', 'like', '%' . $address . '%')
                ->orWhere('address_to', 'like', '%' . $address . '%');
        });
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
        return $this->name() . ': ' . $this->request->get('address');
    }
}
