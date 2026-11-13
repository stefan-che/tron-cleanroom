<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Aml;

use App\Helpers\DateHelper;
use App\Helpers\LogFormatter;
use App\Models\Aml\AmlAddressCheck;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AmlChecksList extends Table
{
    /**
     * @var string
     */
    public $target = 'checks';

    protected $defaultSort = [
        'id' => 'desc'
    ];

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width('60px')
                ->sort()
                ->render(fn(AmlAddressCheck $check) => $check->id),

            TD::make('created_at', 'Service')
                ->render(function (AmlAddressCheck $check) {
                    return $check->amlService->name;
                }),

            TD::make('created_at', 'Checked at')
                ->width('150px')
                ->sort()
                ->render(function (AmlAddressCheck $check) {
                    return DateHelper::toDisplayTime($check->created_at)->format('Y-m-d H:i:s');
                }),

            TD::make('check_finish_at', 'Finished')
                ->width('150px')
                ->sort()
                ->render(function (AmlAddressCheck $check) {
                    return $check->check_finish_at
                        ? DateHelper::toDisplayTime($check->check_finish_at)->format('Y-m-d H:i:s')
                        : '-';
                }),

            TD::make('status', 'Status')
                ->width('120px')
                ->sort()
                ->render(fn(AmlAddressCheck $check) => $check->status_name),

            TD::make('aml_risk_score', 'Risk')
                ->width('80px')
                ->sort()
                ->render(function (AmlAddressCheck $check) {
                    if ($check->aml_risk_score === null) {
                        return '-';
                    }

                    $color = $check->aml_risk_score > 0.5 ? 'danger' :
                             ($check->aml_risk_score > 0.2 ? 'warning' : 'success');

                    return "<span class='text-{$color}'><b>{$check->aml_risk_score}</b></span>";
                }),


            TD::make('aml_file_report_link', 'Report')
                ->width('100px')
                ->render(function (AmlAddressCheck $check) {
                    if (!$check->aml_file_report_link) {
                        return '-';
                    }
                    $link = route('aml.report.address', $check->id);
                    return '<a href="' . $link . '" target="_blank" class="btn btn-sm btn-link">
                        <i class="icon-share-alt"></i> Open
                    </a>';
                }),

            TD::make('response', 'Response')
                ->render(function (AmlAddressCheck $check) {
                    if (!$check->response) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($check->response);
                }),

            TD::make('signals', 'Signals')
                ->render(function (AmlAddressCheck $check) {
                    if (!$check->signals) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($check->signals);
                }),

            TD::make('current_tx_count', 'Tx count')
                ->defaultHidden()
                ->width('100px')
                ->render(fn(AmlAddressCheck $check) => $check->current_tx_count ?? 0),

            TD::make('current_transaction_hash', 'USDT Hash')
                ->defaultHidden()
                ->width('150px')
                ->render(function (AmlAddressCheck $check) {
                    if (!$check->current_transaction_hash) {
                        return '-';
                    }
                    return '<code title="' . $check->current_transaction_hash . '">'
                        . substr($check->current_transaction_hash, 0, 8) . '...'
                        . substr($check->current_transaction_hash, -6) . '</code>';
                }),

            TD::make('current_trx_transaction_hash', 'TRX Hash')
                ->defaultHidden()
                ->width('150px')
                ->render(function (AmlAddressCheck $check) {
                    if (!$check->current_trx_transaction_hash) {
                        return '-';
                    }
                    return '<code title="' . $check->current_trx_transaction_hash . '">'
                        . substr($check->current_trx_transaction_hash, 0, 8) . '...'
                        . substr($check->current_trx_transaction_hash, -6) . '</code>';
                }),

            TD::make('error', 'Error')
                ->render(function (AmlAddressCheck $check) {
                    if (!$check->error) {
                        return '<i class="text-success">●</i> No';
                    }
                    return '<span class="text-danger" title="' . e($check->error) . '">
                        <i>●</i> Yes
                    </span>';
                }),

        ];
    }
}
