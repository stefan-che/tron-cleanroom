<?php

namespace App\Helpers;

class DateHelper
{

    public static function toDisplayTime($dateTime)
    {
        return $dateTime?->setTimezone(config('app.display_timezone', config('app.timezone')));
    }

}
