<?php

declare(strict_types=1);

namespace App\Orchid\Screens\WalletsTrx;

use App\Jobs\Wallet\Trx\UpdateTrxWalletsBalance;
use App\Models\Role;
use App\Models\WalletTrx;
use App\Orchid\Layouts\WalletsTrx\ListLayout;
use App\Orchid\Layouts\WalletsTrx\ModalFormLayout;
use App\Repositories\WalletTrxRepository;
use App\Services\Tron\TronService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class WalletsTrxListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'wallets' =>  WalletTrx::filters()
                ->paginate(10),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'TRX wallets';
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
            Role::PERMISSION_TRX_WALLETS,
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

            Button::make('Refresh balances')
                ->icon('orc.reload')
                ->method('updateBalances'),

            ModalToggle::make('Create new wallet')
                ->icon('plus')
                ->type(Color::WARNING())
                ->modal('CreateModal')
                ->modalTitle('Create new wallet')
                ->method('close'),
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
            Layout::modal('CreateModal', [
                ModalFormLayout::class,
            ])
                ->async('asyncGetWallet')
                ->applyButton('Close')
                ->closeButton(''),

            ListLayout::class,
        ];
    }

    /**
     * @return array
     */
    public function asyncGetWallet(): iterable
    {
        $account = (new TronService)->createAccount();

        $repository = new WalletTrxRepository();
        $wallet = $repository->store([
            'address' => $account->getAddress(true),
            'private_key' => $account->getPrivateKey(),
        ]);

        return [
            'address' => $wallet->address,
        ];
    }

    public function updateBalances(Request $request)
    {
        UpdateTrxWalletsBalance::dispatchSync(); //dispatch dispatchSync

        return redirect()->route('platform.wallet_trx.list');
    }

    public function close(Request $request)
    {
        return redirect()->route('platform.wallet_trx.list');
    }

    public function remove(Request $request): void
    {
        WalletTrx::findOrFail($request->get('id'))->delete();

        Toast::info('Wallet deleted');
    }

}
