<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Security;

use App\Models\User\BlockedIp;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class BlockedIpsListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'blocked_ips';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('ip_address', 'IP address')
                ->sort()
                ->cantHide()
                ->filter(Input::make())
                ->render(function (BlockedIp $blockedIp) {
                    $status = $blockedIp->is_permanently_blocked ? 'danger' : 'warning';
                    $icon = $blockedIp->unblocked_at ? 'bs.unlock' : 'bs.lock';

                    return "<span class='badge bg-{$status} me-2'><i class='{$icon}'></i></span>" . $blockedIp->ip_address;
                }),

            TD::make('reason', 'Block reason')
                ->sort()
                ->filter(Input::make()),

            TD::make('attempts', 'Attempts')
                ->sort()
                ->align(TD::ALIGN_CENTER),

            TD::make('is_permanently_blocked', 'Block type')
                ->sort()
                ->render(function (BlockedIp $blockedIp) {
                    if ($blockedIp->unblocked_at) {
                        return '<span class="badge bg-success">Unblocked</span>';
                    }

                    return $blockedIp->is_permanently_blocked
                        ? '<span class="badge bg-danger">Permanent</span>'
                        : '<span class="badge bg-warning">Temporary</span>';
                }),

            TD::make('blocked_until', 'Blocked until')
                ->sort()
                ->render(function (BlockedIp $blockedIp) {
                    if ($blockedIp->unblocked_at) {
                        return '<span class="text-muted">Unblocked</span>';
                    }

                    if ($blockedIp->is_permanently_blocked) {
                        return '<span class="text-danger">Permanent</span>';
                    }

                    return $blockedIp->blocked_until
                        ? $blockedIp->blocked_until->format('d.m.Y H:i')
                        : '-';
                }),

            TD::make('unblocked_at', 'Unblocked at')
                ->sort()
                ->render(function (BlockedIp $blockedIp) {
                    if (!$blockedIp->unblocked_at) {
                        return '-';
                    }

                    return $blockedIp->unblocked_at->format('d.m.Y H:i');
                }),

            TD::make('unblockedBy.name', 'Unblocked by')
                ->sort()
                ->render(function (BlockedIp $blockedIp) {
                    return $blockedIp->unblockedBy?->name ?? '-';
                }),

            TD::make('created_at', 'Created')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->defaultHidden()
                ->sort(),

            TD::make('updated_at', 'Updated')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('120px')
                ->render(function (BlockedIp $blockedIp) {
                    $actions = [];

                    if (!$blockedIp->unblocked_at) {
                        $actions[] = Button::make('Unblock')
                            ->icon('bs.unlock')
                            ->method('unblock', ['id' => $blockedIp->id])
                            ->confirm('Are you sure you want to unblock this IP address?');
                    }

                    $actions[] = Link::make('Edit')
                        ->route('platform.security.blocked-ips.edit', $blockedIp->id)
                        ->icon('bs.pencil');

                    $actions[] = Button::make('Delete')
                        ->icon('bs.trash3')
                        ->method('destroy', ['id' => $blockedIp->id])
                        ->confirm('Are you sure you want to delete this record?');

                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list($actions);
                }),
        ];
    }
}
