<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Energy;

use App\Models\Energy\EnergyWalletHistory;
use App\Models\Role;
use App\Orchid\Layouts\Energy\EnergyWalletFiltersLayout;
use App\Orchid\Layouts\Energy\ReplenishmentHistoryLayout;
use App\Orchid\Layouts\Energy\ReplenishmentWalletsListLayout;
use App\Orchid\Layouts\Energy\WalletPoolAddFormLayout;
use App\Repositories\Energy\EnergyWalletRepository;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AutoReplenishmentScreen extends Screen
{
    public function __construct(private readonly EnergyWalletRepository $repository) {}

    public function query(): iterable
    {
        return [
            'wallets' => $this->repository->query()
                ->with('recipient')
                ->filters(EnergyWalletFiltersLayout::class)
                ->latest()
                ->paginate(30),
            'history' => EnergyWalletHistory::with(['walletPool' => fn($q) => $q->withTrashed()])
                ->latest('created_at')
                ->paginate(50),
        ];
    }

    public function name(): ?string
    {
        return 'Energy auto-replenishment';
    }

    public function description(): ?string
    {
        return 'Pool of TRC-20 wallets for automatic TRON energy replenishment';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_ENERGY_WALLET_POOL,
        ];
    }

    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('Add wallet')
                ->modal('addWalletModal')
                ->icon('bs.plus-circle')
                ->method('addWallet'),
        ];
    }

    public function layout(): iterable
    {
        return [
            EnergyWalletFiltersLayout::class,

            Layout::tabs([
                'Wallets' => ReplenishmentWalletsListLayout::class,
                'History'  => ReplenishmentHistoryLayout::class,
            ]),

            Layout::modal('addWalletModal', [
                WalletPoolAddFormLayout::class,
            ])
                ->title('Add wallet to pool')
                ->applyButton('Add')
                ->closeButton('Cancel'),
        ];
    }

    public function addWallet(Request $request): void
    {
        $data = $request->validate([
            'wallet.address'                    => ['required', 'string', 'unique:energy_wallets,address'],
            'wallet.label'                      => ['nullable', 'string', 'max:255'],
            'wallet.auto_replenishment_enabled' => ['nullable', 'boolean'],
        ]);

        $actor = auth()->user()?->name ?? 'admin';

        $this->repository->create([
            'address'                    => $data['wallet']['address'],
            'label'                      => $data['wallet']['label'] ?? null,
            'auto_replenishment_enabled' => (bool) ($data['wallet']['auto_replenishment_enabled'] ?? true),
        ], $actor);

        Toast::info('Wallet added to the pool.');
    }

    public function toggle(Request $request): void
    {
        $wallet = $this->repository->find((int) $request->input('id'));

        if (!$wallet) {
            Toast::error('Wallet not found.');
            return;
        }

        $actor = auth()->user()?->name ?? 'admin';
        $this->repository->toggle($wallet, $actor);

        $status = $wallet->auto_replenishment_enabled ? 'disabled' : 'enabled';
        Toast::info("Auto-replenishment for {$wallet->address} {$status}.");
    }

    public function remove(Request $request): void
    {
        $wallet = $this->repository->find((int) $request->input('id'));

        if (!$wallet) {
            Toast::error('Wallet not found.');
            return;
        }

        $actor = auth()->user()?->name ?? 'admin';
        $this->repository->remove($wallet, $actor);

        Toast::info('Wallet removed from the pool.');
    }
}
