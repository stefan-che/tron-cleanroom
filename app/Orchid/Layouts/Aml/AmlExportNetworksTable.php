<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Aml;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\Repository;
use Orchid\Screen\TD;

/**
 * Export addresses broken down by network.
 *
 * The `aml_addresses` registry stores 26 networks; transactions store only the one
 * the transfer arrived on. The table shows what the operator leaves out by narrowing
 * the export to TRX, and what else besides TRON is in the database.
 */
class AmlExportNetworksTable extends Table
{
    /**
     * @var string
     */
    public $target = 'networks';

    protected $title = 'Addresses by network';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('code', 'Network')
                ->render(fn(Repository $row) => $row->get('code')),

            TD::make('addresses', 'Addresses')
                ->render(fn(Repository $row) => $row->get('addresses')),
        ];
    }

    protected function iconNotFound(): string
    {
        return 'bs.diagram-3';
    }

    protected function textNotFound(): string
    {
        return 'No addresses';
    }

    protected function subNotFound(): string
    {
        return 'Nothing to export for the selected filters';
    }
}
