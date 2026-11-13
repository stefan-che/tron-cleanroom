<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Helpers\ArrayHelper;
use App\Services\Aml\AmlBitOk\Aml;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class Report
{
    /**
     * Fetches the PDF report for a transaction
     *
     * @param string $id Transaction ID in BitOk
     * @return array
     */
    public function getReport(string $id): array
    {
        $runner = new Aml(sprintf('/v1/transfers/%s/pdf-report/', $id));
        return $runner->executeFile();
    }

    /**
     * Saves the PDF report to a file
     *
     * @param string $id Transaction ID
     * @return string
     */
    public function saveReport(string $id): string
    {
        try {
            $content = $this->getReport($id);

            if (empty($content['content'])) {
                throw new \RuntimeException(sprintf('Content file error: %s', $content['status'] ?? ''));
            }

            $filename = sprintf('aml-reports/%s.pdf', $id);

            if (!Storage::disk('public')->put($filename, $content['content'])) {
                throw new \RuntimeException('Failed to save file to storage');
            }

            return '/storage/' . $filename;

        } catch (\Exception $exception) {
            Log::error('Failed to save AML report', [
                'id' => $id,
                'error' => $exception->getMessage()
            ]);
            return '';
        }
    }
}
