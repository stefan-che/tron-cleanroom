<?php

namespace App\Enums;

enum TransactionStatuses: int
{

    case NEW = 1;

    /**
     * AML check in progress
     */
    case PROCESS = 2;

    /**
     * Check passed
     */
    case FUNDS_VERIFICATION_COMPLETED_SUCCESSFULLY = 3;

    /**
     * Transaction failed the AML check
     */
    case FUNDS_VERIFICATION_FAILED = 4;

    /**
     * AML check error
     */
    case AML_ERROR = 5;
}
