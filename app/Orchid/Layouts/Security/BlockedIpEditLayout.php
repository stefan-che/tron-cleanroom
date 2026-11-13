<?php

namespace App\Orchid\Layouts\Security;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Layouts\Rows;

class BlockedIpEditLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {

        return [
            Input::make('blocked_ip.ip_address')
                ->type('text')
                ->required()
                ->title('IP address')
                ->placeholder('192.168.1.1')
                ->help('Enter the IP address to block'),

            TextArea::make('blocked_ip.reason')
                ->required()
                ->title('Block reason')
                ->placeholder('Describe the block reason')
                ->rows(3),

            CheckBox::make('blocked_ip.is_permanently_blocked')
                ->title('Permanent block')
                ->sendTrueOrFalse()
                ->placeholder('Block permanently')
                ->help('If checked, the IP is blocked permanently'),

            DateTimer::make('blocked_ip.blocked_until')
                ->title('Block until')
                ->placeholder('Select date and time')
                ->help('Date and time until which the IP is blocked (if not permanent)')
                ->canSee(!$this->query['blocked_ip']->is_permanently_blocked ?? false),

            Input::make('blocked_ip.attempts')
                ->type('number')
                ->title('Attempts')
                ->placeholder('0')
                ->help('Number of failed login attempts'),

            TextArea::make('blocked_ip.user_agent')
                ->title('User Agent')
                ->placeholder('Browser/device information')
                ->rows(2)
                ->readonly(),
        ];
    }
}
