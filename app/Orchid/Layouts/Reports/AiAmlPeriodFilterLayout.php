<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Reports;

use App\Orchid\Filters\Reports\AiAmlPeriodFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class AiAmlPeriodFilterLayout extends Selection
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
            AiAmlPeriodFilter::class,
        ];
    }
}
