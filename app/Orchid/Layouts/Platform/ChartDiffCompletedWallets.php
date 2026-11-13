<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Platform;

use Orchid\Screen\Layouts\Chart;

class ChartDiffCompletedWallets extends Chart
{
    /**
     * Available options:
     * 'bar', 'line',
     * 'pie', 'percentage'.
     *
     * @var string
     */
    protected $type = self::TYPE_BAR;

    /**
     * @var int
     */
    protected $height = 300;

}
