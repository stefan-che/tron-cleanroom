<?php

namespace App\Orchid\Layouts\Recipients;

use App\Models\Recipient\Recipient;
use App\Services\Ledger\LedgerService;
use Orchid\Screen\Actions\Menu;
use Orchid\Screen\Layouts\TabMenu;

class TabMenuLayout extends TabMenu
{
    /**
     * Get the menu elements to be displayed.
     *
     * @return Menu[]
     */
    protected function navigations(): iterable
    {
        /** @var Recipient $recipient */
        $recipient = $this->query->get('recipient');

        $items = [
            Menu::make('Wallets')
                ->rawClick()
                ->route('platform.recipient.wallets', ['recipient' => $recipient->id]),

            Menu::make('Users (Telegram)')
                ->rawClick()
                ->route('platform.recipient.tg_users', ['recipient' => $recipient->id]),

            Menu::make('Recipient details')
                ->rawClick()
                ->route('platform.recipient.edit', ['recipient' => $recipient->id]),
        ];

        if ($recipient->ledger_user_id && app(LedgerService::class)->isConfigured()) {
            $items[] = Menu::make('Ledger accounts')
                ->rawClick()
                ->route('platform.recipient.accounts', ['recipient' => $recipient->id]);
        }

        return $items;
    }
}
