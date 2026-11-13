<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use App\Models\Ai\AiService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AiServicesLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'aiServices';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('name', 'Service name')
                ->width('200'),

            TD::make('code', 'Code')
                ->width('100'),

            TD::make('model', 'Model')
                ->width('160'),

            TD::make('site_link', 'Website')
                ->width('180'),

            TD::make('is_enabled', 'Status')
                ->width('120')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(AiService $service) => $service->is_enabled ?
                    '<span class="badge bg-success">Enabled</span>' :
                    '<span class="badge bg-secondary">Disabled</span>'),

            TD::make('actions', 'Actions')
                ->width('220')
                ->align(TD::ALIGN_CENTER)
                ->render(function (AiService $service) {
                    return Group::make([
                        ModalToggle::make('Edit')
                            ->icon('pencil')
                            ->class('btn btn-sm btn-outline-primary')
                            ->modal('aiServiceModal')
                            ->modalTitle('AI service: ' . $service->name)
                            ->method('saveAiService')
                            ->asyncParameters([
                                'service' => $service->id,
                            ]),

                        Button::make('Balance')
                            ->icon('wallet')
                            ->class('btn btn-sm btn-outline-secondary')
                            ->method('checkAiServiceBalance')
                            ->parameters([
                                'service' => $service->id,
                            ]),

                        Button::make($service->is_enabled ? 'Disable' : 'Enable')
                            ->icon($service->is_enabled ? 'ban' : 'check')
                            ->class($service->is_enabled ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-outline-success')
                            ->method('toggleAiService')
                            ->parameters([
                                'service' => $service->id,
                            ]),
                    ])->autoWidth();
                }),
        ];
    }
}
