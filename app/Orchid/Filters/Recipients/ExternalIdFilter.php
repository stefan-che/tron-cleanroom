<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Recipients;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class ExternalIdFilter extends Filter
{

    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'External ID';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['external_id'];
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
        $code = trim($this->request->get('external_id'));
        return $builder->where('external_id', 'like', '%' . $code . '%');
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('external_id')
                ->value($this->request->get('external_id'))
                ->title('External ID'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name() . ': ' . $this->request->get('external_id');
    }
}
