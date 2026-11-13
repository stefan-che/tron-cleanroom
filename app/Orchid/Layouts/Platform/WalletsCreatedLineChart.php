<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Platform;

use Orchid\Screen\Layouts\Chart;

class WalletsCreatedLineChart extends Chart
{
    protected $type = self::TYPE_LINE;

    protected $height = 350;
}
