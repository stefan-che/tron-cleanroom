<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations\Statistics;

use Orchid\Screen\Layouts\Chart;

class ConversionRateChart extends Chart
{
    /**
     * Available options:
     * 'bar', 'line',
     * 'pie', 'percentage'.
     *
     * @var string
     */
    protected $type = self::TYPE_PIE;

    /**
     * @var int
     */
    protected $height = 550;
}

