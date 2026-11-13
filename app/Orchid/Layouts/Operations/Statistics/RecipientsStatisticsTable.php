<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations\Statistics;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class RecipientsStatisticsTable extends Table
{
    /**
     * @var string
     */
    public $target = 'recipientsStatistics';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('recipient', 'Recipient')
                ->render(fn ($recipient) => $recipient['recipient']),

            TD::make('new', 'Expired')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn ($recipient) => $recipient['new']),

            TD::make('completed', 'Completed')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn ($recipient) => $recipient['completed']),

            TD::make('total', 'Total wallets')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn ($recipient) => $recipient['total']),

            TD::make('conversion', 'Conversion')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn ($recipient) => number_format($recipient['conversion'], 2) . '%'),
        ];
    }
}

