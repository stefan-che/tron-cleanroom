<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations\Statistics;

use Orchid\Screen\Layouts\Chart;

class WalletTypesByRecipientBarChart extends Chart
{
    protected $type = self::TYPE_BAR;

    protected $height = 400;
}