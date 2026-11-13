<?php

declare(strict_types=1);

namespace App\Orchid\Screens\TgUsers;

use App\Models\Recipient\TgUser;
use App\Models\Role;
use App\Orchid\Layouts\TgUsers\TgUsersGlobalListLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class TgUsersListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'tgUsers' => TgUser::withCount('recipients')
                ->with('recipients')
                ->paginate(50),
        ];
    }

    public function name(): ?string
    {
        return 'Users (Telegram)';
    }

    public function description(): ?string
    {
        return 'Telegram users with bot access';
    }

    public function permission(): ?iterable
    {
        return [Role::PERMISSION_RECIPIENTS];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Add user')
                ->icon('plus')
                ->route('platform.tg_users.create'),
        ];
    }

    public function layout(): iterable
    {
        return [
            TgUsersGlobalListLayout::class,
        ];
    }

    public function delete(Request $request): void
    {
        TgUser::findOrFail($request->input('id'))->delete();
        Toast::success('User deleted.');
    }
}
