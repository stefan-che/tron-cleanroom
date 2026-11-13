<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Aml;

use App\Models\Aml\AmlAddress;
use App\Models\Role;
use App\Orchid\Layouts\Aml\FiltersLayout;
use App\Orchid\Layouts\Aml\ListLayout;
use App\Services\Aml\Export\AmlDatabaseExporter;
use App\Services\Aml\Export\AmlExportOptions;
use App\Services\Aml\Export\AmlExportWriter;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmlListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'addresses' => AmlAddress::filters(FiltersLayout::class)
                ->defaultSort('id','desc')
                ->paginate(20),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'AML checks';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return '';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_AML,
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Export AML database')
                ->icon('bs.download')
                ->rawClick()
                ->method('export'),

            Link::make('Configure export')
                ->icon('bs.sliders')
                ->route('platform.aml.export'),
        ];
    }

    /**
     * Quick AML database export in aml-import/v1 format with default
     * settings (networks from config/aml_export.php, no raw responses).
     *
     * Delta, compression and other options are on the "AML database export" screen.
     */
    public function export(AmlDatabaseExporter $exporter): StreamedResponse
    {
        $options = AmlExportOptions::fromDefaults();

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
        }, $options->fileName(), [
            'Content-Type' => 'application/x-ndjson',
        ]);
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            FiltersLayout::class,
            ListLayout::class,
        ];
    }


}
