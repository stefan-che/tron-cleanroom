<?php

namespace App\Services\Aml\CoinKyt;

use App\Helpers\Development;

/**
 * Fetches a signed PDF report link by check UUID.
 * GET /openapi/v1/report-download/{uuid}?language={en|ru}
 * Returns {"report_url": "https://...?token=..."}
 */
class GetReportUrl
{
    public function get(string $uuid, string $language = 'en'): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        return (new Aml("/report-download/{$uuid}", [
            'language' => $language,
        ]))->execute();
    }


    public function getLocalData(): array
    {
        return [
            "report_url" => "https://explorer.coinkyt.com/openapi/v1/report/00000000-0000-0000-0000-000000000003?token=<report_token>"
        ];
    }
}
