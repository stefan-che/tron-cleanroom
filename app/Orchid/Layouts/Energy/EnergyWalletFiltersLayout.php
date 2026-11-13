<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Energy;

use App\Orchid\Filters\Energy\EnergyWalletFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class EnergyWalletFiltersLayout extends Selection
{
    public $template = self::TEMPLATE_LINE;

    /** @return Filter[] */
    public function filters(): array
    {
        return [
            EnergyWalletFilter::class,
        ];
    }
}
