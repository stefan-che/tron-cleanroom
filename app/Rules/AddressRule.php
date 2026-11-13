<?php

namespace App\Rules;

use App\Models\Aml\AmlAddress;
use Illuminate\Contracts\Validation\Rule;

class AddressRule implements Rule
{
    private string $blockchain;

    public function __construct(string $blockchain)
    {
        $this->blockchain = $blockchain;
    }

    public function passes($attribute, $value): bool
    {
        $blockchainType = AmlAddress::getBlockChainTypeByName($this->blockchain);
        $patterns = AmlAddress::blockChainTypesRegexp();

        if (!isset($patterns[$blockchainType])) {
            return false;
        }

        return (bool)preg_match($patterns[$blockchainType], $value);
    }

    public function message(): string
    {
        return 'Invalid address for the selected blockchain type';
    }
}
