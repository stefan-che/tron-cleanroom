<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Aml;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\Repository;
use Orchid\Screen\TD;

/**
 * Export contents broken down by AML service.
 *
 * The scale column matters: a service without a declared scale is left out
 * of the file entirely, and that must be visible before downloading.
 */
class AmlExportSummaryTable extends Table
{
    /**
     * @var string
     */
    public $target = 'providers';

    protected $title = 'Export contents by service';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('code', 'Service')
                ->render(fn(Repository $row) => $row->get('code')),

            TD::make('scale', 'Risk score scale')
                ->render(function (Repository $row) {
                    $scale = $row->get('scale');

                    return $scale
                        ? "<code>$scale</code>"
                        : "<span class='text-danger'><b>not declared — verdicts will not be included in the file</b></span>";
                }),

            TD::make('checks', 'Completed verdicts')
                ->render(fn(Repository $row) => $row->get('checks')),
        ];
    }

    protected function iconNotFound(): string
    {
        return 'bs.database';
    }

    protected function textNotFound(): string
    {
        return 'No completed checks';
    }

    protected function subNotFound(): string
    {
        return 'Nothing to export for the selected filters';
    }
}
