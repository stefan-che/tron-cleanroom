<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class BlockedIpStatusFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Block status';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['status'];
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
        $status = $this->request->get('status');

        return match ($status) {
            'active' => $builder->active(),
            'unblocked' => $builder->unblocked(),
            'permanent' => $builder->where('is_permanently_blocked', true),
            'temporary' => $builder->where('is_permanently_blocked', false)->whereNull('unblocked_at'),
            default => $builder,
        };
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('status')
                ->options([
                    'active' => 'Active blocks',
                    'unblocked' => 'Unblocked',
                    'permanent' => 'Permanent blocks',
                    'temporary' => 'Temporary blocks',
                ])
                ->empty('All')
                ->value($this->request->get('status'))
                ->title('Block status'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $status = $this->request->get('status');

        return match ($status) {
            'active' => 'Active blocks',
            'unblocked' => 'Unblocked',
            'permanent' => 'Permanent blocks',
            'temporary' => 'Temporary blocks',
            default => $this->name(),
        };
    }
}
