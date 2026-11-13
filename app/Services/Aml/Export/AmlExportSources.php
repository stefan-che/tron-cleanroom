<?php

declare(strict_types=1);

namespace App\Services\Aml\Export;

/**
 * Address sources for the AML database export.
 *
 * These are two different entities and must not be confused:
 *
 *  - TRANSACTIONS - sender addresses of incoming transactions
 *    (`transactions.from_address`) together with the AML check verdicts of those
 *    transactions (`transactions_aml_checks`). This is exactly "the wallet the funds
 *    came from": the address is in the database because it actually sent a transfer
 *    to our wallet. One address may have several transfers - for
 *    each service the most recent verdict is used.
 *
 *  - REGISTRY - the `aml_addresses` registry with `aml_addresses_checks` verdicts.
 *    Filled ONLY externally, via `POST /api/aml/{blockchain}/{address}`
 *    (`AmlController::check`); nothing inside the project writes to it.
 *    What the API consumer sends there cannot be inferred from our code,
 *    so "source address of a transfer" is not guaranteed here.
 */
class AmlExportSources
{
    public const string TRANSACTIONS = 'transactions';
    public const string REGISTRY = 'registry';

    public static function all(): array
    {
        return [self::TRANSACTIONS, self::REGISTRY];
    }

    public static function titles(): array
    {
        return [
            self::TRANSACTIONS => 'Transaction sender addresses',
            self::REGISTRY => 'Checked address registry (aml_addresses)',
        ];
    }
}
