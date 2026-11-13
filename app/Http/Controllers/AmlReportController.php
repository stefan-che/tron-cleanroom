<?php

namespace App\Http\Controllers;

use App\Models\Aml\AmlAddressCheck;
use App\Models\Wallet\TransactionAmlCheck;
use App\Services\Aml\AmlReportStorage;
use App\Services\Aml\AmlServiceDispatcher;
use Illuminate\Support\Facades\Storage;

class AmlReportController extends Controller
{
    /**
     * Download the AML report by address check ID
     */
    public function downloadAddressReport(string $checkId)
    {
        $check = AmlAddressCheck::with('amlService')->find($checkId);

        if (!$check) {
            return response()->json(['error' => 'Check not found'], 404);
        }

        if (!$check->aml_file_report_link && !$check->response) {
            return response()->json(['error' => 'Report file not found'], 404);
        }

        if ($check->local_report_path && Storage::disk('public')->exists($check->local_report_path)) {
            return app(AmlReportStorage::class)->serve($check->local_report_path);
        }

        $dispatcher = app(AmlServiceDispatcher::class);
        return $dispatcher->downloadReport($check->amlService->code, $check->aml_file_report_link, $check->response);
    }

    /**
     * Download the AML report by transaction check ID
     */
    public function downloadTransactionReport(string $checkId)
    {
        $check = TransactionAmlCheck::with('service')->find($checkId);

        if (!$check) {
            return response()->json(['error' => 'Check not found'], 404);
        }

        if (!$check->file_report_link && !$check->response) {
            return response()->json(['error' => 'Report file not found'], 404);
        }

        if ($check->local_report_path && Storage::disk('public')->exists($check->local_report_path)) {
            return app(AmlReportStorage::class)->serve($check->local_report_path);
        }

        $dispatcher = app(AmlServiceDispatcher::class);
        return $dispatcher->downloadReport($check->service->code, $check->file_report_link, $check->response);
    }
}
