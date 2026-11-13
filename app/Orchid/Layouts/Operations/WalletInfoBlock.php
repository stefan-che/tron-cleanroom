<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\DateHelper;
use App\Helpers\UserHelper;
use App\Models\Wallet\Wallet;
use App\Services\Tron\TronService;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Legend;
use Orchid\Screen\Sight;

class WalletInfoBlock extends Legend
{
    protected $target = 'wallet';
    protected $title = '';

    protected function columns(): iterable
    {
        /** @var Wallet $wallet */
        $wallet = $this->query->get('wallet');

        return [
            Sight::make('external_id', 'External ID')
                ->render(fn(Wallet $wallet) => sprintf('%s (%s)', $wallet->external_id, $wallet->external_id_format)),

            Sight::make('address', 'Address')
                ->render(fn(Wallet $wallet) => Link::make((string)$wallet->address)
                    ->rawClick()
                    ->icon('share-alt')
                    ->target('_blank')
                    ->href($wallet->tronscan_address_link)
                ),
            Sight::make('address_to', 'Funds will be sent to')
                ->canSee((bool)$wallet->address_to || UserHelper::isPermSetWallet() || UserHelper::isPermSetCustomWallet())
                ->render(function (Wallet $wallet) {
                    return Group::make([
                        Link::make($wallet->address_to)
                            ->canSee((bool)$wallet->address_to)
                            ->href($wallet->tronscan_address_to_link)
                            ->icon('share-alt')
                            ->target('_blank'),

                        ModalToggle::make($wallet->address_to ? 'Change' : 'Set')
                            ->modal('addressToModal')
                            ->modalTitle('Address')
                            ->method('setAddressTo')
                            ->canSee(UserHelper::isTwoFactorEnabled() && (UserHelper::isPermSetCustomWallet() || ($wallet->isCheckTransactionFinish() && UserHelper::isPermSetWallet())))
                            ->icon($wallet->address_to ? 'pencil' : 'plus')
                            ->asyncParameters([
                                'wallet' => $wallet->id,
                            ]),

                        Label::make('warning')
                            ->canSee($wallet->isShowOldAddressToLabel() && (bool)$wallet->address_to)
                            ->set('class', 'text-danger fw-bold text-decoration-underline')
                            ->value('Warning: the address may be outdated, confirm the current one with the manager: ' . $wallet->address_to),
                    ]);
                }
                ),

            Sight::make('created_at', 'Created at')
                ->render(fn(Wallet $wallet) => DateHelper::toDisplayTime($wallet->created_at)->format('Y-m-d H:i:s')),

            Sight::make('updated_at', 'Last updated')
                ->render(fn(Wallet $wallet) => DateHelper::toDisplayTime($wallet->updated_at)->format('Y-m-d H:i:s')),

            Sight::make('status', 'Status')
                ->render(fn(Wallet $wallet) => $wallet->status_name),

            Sight::make('operation_type', 'Operation type')
                ->render(fn(Wallet $wallet) => $wallet->isOperationSell()
                    ? '<i class="text-warning">●</i> ' . $wallet->operation_type_name . ' (no AML check)'
                    : '<i class="text-primary">●</i> ' . $wallet->operation_type_name),

            Sight::make('type', 'Type')
                ->canSee((bool)$wallet->type)
                ->render(fn(Wallet $wallet) => $wallet->type_name),

            Sight::make('is_test', 'Test wallet')
                ->canSee((bool)$wallet->is_test)
                ->render(fn(Wallet $wallet) => $wallet->is_test ? '<i class="text-danger">●</i> Yes'
                    : '<i class="text-success">●</i> No'),

            Sight::make('has_error', 'Error')
                ->render(fn(Wallet $wallet) => $wallet->has_error ? '<i class="text-danger">●</i> Yes'
                    : '<i class="text-success">●</i> No'),
            Sight::make('amount', 'USDT balance'),
            Sight::make('amount_energy', 'Energy balance'),
            Sight::make('amount_trx', 'TRX balance'),

            Sight::make('recipient', 'Recipient')
                ->render(function (Wallet $wallet) {
                    if (!$wallet->recipient) {
                        return '';
                    }

                    return Link::make($wallet->recipient?->name)
                        ->rawClick()
                        ->icon('share-alt')
                        ->target('_blank')
                        ->route('platform.recipient.wallets', $wallet->recipient);
                }),

            Sight::make('aml_code', 'aml code')
                ->render(fn(Wallet $wallet) => $wallet->aml_code),

            Sight::make('aml_risk_score', 'AML risk')
                ->render(fn(Wallet $wallet) => $wallet->aml_risk_score),

            Sight::make('last_transfer_amount', 'Last transfer amount')
                ->render(fn(Wallet $wallet) => $wallet->last_transfer_amount),

            Sight::make('aml_file_report_link', 'AML report')
                ->canSee((bool)$wallet->aml_file_report_link)
                ->render(fn(Wallet $wallet) => Link::make(__('Open report'))
                    ->icon('share-alt')
                    ->href(route('wallet.report', $wallet->address))
                    ->target('_blank')
                ),

            Sight::make('energy_cost_trx', 'Energy cost, TRX')
                ->render(fn(Wallet $wallet) => $wallet->energy_cost_trx),

            Sight::make('aml_cost_trx', 'AML check cost, TRX')
                ->render(fn(Wallet $wallet) => $wallet->aml_cost_trx),

            Sight::make('comment', 'Comment')
                ->render(fn(Wallet $wallet) => Group::make([
                    TextArea::make('comment')
                        ->set('class', 'form-control')
                        ->canSee((bool)$wallet->comment)
                        ->value($wallet->comment),

                    ModalToggle::make('')
                        ->modal('walletCommentModal')
                        ->modalTitle('Comment')
                        ->method('setComment')
                        ->icon('plus')
                        ->asyncParameters([
                            'wallet' => $wallet->id,
                        ])
                ])->alignEnd()->alignBaseLine()),

            Sight::make('connect_type', 'Connect'),

            Sight::make('key_check_manager', 'Key check (KeyManager)')
                ->render(function (Wallet $wallet) {
                    return $this->renderKeyCheck($wallet->decPrivateKeyFromManager(), $wallet->address);
                }),

            Sight::make('key_check_saved', 'Key check (saved_key)')
                ->canSee((bool)$wallet->saved_key)
                ->render(function (Wallet $wallet) {
                    return $this->renderKeyCheck($wallet->decPrivateKeyFromSaved(), $wallet->address);
                }),
        ];
    }

    /**
     * Green if the private key derives the expected address, red otherwise.
     */
    private function renderKeyCheck(string $privateKey, ?string $address): string
    {
        try {
            $ok = $privateKey !== ''
                && $address
                && (new TronService())->verifyKeyPair($privateKey, $address);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok
            ? '<i class="text-success">●</i> OK'
            : '<i class="text-danger">●</i> Error';
    }

}
