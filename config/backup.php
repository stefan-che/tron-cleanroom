<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup of wallet private keys
    |--------------------------------------------------------------------------
    |
    | When disabled, wallets:backup-keys-table does not create the table
    | and BackupWalletKeyJob does not write to backup_db.
    |
    */

    'wallet_keys' => [
        'enabled'    => env('WALLET_KEY_BACKUP_ENABLED', false),
        'connection' => 'backup_db',
        'table'      => 'wallet_keys_backup',
    ],

];
