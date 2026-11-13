<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\AiAmlFormatter;
use App\Helpers\DateHelper;
use App\Helpers\LogFormatter;
use App\Models\Wallet\WalletAiAmlCheck;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class WalletAiChecksList extends Table
{
    /**
     * @var string
     */
    public $target = 'ai_checks';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('created_at', 'Date/Time')
                ->width('150')
                ->render(fn(WalletAiAmlCheck $check) => DateHelper::toDisplayTime($check->created_at)->format('Y-m-d H:i:s')),

            TD::make('service', 'AI service')
                ->width('150')
                ->render(fn(WalletAiAmlCheck $check) => $check->service?->name ?? '-'),

            TD::make('prompt', 'prompt')
                ->defaultHidden()
                ->render(fn(WalletAiAmlCheck $check) => $check->prompt),

            TD::make('wallet_color', 'Wallet')
                ->width('100')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(WalletAiAmlCheck $check) => AiAmlFormatter::colorBadge($check->wallet_color)),

            TD::make('coinkyt', 'CoinKYT')
                ->width('280')
                ->render(function (WalletAiAmlCheck $check) {
                    $coinkyt = $check->coinkyt_result;

                    if (empty($coinkyt)) {
                        return '<span class="text-muted">-</span>';
                    }

                    $meta = [];
                    if (!empty($coinkyt['risk'])) {
                        $meta[] = e((string) $coinkyt['risk']);
                    }
                    if (isset($coinkyt['confidence'])) {
                        $meta[] = (int) $coinkyt['confidence'] . '%';
                    }

                    $badge = AiAmlFormatter::colorBadge($coinkyt['wallet_color'] ?? null);
                    $metaLine = $meta
                        ? ' <span class="small text-muted">' . implode(' · ', $meta) . '</span>'
                        : '';

                    $summary = !empty($coinkyt['summary'])
                        ? '<div class="small mt-1">' . e((string) $coinkyt['summary']) . '</div>'
                        : '';

                    return '<div>' . $badge . $metaLine . '</div>' . $summary;
                }),

            TD::make('risk', 'Risk')
                ->width('90')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(WalletAiAmlCheck $check) => $check->risk ?? '-'),

            TD::make('confidence', 'Confidence')
                ->width('100')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(WalletAiAmlCheck $check) => $check->confidence !== null ? $check->confidence . '%' : '-'),

            TD::make('summary', 'Summary')
                ->render(function (WalletAiAmlCheck $check) {
                    if ($check->status === WalletAiAmlCheck::STATUS_FAILED) {
                        return '<span class="text-danger">Error: ' . e((string) $check->error) . '</span>';
                    }
                    if ($check->status === WalletAiAmlCheck::STATUS_PENDING) {
                        return '<span class="text-muted">Processing…</span>';
                    }
                    return e((string) $check->summary);
                }),

            TD::make('raw_response', 'Provider response')
                ->render(function (WalletAiAmlCheck $check) {
                    if (empty($check->raw_response)) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($check->raw_response);
                }),

            TD::make('request_payload', 'AI request')
                ->render(function (WalletAiAmlCheck $check) {
                    if (empty($check->request_payload)) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($check->request_payload);
                }),
        ];
    }
}
