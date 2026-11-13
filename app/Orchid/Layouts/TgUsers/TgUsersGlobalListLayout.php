<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\TgUsers;

use App\Models\Recipient\TgUser;
use App\Services\Ledger\LedgerService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TgUsersGlobalListLayout extends Table
{
    public $target = 'tgUsers';

    public function columns(): array
    {
        return [
            TD::make('id', 'ID')->width(60),

            TD::make('telegram_id', 'Telegram ID'),

            TD::make('name', 'Name')
                ->render(fn(TgUser $user) => Link::make($user->name ?? '-')
                    ->route('platform.tg_users.edit', ['tgUser' => $user->id])
                ),

            TD::make('can_create_recipients', 'Creates recipients')
                ->render(fn(TgUser $user) => $user->can_create_recipients ? '<i class="text-success">●</i> Yes'
                    : '<i class="text-danger">●</i> No'),

            TD::make('can_manage_ledger_accounts', 'Ledger accounts')
                ->canSee(app(LedgerService::class)->isConfigured())
                ->render(fn(TgUser $user) => $user->can_manage_ledger_accounts ? '<i class="text-success">●</i> Yes'
                    : '<i class="text-danger">●</i> No'),

            TD::make('recipients', 'Recipients')
                ->render(fn(TgUser $user) => $user->recipients
                    ->map(fn($o) => $o->name)
                    ->implode(', ') ?: '-'
                ),

            TD::make('created_at', 'Created')
                ->render(fn(TgUser $user) => $user->created_at?->format('d.m.Y H:i')),

            TD::make('actions', '')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn(TgUser $user) => DropDown::make()
                    ->icon('options-vertical')
                    ->list([
                        Link::make('Edit')
                            ->icon('pencil')
                            ->route('platform.tg_users.edit', ['tgUser' => $user->id]),

                        Button::make('Delete')
                            ->icon('trash')
                            ->confirm("Delete user {$user->telegram_id}?")
                            ->method('delete')
                            ->parameters(['id' => $user->id]),
                    ])
                ),
        ];
    }
}
