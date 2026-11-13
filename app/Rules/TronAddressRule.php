<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class TronAddressRule implements Rule
{
    public function __construct()
    {
    }

    public function passes($attribute, $value): bool
    {
        $trxPattern = '/T[A-Za-z1-9]{33}$/';
        $usdtPattern = '/^T[1-9A-HJ-NP-Za-km-z]{33}$/';

        return preg_match($trxPattern, $value) || preg_match($usdtPattern, $value);
    }

    public function message(): string
    {
        return 'Invalid USDT address';
    }
}
