<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Aml;

use App\Models\Aml\AmlAddress;
use App\Models\Role;
use App\Orchid\Layouts\Aml\AmlExportFormLayout;
use App\Orchid\Layouts\Aml\AmlExportNetworksTable;
use App\Orchid\Layouts\Aml\AmlExportSummaryTable;
use App\Services\Aml\Export\AmlDatabaseExporter;
use App\Services\Aml\Export\AmlExportOptions;
use App\Services\Aml\Export\AmlExportWriter;
use Illuminate\Http\Request;
use Orchid\Screen\Repository;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Exports the accumulated AML database for import into an external AML database.
 *
 * Format: `aml-import/v1` (JSONL, described in AmlDatabaseExporter). The screen shows what goes
 * into the file before it is downloaded: an export of 20k addresses takes minutes,
 * and finding a mixed-up scale only on the receiving side is too late.
 */
class AmlExportScreen extends Screen
{
    public function query(Request $request, AmlDatabaseExporter $exporter): iterable
    {
        $options = $this->options($request);

        $byProvider = $exporter->checksByProvider($options);
        $byNetwork = $exporter->addressesByNetwork($options);

        return [
            'form' => [
                'sources' => $options->sources,
                'networks' => $options->networks === [] ? ['ALL'] : $options->networks,
                'since' => $request->get('since'),
                'gzip' => $options->gzip,
                'with_raw' => $options->withRaw,
                'raw_min_score' => $options->rawMinScore,
                'allow_scale_mismatch' => $options->allowScaleMismatch,
            ],
            'metrics' => [
                'addresses' => ['value' => array_sum($byNetwork)],
                'checks' => ['value' => array_sum($byProvider)],
                'providers' => ['value' => count($byProvider)],
            ],
            'providers' => $this->providerRows($byProvider),
            'networks' => $this->networkRows($byNetwork),
        ];
    }

    public function name(): ?string
    {
        return 'AML database export';
    }

    public function description(): ?string
    {
        return 'Export of addresses and AML provider verdicts in aml-import/v1 (JSONL) format for import into an external AML database';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_AML,
        ];
    }

    /**
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::metrics([
                'Addresses in export' => 'metrics.addresses',
                'Verdicts (upper bound)' => 'metrics.checks',
                'Providers' => 'metrics.providers',
            ]),

            Layout::columns([
                AmlExportSummaryTable::class,
                AmlExportNetworksTable::class,
            ]),

            AmlExportFormLayout::class,
        ];
    }

    /**
     * Streams the export file: there is no point buffering tens of megabytes
     * in memory to send them at once.
     */
    public function export(Request $request, AmlDatabaseExporter $exporter): StreamedResponse|null
    {
        try {
            $options = $this->options($request);
        } catch (Throwable $e) {
            Toast::error('Failed to build the export: ' . $e->getMessage());

            return null;
        }

        $fileName = $options->fileName();

        return response()->streamDownload(function () use ($exporter, $options) {
            // An export of 20k addresses exceeds the default execution time
            // limit, and an interruption midway produces a broken file.
            set_time_limit(0);

            $writer = new AmlExportWriter(
                sink: static function (string $chunk) {
                    echo $chunk;
                    flush();
                },
                gzip: $options->gzip,
            );

            $exporter->stream($options, static fn(string $line) => $writer->write($line));

            $writer->finish();
        }, $fileName, [
            'Content-Type' => $options->gzip ? 'application/gzip' : 'application/x-ndjson',
        ]);
    }

    /**
     * Export parameters from the screen form.
     */
    private function options(Request $request): AmlExportOptions
    {
        $networks = $request->get('networks');

        if (is_array($networks) && in_array('ALL', $networks, true)) {
            $networks = [];
        }

        return AmlExportOptions::fromDefaults([
            'networks' => $networks,
            'sources' => $request->get('sources'),
            'since' => $request->get('since'),
            'gzip' => $request->boolean('gzip') ?: null,
            'withRaw' => $request->boolean('with_raw') ?: null,
            'rawMinScore' => $request->get('raw_min_score'),
            'allowScaleMismatch' => $request->boolean('allow_scale_mismatch') ?: null,
        ]);
    }

    /**
     * Rows of the "export contents" table: provider, declared scale, verdict count.
     *
     * @param array<string, int> $byProvider
     * @return Repository[]
     */
    private function providerRows(array $byProvider): array
    {
        $scales = (array) config('aml_export.scales', []);

        ksort($byProvider);

        return collect($byProvider)
            ->map(fn(int $count, string $code) => new Repository([
                'code' => $code,
                'scale' => $scales[$code] ?? null,
                'checks' => $count,
            ]))
            ->values()
            ->all();
    }

    /**
     * Rows of the "addresses by network" table.
     *
     * @param array<string, int> $byNetwork
     * @return Repository[]
     */
    private function networkRows(array $byNetwork): array
    {
        return collect($byNetwork)
            ->map(fn(int $count, string $code) => new Repository([
                'code' => $code,
                'addresses' => $count,
            ]))
            ->values()
            ->all();
    }

    /**
     * Networks available for selection in the form.
     *
     * @return array<string, string>
     */
    public static function networkOptions(): array
    {
        $options = ['ALL' => 'All networks'];

        foreach (AmlAddress::amlCodes() as $code) {
            $options[$code] = $code;
        }

        return $options;
    }
}
