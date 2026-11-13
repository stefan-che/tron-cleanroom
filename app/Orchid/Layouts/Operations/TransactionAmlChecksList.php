<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\DateHelper;
use App\Helpers\LogFormatter;
use App\Models\Wallet\TransactionAmlCheck;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TransactionAmlChecksList extends Table
{
    /**
     * @var string
     */
    public $target = 'transaction_aml_checks';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width('60px')
                ->defaultHidden()
                ->render(fn(TransactionAmlCheck $check) => $check->id),

            TD::make('service', 'Service')
                ->render(function (TransactionAmlCheck $check) {
                    return $check->service->name ?? '-';
                }),

            TD::make('created_at', 'Created at')
                ->width('150px')
                ->defaultHidden()
                ->render(function (TransactionAmlCheck $check) {
                    return DateHelper::toDisplayTime($check->created_at)->format('Y-m-d H:i:s');
                }),

            TD::make('check_finish_at', 'Finished')
                ->width('150px')
                ->defaultHidden()
                ->render(function (TransactionAmlCheck $check) {
                    return $check->check_finish_at
                        ? DateHelper::toDisplayTime($check->check_finish_at)->format('Y-m-d H:i:s')
                        : '-';
                }),

            TD::make('status', 'Status')
                ->width('120px')
                ->render(fn(TransactionAmlCheck $check) => $check->status_name),

            TD::make('risk_score', 'Risk')
                ->width('80px')
                ->render(function (TransactionAmlCheck $check) {
                    if ($check->risk_score === null) {
                        return '-';
                    }

                    $color = $check->risk_score > 0.5 ? 'danger' :
                             ($check->risk_score > 0.2 ? 'warning' : 'success');

                    return "<span class='text-{$color}'><b>{$check->risk_score_normalize}</b></span>";
                }),

            TD::make('file_report_link', 'Report')
                ->width('100px')
                ->render(function (TransactionAmlCheck $check) {
                    if (!$check->report_file_url) {
                        return '-';
                    }
                    $link = route('aml.report.transaction', $check->id);
                    return '<a href="' . $link . '" target="_blank" class="btn btn-sm btn-link">
                        <i class="icon-share-alt"></i> Open
                    </a>';
                }),

            TD::make('response', 'Response')
                ->defaultHidden()
                ->render(function (TransactionAmlCheck $check) {
                    if (!$check->response) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($check->response);
                }),

            TD::make('signals', 'Signals')
                ->defaultHidden()
                ->render(function (TransactionAmlCheck $check) {
                    if (!$check->signals) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($check->signals);
                }),

            TD::make('uid', 'UID')
                ->defaultHidden()
                ->render(fn(TransactionAmlCheck $check) => $check->uid ?? '-'),

            TD::make('error_info', 'Error')
                ->render(function (TransactionAmlCheck $check) {
                    if (!$check->isFailed()) {
                        return '<i class="text-success">●</i> No';
                    }
                    return '<span class="text-danger"><i>●</i> Yes</span>';
                }),
        ];
    }
}
