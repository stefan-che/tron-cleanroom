<?php

namespace App\Orchid\Layouts\Recipients;

use App\Orchid\Filters\Recipients\ExternalIdFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class FiltersLayout extends Selection
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
            ExternalIdFilter::class,
        ];
    }
}
