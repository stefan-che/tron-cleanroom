<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations\Statistics;

use App\Orchid\Filters\Wallets\Statistics\PeriodFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class PeriodFilterLayout extends Selection
{
    /**
     * @var string
     */
    public $template = self::TEMPLATE_LINE;

    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            PeriodFilter::class,
        ];
    }
}
