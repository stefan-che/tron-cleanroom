<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Wallets;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class CodeFilter extends Filter
{

    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Code';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['code'];
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
        $code = trim($this->request->get('code'));
        return $builder->where('code', 'like','%' . $code . '%');
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('code')
                ->value($this->request->get('code'))
                ->title('Code'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name().': ' . $this->request->get('code');
    }
}
