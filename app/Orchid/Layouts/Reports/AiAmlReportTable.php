<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Reports;

use App\Helpers\AiAmlFormatter;
use App\Models\Aml\AmlService;
use App\Reports\AiAml\AiAmlChecksReport;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\Repository;
use Orchid\Screen\TD;

class AiAmlReportTable extends Table
{
    /**
     * @var string
     */
    public $target = 'rows';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        $columns = [
            TD::make('created_at', 'Date/Time')
                ->width('150'),

            TD::make('wallet_external_id', 'External ID')
                ->width('100'),

            TD::make('wallet_address', 'Wallet address')
                ->width('160')
                ->render(fn(Repository $row) => '<span class="text-truncate d-inline-block" style="max-width: 150px;">'
                    . e((string) $row->get('wallet_address')) . '</span>'),

            TD::make('tx_hash', 'Transaction hash')
                ->width('160')
                ->render(fn(Repository $row) => '<span class="text-truncate d-inline-block" style="max-width: 150px;">'
                    . e((string) $row->get('tx_hash')) . '</span>'),

            TD::make('ai_wallet_color', 'AI')
                ->width('120')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(Repository $row) => AiAmlFormatter::colorBadge($row->get('ai_wallet_color'))
                    . $this->riskMeta($row->get('ai_risk'), $row->get('ai_confidence'))),

            TD::make('coinkyt_wallet_color', 'AI · CoinKYT')
                ->width('120')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(Repository $row) => AiAmlFormatter::colorBadge($row->get('coinkyt_wallet_color'))
                    . $this->riskMeta($row->get('coinkyt_risk'))),

            TD::make('operator_wallet_type', 'Operator decision')
                ->width('140')
                ->align(TD::ALIGN_CENTER)
                ->popover('Wallet type the operator actually sent the funds to. '
                    . 'The caption under the badge shows whether this decision matched the AI verdict '
                    . '(WHITE, YELLOW, GREY, BLACK).')
                ->render(fn(Repository $row) => AiAmlFormatter::walletTypeBadge($row->get('operator_wallet_type'))
                    . $this->matchMark($row)),
        ];

        foreach (AiAmlChecksReport::services() as $service) {
            $columns[] = $this->serviceColumn($service);
        }

        $columns[] = TD::make('score_spread', 'Spread')
            ->width('110')
            ->align(TD::ALIGN_CENTER)
            ->popover('Difference between the highest and lowest AML service score '
                . 'for this transaction: the larger it is, the more the services disagree. '
                . 'Below: σ is the score standard deviation and the number of services that returned a result.')
            ->render(function (Repository $row) {
                $spread = $row->get('score_spread');

                if ($spread === null) {
                    return '<span class="text-muted">-</span>';
                }

                return e((string) $spread)
                    . '<div class="small text-muted">σ ' . e((string) $row->get('score_std_dev'))
                    . ' · ' . (int) $row->get('services_count') . ' services</div>';
            });

        return $columns;
    }

    /**
     * Marks whether the operator decision matches the AI verdict.
     */
    private function matchMark(Repository $row): string
    {
        $matched = AiAmlChecksReport::isDecisionMatched($row->toArray());

        if ($matched === null) {
            return '';
        }

        return $matched
            ? '<div class="small text-success">match</div>'
            : '<div class="small text-warning">mismatch</div>';
    }

    private function serviceColumn(AmlService $service): TD
    {
        $key = AiAmlChecksReport::SERVICE_COLUMN_PREFIX . $service->code;
        $colorKey = AiAmlChecksReport::SERVICE_COLOR_PREFIX . $service->code;
        $noteKey = AiAmlChecksReport::SERVICE_COLOR_NOTE_PREFIX . $service->code;

        return TD::make($key, $service->name)
            ->width('120')
            ->align(TD::ALIGN_CENTER)
            ->popover('Color based on this service only, computed in PHP using the rules '
                . 'from the AI prompt (shares of dangerous and suspicious sources, sanctions, counterparties). '
                . 'Under the badge: the service risk score; hover the badge for the color rationale. '
                . 'A dash instead of a color means the service returned no source-of-funds data.')
            ->render(function (Repository $row) use ($key, $colorKey, $noteKey) {
                $score = $row->get($key);

                if ($score === null) {
                    return '<span class="text-muted">-</span>';
                }

                $note = $row->get($noteKey);

                return '<span title="' . e((string) $note) . '">'
                    . AiAmlFormatter::colorBadge($row->get($colorKey))
                    . '</span>'
                    . '<div class="small text-muted">' . e((string) $score) . '</div>';
            });
    }

    /**
     * Caption under the badge: AI risk and confidence.
     */
    private function riskMeta(?string $risk, ?int $confidence = null): string
    {
        $parts = array_filter([
            $risk ? e($risk) : null,
            $confidence !== null ? $confidence . '%' : null,
        ]);

        return $parts
            ? '<div class="small text-muted">' . implode(' · ', $parts) . '</div>'
            : '';
    }
}
