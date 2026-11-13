<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Aml;

use App\Helpers\DateHelper;
use App\Models\Aml\AmlAddress;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'addresses';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width(20)
                    ->render(fn(AmlAddress $address) => Link::make((string)$address->id)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.aml.info', $address)
                ),

            TD::make('address', 'Address')
                ->render(fn(AmlAddress $address) => Link::make($address->address)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.aml.info', $address)
                ),

            TD::make('aml_code', 'Blockchain')
                ->render(fn(AmlAddress $address) => Link::make($address->aml_code)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.aml.info', $address)
                ),

            TD::make('aml_risk_score', 'Risk'),
            TD::make('aml_uid', 'aml_uid')
                ->defaultHidden(),

            TD::make('status_name', 'Status')
                ->render(function (AmlAddress $address) {
                    return ($address->has_error ? '<i class="text-danger">●</i> ' : '<i class="text-success">●</i> ') .
                        $address->status_name;
                }),

            TD::make('created_at', 'Created at')
                ->render(function (AmlAddress $address) {
                    return DateHelper::toDisplayTime($address->created_at)->format('Y-m-d H:i:s');
                })
                ->defaultHidden(),

            TD::make('comment', 'Comment')
                ->defaultHidden()
                ->render(fn(AmlAddress $address) => Str::words($address->comment, 3, '...')),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn(AmlAddress $address) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Details'))
                            ->route('platform.aml.info', $address->id)
                            ->icon('bs.pencil'),
                    ])),
        ];
    }

    protected function striped(): bool
    {
        return true;
    }
}
