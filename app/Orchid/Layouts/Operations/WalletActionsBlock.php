<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Models\Wallet\Wallet;
use App\Services\Aml\CoinKyt\Monitoring\MonitoringService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;
use Orchid\Support\Color;

class WalletActionsBlock extends Rows
{

    protected function fields(): iterable
    {

        /** @var Wallet $wallet */
        $wallet = $this->query->get('wallet');

        $isShow = !$wallet->isCompleted();
        $isNotTest = !$wallet->isTestWallet();

        $monitoringService = app(MonitoringService::class);
        $coinKytSupported = $monitoringService->supports($wallet->blockchain_type);
        $coinKytMonitored = $coinKytSupported && $monitoringService->isMonitored($wallet);

        return [
            Button::make('Request energy')
                ->icon('bs.lightning-charge')
                ->canSee((bool)$wallet->amount_trx && $isShow && $isNotTest)
                ->confirm('Are you sure?')
                ->method('requestEnergy'),


            Button::make('Activate wallet (add TRX)')
                ->icon('bs.wallet2')
                ->canSee($isShow && $isNotTest)
                ->confirm('Are you sure?')
                ->method('activateWallet'),

            Group::make([
                Button::make('Send funds to White wallet')
                    ->canSee(!$wallet->address_to && $isShow && $isNotTest)
                    ->icon('bs.send')
                    ->confirm('Are you sure?')
                    ->method('sendWhiteUsdt'),

                Button::make('Send funds to Gray wallet')
                    ->canSee(!$wallet->address_to && $isShow && $isNotTest)
                    ->icon('bs.send')
                    ->confirm('Are you sure?')
                    ->method('sendGrayUsdt'),

                Button::make('Send funds to Yellow wallet')
                    ->canSee(!$wallet->address_to && $isShow && $isNotTest)
                    ->icon('bs.send')
                    ->confirm('Are you sure?')
                    ->method('sendYellowUsdt'),
            ]),

            Group::make([
                Button::make('Send funds (USDT) to destination wallet')
                    ->canSee((bool)$wallet->address_to && $isShow && $isNotTest)
                    ->icon('bs.cash-stack')
                    ->confirm('Are you sure?')
                    ->method('sendUsdt'),

                Label::make('warning')
                    ->canSee((bool)$wallet->address_to && $isShow)
                    ->set('class', 'text-danger fw-bold text-decoration-underline')
                    ->value('Double-check the address before sending: ' . $wallet->address_to),

                Label::make('warning')
                    ->canSee((bool)$wallet->address_to && $isShow && $wallet->isShowRiskLabel())
                    ->set('class', 'text-warning fw-bold text-decoration-underline')
                    ->value('Wallet mismatch: the risk exceeds the threshold for the selected Recipient wallet!'),

                Label::make('warning')
                    ->canSee($wallet->isShowOldAddressToLabel() && (bool)$wallet->address_to)
                    ->set('class', 'text-danger fw-bold text-decoration-underline')
                    ->value('Warning: the address may be outdated, confirm the current one with the manager: ' . $wallet->address_to),
            ]),

            Button::make('Refresh TRX balance')
                ->icon('bs.arrow-clockwise')
                ->canSee($isNotTest)
                ->method('refreshTrxBalance'),

            Button::make('Refresh USDT balance')
                ->canSee($isNotTest)
                ->icon('bs.arrow-clockwise')
                ->method('refreshUsdtBalance'),

            Button::make('Refresh energy')
                ->canSee($isNotTest)
                ->icon('bs.arrow-clockwise')
                ->method('refreshEnergy'),

            Button::make('Run AI AML check (background)')
                ->icon('bs.robot')
                ->canSee($isNotTest)
                ->method('runAiAmlCheck'),

            Button::make('Add to CoinKYT monitoring')
                ->icon('bs.eye')
                ->canSee($isNotTest && $coinKytSupported && !$coinKytMonitored)
                ->confirm('Add the wallet address to CoinKYT monitoring?')
                ->method('addCoinKytMonitoring'),

            Button::make('Remove from CoinKYT monitoring')
                ->icon('bs.eye-slash')
                ->type(Color::WARNING())
                ->canSee($isNotTest && $coinKytMonitored)
                ->confirm('Remove the wallet address from CoinKYT monitoring?')
                ->method('removeCoinKytMonitoring'),

            ModalToggle::make('Send remaining TRX to TRX wallet')
                ->modal('sendTrxToTrxWalletModal')
                ->canSee((bool)$wallet->amount_trx && $isNotTest)
                ->modalTitle('Send TRX')
                ->method('sendTrxToTrxWallet')
                ->icon('bs.send')
                ->asyncParameters([
                    'wallet' => $wallet->id,
                ]),

            Button::make('Set status: New')
                ->icon('bs.flag')
                ->canSee(!$wallet->isNew())
                ->confirm('Are you sure?')
                ->method('setStatusNew'),

            Button::make('Set status: Completed')
                ->icon('bs.check-circle')
                ->canSee(!$wallet->isCompleted())
                ->confirm('Are you sure?')
                ->method('setStatusCompleted'),

            Button::make('Set status: Funds transfer finished')
                ->icon('bs.check2-circle')
                ->canSee(!$wallet->isTransferMoneyProcessFinish())
                ->confirm('Are you sure?')
                ->method('setStatusTransferMoneyProcessFinish'),

            Button::make('Set status: Transaction check finished')
                ->icon('bs.clipboard-check')
                ->canSee($wallet->isCheckTransactionStart())
                ->confirm('Are you sure?')
                ->method('setStatusCheckTransactionFinish'),

            Group::make([
                Button::make('Send TRX to destination wallet (rare cases)')
                    ->canSee((bool)$wallet->address_to && $isShow && $isNotTest)
                    ->icon('bs.send')
                    ->confirm('Are you sure?')
                    ->method('sendTrx'),

                Label::make('warning')
                    ->canSee((bool)$wallet->address_to && $isShow)
                    ->set('class', 'text-danger fw-bold text-decoration-underline')
                    ->value('Double-check the address before sending: ' . $wallet->address_to),

                Label::make('warning')
                    ->canSee($wallet->isShowOldAddressToLabel() && (bool)$wallet->address_to)
                    ->set('class', 'text-danger fw-bold text-decoration-underline')
                    ->value('Warning: the address may be outdated, confirm the current one with the manager: ' . $wallet->address_to),
            ]),

            Button::make(sprintf('Add %d TRX (when bandwidth is exhausted by a large number of transactions)',Wallet::ADDITIONAL_TRX))
                ->icon('bs.plus-circle')
                ->canSee($isShow && $isNotTest)
                ->confirm('Are you sure?')
                ->method('addTrxWallet'),

            Button::make('This is a test wallet')
                ->type(Color::DARK())
                ->icon('bs.bug')
                ->canSee($wallet->isNew() && $isNotTest)
                ->confirm('Are you sure?')
                ->method('setTest'),
        ];
    }
}
