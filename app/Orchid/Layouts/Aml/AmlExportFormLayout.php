<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Aml;

use App\Orchid\Screens\Aml\AmlExportScreen;
use App\Services\Aml\Export\AmlExportSources;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

/**
 * AML database export form.
 *
 * The button is marked rawClick: the file downloads via a regular form submit,
 * since a turbo request cannot consume the response stream.
 */
class AmlExportFormLayout extends Rows
{
    public function fields(): array
    {
        return [
            Select::make('sources')
                ->options(AmlExportSources::titles())
                ->multiple()
                ->value(AmlExportSources::all())
                ->title('Address sources')
                ->help('"Transaction sender addresses" are those that actually sent a transfer to our wallet. The aml_addresses registry is filled only via the external API, and what the client sends there is not determined by our code'),

            Select::make('networks')
                ->options(AmlExportScreen::networkOptions())
                ->multiple()
                ->value(['TRX'])
                ->title('Networks')
                ->help('The receiver currently accepts only TRX; other networks are rejected as foreign_network'),

            DateTimer::make('since')
                ->title('Delta: only checks updated after')
                ->format('Y-m-d H:i:s')
                ->enableTime()
                ->allowInput()
                ->help('Empty means a full export. An address is included if it has a recent check; all its verdicts are included, otherwise the receiver would recompute risk from an incomplete set'),

            CheckBox::make('gzip')
                ->sendTrueOrFalse()
                ->title('Compression')
                ->placeholder('Download as .jsonl.gz'),

            CheckBox::make('with_raw')
                ->sendTrueOrFalse()
                ->title('Raw provider responses')
                ->placeholder('Include checks[].raw for risky addresses')
                ->help('Off by default: raw responses inflate the file by an order of magnitude and are only needed to investigate disputed cases'),

            Input::make('raw_min_score')
                ->type('number')
                ->min(0)
                ->max(100)
                ->value(config('aml_export.raw_min_score', 40))
                ->title('Risk threshold for raw responses (0–100)'),

            CheckBox::make('allow_scale_mismatch')
                ->sendTrueOrFalse()
                ->title('Verdicts outside the declared scale')
                ->placeholder('Export them, inferring the scale from the value')
                ->help('By default such verdicts are rejected: a risk silently off by 10x looks like a clean address on the receiver. The proper fix is to correct config/aml_export.php → scales'),

            Button::make('Download export')
                ->icon('bs.download')
                ->rawClick()
                ->method('export'),
        ];
    }
}
