<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use App\Models\Aml\AmlService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AmlServicesLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'amlServices';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('name', 'Service name')
                ->width('250'),

            TD::make('code', 'Code')
                ->width('150'),

            TD::make('site_link', 'Website')
                ->width('200'),

            TD::make('api_doc_link', 'API docs')
                ->width('200'),

            TD::make('is_enabled', 'Status')
                ->width('150')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(AmlService $service) => $service->is_enabled ?
                    '<span class="badge bg-success">Enabled</span>' :
                    '<span class="badge bg-secondary">Disabled</span>'),

            TD::make('actions', 'Actions')
                ->width('150')
                ->align(TD::ALIGN_CENTER)
                ->render(function (AmlService $service) {
                    return Button::make($service->is_enabled ? 'Disable' : 'Enable')
                        ->icon($service->is_enabled ? 'ban' : 'check')
                        ->class($service->is_enabled ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-outline-success')
                        ->method('toggleAmlService')
                        ->parameters([
                            'service' => $service->id,
                        ]);

                }),
        ];
    }
}








