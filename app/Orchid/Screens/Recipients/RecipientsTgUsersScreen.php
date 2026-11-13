<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Recipients;

use App\Models\Recipient\Recipient;
use App\Models\Recipient\TgUser;
use App\Models\Role;
use App\Orchid\Layouts\Recipients\TabMenuLayout;
use App\Orchid\Layouts\Recipients\TgUsers\TgUsersListLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class RecipientsTgUsersScreen extends Screen
{
    public $recipient;

    public function query(Recipient $recipient): iterable
    {
        return [
            'recipient'  => $recipient,
            'tgUsers' => $recipient->tgUsers()->paginate(50),
            // For the Select: only users not yet linked to this recipient
            'availableUsers' => TgUser::whereDoesntHave('recipients', fn($q) => $q->where('recipients.id', $recipient->id))
                ->get()
                ->mapWithKeys(fn(TgUser $u) => [$u->id => $u->select_label])
                ->toArray(),
        ];
    }

    public function name(): ?string
    {
        return sprintf('Telegram users: %s (%s)', $this->recipient->name, $this->recipient->external_id);
    }

    public function description(): ?string
    {
        return 'Telegram users with bot access to this recipient\'s wallets';
    }

    public function permission(): ?iterable
    {
        return [Role::PERMISSION_RECIPIENTS];
    }

    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('Link user')
                ->icon('plus')
                ->modal('attachTgUserModal')
                ->method('attachTgUser'),
        ];
    }

    public function layout(): iterable
    {
        return [
            TabMenuLayout::class,
            TgUsersListLayout::class,

            Layout::modal('attachTgUserModal', [
                Layout::rows([
                    Select::make('tg_user_id')
                        ->title('User')
                        ->fromQuery(
                            TgUser::whereDoesntHave('recipients', fn($q) => $q->where('recipients.id', $this->recipient->id ?? 0)),
                            'name',
                            'id'
                        )
                        ->empty('— select a user —')
                        ->required(),
                ]),
            ])->title('Link a Telegram user to the recipient'),
        ];
    }

    public function attachTgUser(Recipient $recipient, Request $request): void
    {
        $request->validate([
            'tg_user_id' => 'required|integer|exists:tg_users,id',
        ]);

        $tgUserId = (int)$request->input('tg_user_id');

        if ($recipient->tgUsers()->where('tg_user_id', $tgUserId)->exists()) {
            Toast::warning('This user is already linked to the recipient.');
            return;
        }

        $recipient->tgUsers()->attach($tgUserId);

        Toast::success('User linked to the recipient.');
    }

    public function detachTgUser(Recipient $recipient, Request $request): void
    {
        $recipient->tgUsers()->detach((int)$request->input('tg_user_id'));
        Toast::success('User unlinked from the recipient.');
    }
}
