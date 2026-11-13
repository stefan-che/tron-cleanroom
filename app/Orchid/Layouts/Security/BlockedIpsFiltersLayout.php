<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Security;

use App\Orchid\Filters\BlockedIpStatusFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class BlockedIpsFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            BlockedIpStatusFilter::class,
        ];
    }
}
