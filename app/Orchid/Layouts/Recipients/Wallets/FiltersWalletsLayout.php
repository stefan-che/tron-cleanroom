<?php

namespace App\Orchid\Layouts\Recipients\Wallets;

use App\Orchid\Filters\Recipients\BlockChainTypeFilter;
use App\Orchid\Filters\Recipients\TypeFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class FiltersWalletsLayout extends Selection
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
            TypeFilter::class,
            BlockChainTypeFilter::class,
        ];
    }
}
