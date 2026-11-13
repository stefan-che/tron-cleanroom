<?php

namespace App\Orchid\Layouts\Operations;

use App\Enums\WalletStatuses;
use App\Models\Wallet\Wallet;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\TD;

class WalletTestingStatusesLayout extends Rows
{

    /**
     * @return TD[]
     */
    public function fields(): array
    {

        /** @var Wallet $wallet */
        $wallet = $this->query->get('wallet');

        if (!$wallet->isTestWallet()) {
            return [
                Group::make([
                    Label::make('test_label')->value('This wallet is not intended for testing')
                ])
            ];
        }

        return [
            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::NEW->value], WalletStatuses::NEW->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::NEW->value,
                ]),

            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::ADD_TRANSACTION->value], WalletStatuses::ADD_TRANSACTION->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::ADD_TRANSACTION->value,
                ]),

            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::CHECK_TRANSACTION_START->value], WalletStatuses::CHECK_TRANSACTION_START->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::CHECK_TRANSACTION_START->value,
                ]),

            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::CHECK_TRANSACTION_FINISH->value], WalletStatuses::CHECK_TRANSACTION_FINISH->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::CHECK_TRANSACTION_FINISH->value,
                ]),

            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::TRANSFER_MONEY_PROCESS_START->value], WalletStatuses::TRANSFER_MONEY_PROCESS_START->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::TRANSFER_MONEY_PROCESS_START->value,
                ]),

            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value], WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value,
                ]),

            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::COMPLETED->value], WalletStatuses::COMPLETED->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::COMPLETED->value,
                ]),

            Button::make(sprintf('%s [%s]', Wallet::getStatuses()[WalletStatuses::TIME_OUT->value], WalletStatuses::TIME_OUT->value))
                ->icon('bs.arrow-right')
                ->method('setStatus', [
                    'status' => WalletStatuses::TIME_OUT->value,
                ]),

        ];
    }

}
