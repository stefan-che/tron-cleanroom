<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Operations;

use App\Models\Role;
use App\Models\Wallet\Wallet;
use App\Orchid\Layouts\Operations\FiltersLayout;
use App\Orchid\Layouts\Operations\ListLayout;
use App\Repositories\WalletRepository;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class WalletsListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'wallets' => Wallet::filters(FiltersLayout::class)
                ->with(['recipient'])
                ->defaultSort('id', 'desc')
                ->paginate(20),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Operations';
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
            Role::PERMISSION_OPERATIONS,
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
            FiltersLayout::class,
            ListLayout::class,
        ];
    }

    public function setTestWallet(Request $request)
    {
        $wallet = app(WalletRepository::class)->getById((int)$request->get('id'));

        if ($wallet) {
            $wallet->update([
                'is_test' => !$wallet->is_test,
            ]);
        }

        Toast::info($wallet->is_test ? 'Wallet marked as test' : 'Wallet is no longer a test wallet')->delay(1000);
    }


}
