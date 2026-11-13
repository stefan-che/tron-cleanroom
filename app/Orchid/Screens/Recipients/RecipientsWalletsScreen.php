<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Recipients;

use App\Models\Recipient\Recipient;
use App\Models\Recipient\Wallet;
use App\Models\Role;
use App\Orchid\Layouts\Recipients\TabMenuLayout;
use App\Orchid\Layouts\Recipients\Wallets\FiltersWalletsLayout;
use App\Orchid\Layouts\Recipients\Wallets\WalletsListLayout;
use App\Repositories\Recipient\RecipientWalletRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class RecipientsWalletsScreen extends Screen
{

    /**
     * @var Recipient
     */
    public $recipient;

    /**
     * Fetch data to be displayed on the screen.
     *
      * @return array
     */
    public function query(Recipient $recipient): iterable
    {
        return [
            'recipient' => $recipient,
            'wallets' => Wallet::where('recipient_id', $recipient->id)
                ->filters(FiltersWalletsLayout::class)
                ->paginate(20),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return sprintf('Recipient wallets: %s (%s)', $this->recipient->name, $this->recipient->external_id);
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return '';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_RECIPIENTS,
        ];
    }

    /**
     * The screen's action buttons.
     *
      * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('Add wallet')
                ->icon('plus')
                ->modal('addWalletModal')
                ->method('addWallet'),
        ];
    }

    /**
     * The screen's layout elements.
     *
      * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            TabMenuLayout::class,
            FiltersWalletsLayout::class,
            WalletsListLayout::class,

            Layout::modal('addWalletModal', [
                Layout::rows([
                    Select::make('wallet.blockchain_type')
                        ->title('Blockchain')
                        ->options(Wallet::blockChainTypesNames())
                        ->required(),

                    Select::make('wallet.type')
                        ->title('Wallet type')
                        ->options(Wallet::allTypes())
                        ->required(),

                    Input::make('wallet.address')
                        ->title('Destination address')
                        ->required(),
                ]),
            ])->title('New destination wallet'),
        ];
    }

    public function addWallet(Recipient $recipient, Request $request, RecipientWalletRepository $repository): void
    {
        $data = $request->validate([
            'wallet.blockchain_type' => ['required', 'integer', Rule::in(array_keys(Wallet::blockChainTypesNames()))],
            'wallet.type'            => ['required', 'integer', Rule::in(array_keys(Wallet::allTypes()))],
            'wallet.address'         => ['required', 'string', 'max:128', 'unique:recipient_wallets,address'],
        ])['wallet'];

        $regexp = Wallet::blockChainTypesRegexp()[(int)$data['blockchain_type']] ?? '';
        if ($regexp && !preg_match($regexp, $data['address'])) {
            Toast::error('The address does not match the selected blockchain format.');
            return;
        }

        $exists = $recipient->wallets()
            ->where('type', (int)$data['type'])
            ->where('blockchain_type', (int)$data['blockchain_type'])
            ->exists();

        if ($exists) {
            Toast::warning('The recipient already has a wallet of this type on this blockchain.');
            return;
        }

        $repository->store([
            'recipient_id'     => $recipient->id,
            'blockchain_type' => (int)$data['blockchain_type'],
            'type'            => (int)$data['type'],
            'address'         => $data['address'],
        ]);

        Toast::success('Wallet added.');
    }
}
