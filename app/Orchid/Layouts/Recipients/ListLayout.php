<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Recipients;

use App\Models\Recipient\Recipient;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'recipients';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width(20)
                ->render(fn(Recipient $recipient) => Link::make((string)$recipient->id)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.recipient.wallets', $recipient)
                ),
            TD::make('external_id', 'API ID')
                ->render(fn(Recipient $recipient) => Link::make($recipient->external_id)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.recipient.wallets', $recipient)
                )->defaultHidden(),

            TD::make('name', 'Name')
                ->render(fn(Recipient $recipient) => Link::make($recipient->name)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.recipient.wallets', $recipient)
                ),

            TD::make('wallets_count', 'Wallets')
                ->render(fn(Recipient $recipient) => $recipient->wallets_count),

            TD::make('tg_users_count', 'TG accounts')
                ->render(fn(Recipient $recipient) => $recipient->tg_users_count),

            TD::make('comment', 'Comment')
                ->defaultHidden(),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn(Recipient $recipient) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Edit'))
                            ->route('platform.recipient.wallets', $recipient->id)
                            ->icon('bs.pencil'),
                    ])),
        ];
    }
}
