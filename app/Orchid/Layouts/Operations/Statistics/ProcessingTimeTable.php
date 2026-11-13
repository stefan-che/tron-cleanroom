<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations\Statistics;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ProcessingTimeTable extends Table
{
    public $target = 'processingTimeTable';

    public function columns(): array
    {
        return [
            TD::make('day', 'Date')
                ->render(fn($row) => $row['day']),

            TD::make('recipient', 'Recipient')
                ->render(fn($row) => $row['recipient']),

            TD::make('avg', 'Average (min)')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn($row) => number_format($row['avg'], 1)),

            TD::make('min', 'Min (min)')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn($row) => $row['min']),

            TD::make('max', 'Max (min)')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn($row) => $row['max']),

            TD::make('count', 'Operations')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn($row) => $row['count']),
        ];
    }
}