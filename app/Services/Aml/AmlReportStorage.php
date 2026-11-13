<?php

namespace App\Services\Aml;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmlReportStorage
{
    /**
     * Downloads a PDF by URL and stores it in storage/app.
     * Returns the file path, or null on error.
     */
    public function downloadAndStore(string $url, string $storagePath, array $headers = []): ?string
    {
        try {
            $response = Http::withHeaders($headers)
                ->timeout(60)
                ->connectTimeout(10)
                ->get($url);

            if (!$response->successful()) {
                Log::channel('aml')->warning('AmlReportStorage: failed to download report', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            Storage::disk('public')->put($storagePath, $response->body());

            Log::channel('aml')->info('AmlReportStorage: report saved locally', [
                'path' => $storagePath,
            ]);

            return $storagePath;
        } catch (\Throwable $e) {
            Log::channel('aml')->error('AmlReportStorage: exception while downloading report', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Returns a local file as a download response.
     */
    public function serve(string $storagePath, string $filename = 'aml_report.pdf'): StreamedResponse
    {
        return response()->streamDownload(function () use ($storagePath) {
            echo Storage::disk('public')->get($storagePath);
        }, $filename);
    }
}
