<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\UserHelper;
use App\Models\Wallet\Wallet;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;
class AddressToFormLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        /**
         * @var Wallet $w
         */
        $w = $this->query->get('wallet');

        $wallets = $w->recipient?->wallets;
        $options = [];

        if ($wallets && !$wallets->isEmpty()) {
            foreach ($wallets as $wallet) {
                $options[$wallet->address] = sprintf('%s - %s', $wallet->type_name, $wallet->address);
            }
        }

        return [
            Select::make('wallet.address_to_recipients')
                ->options($options)
                ->empty('Select an address from the Recipient wallets')
                ->title('Recipient addresses'),

            Input::make('wallet.address_to_custom')
                ->placeholder('Or paste the address to transfer to')
                ->canSee(UserHelper::isPermSetCustomWallet())
                ->title('Other address'),

            Input::make('wallet.code_2fa')
                ->placeholder('Enter 2FA code')
                ->canSee(UserHelper::isTwoFactorEnabled())
                ->title('2FA code'),

            Label::make('warning')
                ->set('class', 'text-danger')
                ->value('Double-check the wallet address before sending!'),

        ];
    }
}
