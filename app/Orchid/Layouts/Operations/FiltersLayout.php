<?php

namespace App\Orchid\Layouts\Operations;


use App\Orchid\Filters\Wallets\AddressFilter;
use App\Orchid\Filters\Wallets\RecipientFilter;
use App\Orchid\Filters\Wallets\OperationTypeFilter;
use App\Orchid\Filters\Wallets\StatusFilter;
use App\Orchid\Filters\Wallets\TypeFilter;
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
            TypeFilter::class,
            OperationTypeFilter::class,
            AddressFilter::class,
            RecipientFilter::class,
        ];
    }
}
