<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Recipients\TgUsers;

use App\Models\Recipient\TgUser;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TgUsersListLayout extends Table
{
    public $target = 'tgUsers';

    public function columns(): array
    {
        return [
            TD::make('id', 'ID')->width(60),

            TD::make('telegram_id', 'Telegram ID'),

            TD::make('name', 'Name')
                ->render(fn(TgUser $user) => $user->name ?? '-'),

            TD::make('created_at', 'Added')
                ->render(fn(TgUser $user) => $user->created_at?->format('d.m.Y H:i')),

            TD::make('actions', '')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn(TgUser $user) => Button::make('Detach')
                    ->icon('trash')
                    ->confirm('Detach user ' . $user->telegram_id . ' from this recipient?')
                    ->method('detachTgUser')
                    ->parameters(['tg_user_id' => $user->id])
                ),
        ];
    }
}
