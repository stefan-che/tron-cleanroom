<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Settings;

use App\Models\Tron\TronNode;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TronNodesLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'tronNodes';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('priority', 'Priority')
                ->width('90')
                ->align(TD::ALIGN_CENTER),

            TD::make('name', 'Node')
                ->width('160')
                ->render(fn(TronNode $node) => $node->isTronGrid()
                    ? $node->name . ' <span class="badge bg-info">fallback</span>'
                    : $node->name),

            TD::make('code', 'Code')
                ->width('100'),

            TD::make('full_node', 'Address')
                ->width('220'),

            TD::make('last_block', 'Last block')
                ->width('150')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(TronNode $node) => $node->last_block
                    ? $node->last_block . '<br><small class="text-muted">' . ($node->last_check_at?->diffForHumans() ?? '') . '</small>'
                    : '—'),

            TD::make('is_available', 'Availability')
                ->width('200')
                ->align(TD::ALIGN_CENTER)
                ->render(function (TronNode $node) {
                    if (!$node->is_enabled) {
                        return '<span class="badge bg-secondary">Disabled</span>';
                    }

                    if ($node->is_available) {
                        return '<span class="badge bg-success">Available</span>';
                    }

                    return '<span class="badge bg-danger">Unavailable</span>'
                        . '<br><small class="text-muted">' . e((string)$node->last_error) . '</small>';
                }),

            TD::make('actions', 'Actions')
                ->width('240')
                ->align(TD::ALIGN_CENTER)
                ->render(function (TronNode $node) {
                    return Group::make([
                        ModalToggle::make('Edit')
                            ->icon('pencil')
                            ->class('btn btn-sm btn-outline-primary')
                            ->modal('tronNodeModal')
                            ->modalTitle('Node: ' . $node->name)
                            ->method('saveTronNode')
                            ->asyncParameters([
                                'node' => $node->id,
                            ]),

                        Button::make('Check')
                            ->icon('refresh')
                            ->class('btn btn-sm btn-outline-secondary')
                            ->method('checkTronNode')
                            ->parameters([
                                'node' => $node->id,
                            ]),

                        Button::make($node->is_enabled ? 'Disable' : 'Enable')
                            ->icon($node->is_enabled ? 'ban' : 'check')
                            ->class($node->is_enabled ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-outline-success')
                            ->method('toggleTronNode')
                            ->parameters([
                                'node' => $node->id,
                            ]),

                        Button::make('Delete')
                            ->icon('trash')
                            ->class('btn btn-sm btn-outline-danger')
                            ->confirm('The node will be removed from the list. Continue?')
                            ->method('deleteTronNode')
                            ->parameters([
                                'node' => $node->id,
                            ]),
                    ])->autoWidth();
                }),
        ];
    }
}
