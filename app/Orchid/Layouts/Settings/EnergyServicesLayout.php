<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use App\Models\Energy\EnergyService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class EnergyServicesLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'energyServices';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('name', 'Service name')
                ->width('150'),

            TD::make('code', 'Code')
                ->width('100'),

            TD::make('description', 'Description')
                ->defaultHidden()
                ->width('100'),

            TD::make('site_link', 'Website')
                ->width('200'),

            TD::make('api_doc_link', 'API docs')
                ->width('200'),

            TD::make('is_enabled', 'Status')
                ->width('150')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(EnergyService $service) => $service->is_enabled ?
                    '<span class="badge bg-success">Enabled</span>' :
                    '<span class="badge bg-secondary">Disabled</span>'),

            TD::make('actions', 'Actions')
                ->width('150')
                ->align(TD::ALIGN_CENTER)
                ->render(function (EnergyService $service) {
                    return Button::make($service->is_enabled ? 'Disable' : 'Enable')
                        ->icon($service->is_enabled ? 'ban' : 'check')
                        ->class($service->is_enabled ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-outline-success')
                        ->method('toggleEnergyService')
                        ->parameters([
                            'service' => $service->id,
                        ]);

                }),
        ];
    }
}
