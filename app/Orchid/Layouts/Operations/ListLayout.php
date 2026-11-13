<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\DateHelper;
use App\Helpers\UserHelper;
use App\Models\Wallet\Wallet;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'wallets';

    public $template = 'orchid.operations.table';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width(20)
                ->render(fn(Wallet $wallet) => Link::make(sprintf('%s%s', $wallet->id, ($wallet->is_test ? ' [TEST]' : '')))
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.operation.info', $wallet)
                ),
            TD::make('recipient', 'Recipient')
                ->render(fn(Wallet $wallet) => $wallet->recipient ? Link::make($wallet->recipient->name)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.operation.info', $wallet) : '-'
                ),
            TD::make('address', 'Address')
                ->render(fn(Wallet $wallet) => Link::make($wallet->address)
                    ->rawClick()
                    ->target('_blank')
                    ->icon('share-alt')
                    ->href($wallet->tronscan_address_link)
                ),
            TD::make('address_to', 'Destination address')
                ->defaultHidden()
                ->render(fn(Wallet $wallet) => Link::make($wallet->address_to)
                    ->rawClick()
                    ->target('_blank')
                    ->icon('share-alt')
                    ->canSee((bool)$wallet->tronscan_address_to_link)
                    ->href($wallet->tronscan_address_to_link)
                ),

            TD::make('operation_type', 'Operation type')
                ->render(function (Wallet $wallet) {
                    return $wallet->isOperationSell()
                        ? '<i class="text-warning">●</i> ' . $wallet->operation_type_name
                        : '<i class="text-primary">●</i> ' . $wallet->operation_type_name;
                }),

            TD::make('status_name', 'Status')
                ->render(function (Wallet $wallet) {
                    return ($wallet->has_error ? '<i class="text-danger">●</i> ' : '<i class="text-success">●</i> ') .
                        $wallet->status_name;
                }),

            TD::make('external_id', 'External ID')
                ->defaultHidden(),

            TD::make('last_transfer_amount', 'Last transfer amount')
                ->defaultHidden(),

            TD::make('created_at', 'Created at')
                ->render(function (Wallet $wallet) {
                    return DateHelper::toDisplayTime($wallet->created_at)->format('Y-m-d H:i:s');
                })
                ->defaultHidden(),

            TD::make('amount', 'Balance $'),
            TD::make('amount_trx', 'TRX balance')
                ->defaultHidden(),
            TD::make('energy_balance', 'Energy balance')
                ->defaultHidden(),

            TD::make('comment', 'Comment')
                ->render(fn(Wallet $wallet) => Str::words($wallet->comment, 3, '...')),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn(Wallet $wallet) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Details'))
                            ->route('platform.operation.info', $wallet->id)
                            ->icon('bs.pencil'),

                        Button::make($wallet->isTestWallet() ? 'Unmark test wallet' : 'Mark as test wallet')
                            ->icon('bs.arrow-right')
                            ->canSee(UserHelper::isPermTesting() && !$wallet->isTestWallet())
                            ->confirm('Are you sure?')
                            ->method('setTestWallet', [
                                'id' => $wallet->id,
                            ]),
                    ])),
        ];
    }

    protected function striped(): bool
    {
        return true;
    }
}
