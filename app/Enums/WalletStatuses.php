<?php

namespace App\Enums;

enum WalletStatuses: int
{

    case NEW = 1;

    /**
     * Transaction added
     */
    case ADD_TRANSACTION = 2;

    /**
     * Incoming transaction check started
     */
    case CHECK_TRANSACTION_START = 3;

    /**
     * Incoming transaction check finished
     */
    case CHECK_TRANSACTION_FINISH = 4;

    /**
     * Transfer of the full amount to the recipient wallet started
     */
    case TRANSFER_MONEY_PROCESS_START = 5;

    /**
     * Transfer of the full amount to the recipient wallet finished
     */
    case TRANSFER_MONEY_PROCESS_FINISH = 6;

    /**
     * Transfer completed
     */
    case COMPLETED = 10;

    case TIME_OUT = 11;


}
