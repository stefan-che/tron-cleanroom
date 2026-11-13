<?php

declare(strict_types=1);

namespace App\Orchid\Screens\TgUsers;

use App\Models\Recipient\TgUser;
use App\Models\Role;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TgUserEditScreen extends Screen
{
    public  $tgUser;

    public function query(TgUser $tgUser): iterable
    {
        return [
            'tgUser' => $tgUser,
        ];
    }

    public function name(): ?string
    {
        return $this->tgUser->exists
            ? "Edit: {$this->tgUser->select_label}"
            : 'New Telegram user';
    }

    public function permission(): ?iterable
    {
        return [Role::PERMISSION_RECIPIENTS];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Save')
                ->icon('check')
                ->method('save'),

            Link::make('Cancel')
                ->icon('arrow-left')
                ->route('platform.tg_users.list'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('tgUser.telegram_id')
                    ->title('Telegram ID')
                    ->placeholder('Numeric user ID')
                    ->required()
                    ->readonly($this->tgUser->exists),

                Input::make('tgUser.name')
                    ->title('Name')
                    ->placeholder('Display name in the admin panel'),

                CheckBox::make('tgUser.can_create_recipients')
                    ->title('Manage recipients in the bot')
                    ->placeholder('Can create recipients and add users')
                    ->sendTrueOrFalse(),

                CheckBox::make('tgUser.can_manage_ledger_accounts')
                    ->title('Ledger accounts in the bot')
                    ->placeholder('Can link wallets to ledger accounts')
                    ->sendTrueOrFalse()
                    ->canSee(app(LedgerService::class)->isConfigured()),
            ]),
        ];
    }

    public function save(Request $request, TgUser $tgUser): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'tgUser.telegram_id' => 'required|string|max:32|unique:tg_users,telegram_id' . ($tgUser->exists ? ",{$tgUser->id}" : ''),
            'tgUser.name'                    => 'nullable|string|max:255',
            'tgUser.can_create_recipients'     => 'nullable|boolean',
            'tgUser.can_manage_ledger_accounts' => 'nullable|boolean',
        ]);

        if ($tgUser->exists) {
            $tgUser->update($request->input('tgUser'));
        } else {
            TgUser::create($request->input('tgUser'));
        }

        Toast::success('Saved.');

        return redirect()->route('platform.tg_users.list');
    }
}
