<?php

namespace App\Enums;

enum AmlAddressStatuses: int
{

    case NEW = 1;

    case PROCESS = 2;

    case COMPLETED = 3;
    case ERROR = 4;

    case TIME_OUT = 5;
    case NO_NEW_CHECK = 6;
    case IN_QUEUE = 7;

    public static function options(): array
    {
        return [

            self::NEW->value => 'new',
            self::PROCESS->value => 'process',
            self::COMPLETED->value => 'completed',
            self::ERROR->value => 'error',
            self::TIME_OUT->value => 'timeout',
            self::NO_NEW_CHECK->value => 'no_new_check',
            self::IN_QUEUE->value => 'in_queue',
        ];
    }
}
