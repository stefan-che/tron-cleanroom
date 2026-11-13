<?php

/**
 * Exports the AML address database for import into an external AML database (aml-import/v1 format).
 *
 * php artisan aml:export
 * php artisan aml:export --gzip
 * php artisan aml:export --since="2026-08-01"            # delta
 * php artisan aml:export --networks=TRX,BTC
 * php artisan aml:export --networks=all
 * php artisan aml:export --with-raw --raw-min-score=40
 * php artisan aml:export --path=/tmp/aml.jsonl
 * php artisan aml:export --sources=transactions          # transaction senders only
 */

namespace App\Console\Commands\Aml;

use App\Services\Aml\Export\AmlDatabaseExporter;
use App\Services\Aml\Export\AmlExportOptions;
use App\Services\Aml\Export\AmlExportStats;
use App\Services\Aml\Export\AmlExportWriter;
use Illuminate\Console\Command;
use Throwable;

class ExportAmlDatabase extends Command
{
    protected $signature = 'aml:export
                            {--path= : Output file path (default: storage/exports/aml/...)}
                            {--networks= : Comma-separated network codes or all (default: from config/aml_export.php)}
                            {--sources= : Comma-separated address sources: transactions, registry}
                            {--since= : Delta — only addresses with checks updated after this date}
                            {--gzip : Compress the export to .jsonl.gz}
                            {--with-raw : Include raw provider responses for risky addresses}
                            {--raw-min-score= : Risk threshold 0-100 for --with-raw}
                            {--chunk= : Batch size when fetching addresses}
                            {--allow-scale-mismatch : Do not reject verdicts outside the declared scale}';

    protected $description = 'Export the AML address database in aml-import/v1 format (JSONL)';

    public function handle(AmlDatabaseExporter $exporter): int
    {
        try {
            $options = AmlExportOptions::fromDefaults([
                'networks' => $this->option('networks'),
                'sources' => $this->option('sources'),
                'since' => $this->option('since'),
                'gzip' => $this->option('gzip') ?: null,
                'withRaw' => $this->option('with-raw') ?: null,
                'rawMinScore' => $this->option('raw-min-score'),
                'chunk' => $this->option('chunk'),
                'allowScaleMismatch' => $this->option('allow-scale-mismatch') ?: null,
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $outputPath = $this->option('path')
            ?: rtrim((string) config('aml_export.dir'), '/') . '/' . $options->fileName();

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error("Failed to create directory: $dir");
            return self::FAILURE;
        }

        $file = fopen($outputPath, 'wb');
        if ($file === false) {
            $this->error("Failed to open file for writing: $outputPath");
            return self::FAILURE;
        }

        $this->info('Sources: ' . implode(', ', $options->sources));

        $total = $exporter->count($options);
        $this->info("Addresses to export: $total");

        $bar = $this->output->createProgressBar(max(1, $total));
        $bar->start();

        $writer = new AmlExportWriter(
            sink: static fn(string $chunk) => fwrite($file, $chunk),
            gzip: $options->gzip,
        );

        try {
            $stats = $exporter->stream($options, function (string $line) use ($writer, $bar) {
                $writer->write($line);
                $bar->advance();
            });

            $writer->finish();
        } finally {
            fclose($file);
        }

        $bar->finish();
        $this->newLine(2);

        $this->printStats($stats);
        $this->info("File saved: $outputPath (" . $this->humanSize(filesize($outputPath) ?: 0) . ')');

        return self::SUCCESS;
    }

    private function printStats(AmlExportStats $stats): void
    {
        $this->info("Addresses exported: {$stats->addresses}");
        $this->info("Verdicts exported: {$stats->checks}");

        if ($stats->bySource !== []) {
            $titles = \App\Services\Aml\Export\AmlExportSources::titles();

            $this->table(
                ['Source', 'Verdicts'],
                collect($stats->bySource)
                    ->map(fn($count, $source) => [$titles[$source] ?? $source, $count])
                    ->values()
                    ->all()
            );
        }

        if ($stats->byNetwork !== []) {
            $this->table(
                ['Network', 'Addresses'],
                collect($stats->byNetwork)->map(fn($count, $code) => [$code, $count])->values()->all()
            );
        }

        if ($stats->multiNetwork > 0) {
            $this->line("Addresses found in multiple networks: {$stats->multiNetwork} (one line each in the file)");
        }

        if ($stats->byProvider !== []) {
            $this->table(
                ['Service', 'Verdicts'],
                collect($stats->byProvider)->map(fn($count, $code) => [$code, $count])->values()->all()
            );
        }

        if ($stats->addressesSkipped > 0) {
            $this->warn("Addresses skipped (no usable verdicts left): {$stats->addressesSkipped}");
        }

        foreach ($stats->checksSkipped as $reason => $count) {
            $this->warn("Verdicts dropped ($reason): $count");
        }

        if ($stats->hasScaleMismatch()) {
            $this->newLine();
            $this->error('Verdicts are outside the declared scale — check config/aml_export.php → scales:');

            foreach ($stats->scaleMismatchMax as $provider => $max) {
                $scale = config("aml_export.scales.$provider", '—');
                $this->error("  $provider: declared \"$scale\", found value $max");
            }
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }
}
