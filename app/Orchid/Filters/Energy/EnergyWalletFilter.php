<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Energy;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class EnergyWalletFilter extends Filter
{
    public function name(): string
    {
        return 'Search';
    }

    public function parameters(): array
    {
        return ['energy_search'];
    }

    public function run(Builder $builder): Builder
    {
        $q = trim($this->request->get('energy_search', ''));

        return $builder->where(function (Builder $query) use ($q) {
            $query->where('address', 'like', '%' . $q . '%')
                  ->orWhere('label', 'like', '%' . $q . '%');
        });
    }

    public function display(): array
    {
        return [
            Input::make('energy_search')
                ->value($this->request->get('energy_search'))
                ->placeholder('Address or label...')
                ->title('Search'),
        ];
    }

    public function value(): string
    {
        return $this->name() . ': ' . $this->request->get('energy_search');
    }
}
