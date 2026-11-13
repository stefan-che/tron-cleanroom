<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations\Statistics;

use Orchid\Screen\Layouts\Chart;

class ProcessingTimeLineChart extends Chart
{
    protected $type = self::TYPE_LINE;

    protected $height = 350;
}