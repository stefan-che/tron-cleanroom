<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\DateHelper;
use \App\Models\Wallet\Transaction;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class WalletTransactionList extends Table
{
    /**
     * @var string
     */
    public $target = 'transactions';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [

            TD::make('id', 'ID')
                ->render(function (Transaction $tr) {
                    return Link::make(sprintf('[%s] %s', $tr->id, substr($tr->tx_id, 0, 10) . '...'))
                        ->target('_blank')
                        ->icon('bs.arrow-right')
                        ->href($tr->tronscan_tx_link);
                }),

            TD::make('created_at', 'Created at')
                ->render(function (Transaction $tr) {
                    return DateHelper::toDisplayTime($tr->created_at)->format('Y-m-d H:i:s');
                }),

            TD::make('aml_risk_score', 'AML risk')
                ->render(function (Transaction $tr) {
                    return $tr->aml_risk_score;
                }),

            TD::make('aml_file_report_link', 'AML report')
                ->render(function (Transaction $tr) {
                    if (empty($tr->aml_file_report_link)) {
                        return '';
                    }

                    return Link::make(__('Download'))
                        ->icon('share-alt')
                        ->href(route('transaction.report', $tr->id))
                        ->target('_blank');
                }),

            TD::make('aml_uid', 'aml_uid')
                ->defaultHidden()
                ->render(function (Transaction $tr) {
                    return $tr->aml_uid;
                }),

            TD::make('status_name', 'Status')
                ->render(function (Transaction $tr) {
                    return $tr->status_name;
                }),

            TD::make('amount', 'Amount')
                ->render(function (Transaction $tr) {
                    return $tr->amount;
                }),

            TD::make('tx_id', 'tx_id')
                ->render(function (Transaction $tr) {
                    return $tr->tx_id;
                }),

            TD::make('send_at', 'Sent at')
                ->render(function (Transaction $tr) {
                    if ($tr->send_at) {
                        return DateHelper::toDisplayTime($tr->send_at)->format('Y-m-d H:i:s');
                    }

                    return '';
                })
                ->defaultHidden(),

            TD::make('from_address', 'Sent from wallet')
                ->render(function (Transaction $tr) {
                    return Link::make($tr->from_address)
                        ->target('_blank')
                        ->icon('bs.arrow-right')
                        ->href($tr->tronscan_from_address_link);
                })
                ->defaultHidden(),

            TD::make('send_tx_hash', 'Send tx hash')
                ->defaultHidden()
                ->render(function (Transaction $tr) {
                    if (!$tr->send_tx_hash) {
                        return '-';
                    }
                    return Link::make(substr($tr->send_tx_hash, 0, 10) . '...')
                        ->target('_blank')
                        ->icon('bs.arrow-right')
                        ->href($tr->tronscan_send_tx_link);
                }),


            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn(Transaction $tr) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        ModalToggle::make('AML checks')
                            ->icon('bs.shield-check')
                            ->modal('transactionAmlChecksModal')
                            ->modalTitle('AML checks')
                            ->asyncParameters([
                                'transaction' => $tr->id
                            ]),

                        Button::make('Run AML check')
                            ->icon('bs.arrow-right')
//                            ->canSee(is_null($tr->aml_risk_score)) // todo: restore in production
                            ->confirm('Are you sure?')
                            ->method('runAMLCheck', [
                                'id' => $tr->id,
                            ]),

                    ])),

        ];
    }
}
