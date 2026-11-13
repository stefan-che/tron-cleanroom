<?php

namespace App\Orchid\Layouts\Aml;


use App\Orchid\Filters\Aml\AddressFilter;
use App\Orchid\Filters\Aml\StatusFilter;
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
            StatusFilter::class,
            AddressFilter::class,
        ];
    }
}
