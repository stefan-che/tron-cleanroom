<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Energy;

use App\Models\Energy\EnergyWallet;
use App\Models\Role;
use App\Orchid\Layouts\Energy\EnergyWalletHistoryLayout;
use App\Repositories\Energy\EnergyWalletRepository;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class EnergyWalletInfoScreen extends Screen
{
    /**
     * @var EnergyWallet $wallet
     */
    public  $wallet;
    public  $repository;


    public function query(EnergyWallet $wallet): iterable
    {
        $wallet->load('recipient');

        $this->repository = app(EnergyWalletRepository::class);
        return [
            'wallet'  => $wallet,
            'history' => $wallet->history()->latest('created_at')->paginate(50),
        ];
    }

    public function name(): ?string
    {
        return 'Wallet: ' . ($this->wallet->exists ? $this->wallet->address : '');
    }

    public function description(): ?string
    {
        $label  = $this->wallet->label ?? '-';
        $recipient = $this->wallet->recipient?->name ?? '-';
        return "Label: {$label} · Recipient: {$recipient}";
    }

    public function permission(): ?iterable
    {
        return [Role::PERMISSION_ENERGY_WALLET_POOL];
    }

    public function commandBar(): iterable
    {
        $wallet = $this->wallet;

        return [
            Button::make($wallet->auto_replenishment_enabled ? 'Disable auto-replenishment' : 'Enable auto-replenishment')
                ->icon($wallet->auto_replenishment_enabled ? 'bs.toggle-on' : 'bs.toggle-off')
                ->method('toggle'),

            Button::make('Remove from pool')
                ->icon('bs.trash')
                ->confirm('Are you sure? The wallet will be removed from the pool.')
                ->method('remove'),

            Link::make('← Back to list')
                ->icon('bs.arrow-left')
                ->route('platform.energy.auto-replenishment'),
        ];
    }

    public function layout(): iterable
    {
        $wallet = $this->wallet;
        $status = $wallet->auto_replenishment_enabled ? '✅ Enabled' : '❌ Disabled';

        return [
            Layout::tabs([
                'Details' => Layout::rows([
                    Group::make([
                        Input::make('address')
                            ->title('Address')
                            ->value($wallet->address),

                        Input::make('label')
                            ->title('Label')
                            ->value($wallet->label ?? '-'),
                    ]),
                    Group::make([
                        Input::make('recipient_name')
                            ->title('Recipient')
                            ->value((string) ($wallet?->recipient?->name ?? '-')),

                        Input::make('status')
                            ->title('Auto-replenishment')
                            ->value($status),
                    ]),
                    Group::make([
                        Input::make('created_at')
                            ->title('Added')
                            ->value($wallet->created_at?->format('d.m.Y H:i')),

                        Input::make('updated_at')
                            ->title('Updated')
                            ->value($wallet->updated_at?->format('d.m.Y H:i') ?? '-'),

                        Input::make('expires_at')
                            ->title('Auto-disable at')
                            ->value($wallet->expires_at ? $wallet->expires_at->format('d.m.Y H:i') : 'Not set'),
                    ]),
                ]),

                'History' => EnergyWalletHistoryLayout::class,
            ]),
        ];
    }

    public function toggle(EnergyWallet $wallet): void
    {
        $actor = auth()->user()?->name ?? 'admin';
        $this->repository->toggle($wallet, $actor);

        $status = $wallet->auto_replenishment_enabled ? 'disabled' : 'enabled';
        Toast::info("Auto-replenishment {$status}.");
    }

    public function remove(EnergyWallet $wallet): \Illuminate\Http\RedirectResponse
    {
        $actor = auth()->user()?->name ?? 'admin';
        $this->repository->remove($wallet, $actor);

        Toast::info('Wallet removed from the pool.');

        return redirect()->route('platform.energy.auto-replenishment');
    }
}
