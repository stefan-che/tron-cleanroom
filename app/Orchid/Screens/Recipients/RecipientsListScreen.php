<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Recipients;

use App\Models\Recipient\Recipient;
use App\Models\Role;
use App\Orchid\Layouts\Recipients\FiltersLayout;
use App\Orchid\Layouts\Recipients\ListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;

class RecipientsListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'recipients' => Recipient::withCount(['wallets', 'tgUsers'])
                ->filters(FiltersLayout::class)
                ->paginate(20),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Recipients';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return '';
    }

    public function permission(): ?iterable
    {
        return [
            Role::PERMISSION_RECIPIENTS,
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Create recipient')
                ->icon('plus')
                ->route('platform.recipient.create'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            FiltersLayout::class,
            ListLayout::class,
        ];
    }


}
