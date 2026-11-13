<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\ApiRequest;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\Wallet;
use App\Repositories\WalletRepository;
use App\Rules\TronAddressRule;
use Illuminate\Support\Facades\DB;

/**
 * Change of the recipient address. Payout (sell) operations only
 *
 * @property string $address - wallet address (from the URL)
 * @property string $address_to - new recipient address
 */
class ChangeAddressToRequest extends ApiRequest
{
    private ?Wallet $wallet = null;
    private bool $walletLoaded = false;

    public function rules(): array
    {
        return [
            'address' => [
                'required',
                'string',
                'max:128',
            ],
            'address_to' => array_merge([
                'bail',
                'required',
                'string',
                'max:128',
            ], $this->addressFormatRules()),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $wallet = $this->getWallet();

            if (!$wallet) {
                $validator->errors()->add('address', 'No wallet with this address was found');
                return;
            }

            if (!$wallet->isOperationSell()) {
                $validator->errors()->add('address', 'The recipient address can be changed only for payout (sell) operations');
                return;
            }

            if ($wallet->isTransferMoneyProcessStart()) {
                $validator->errors()->add('address', 'Funds are already being sent, the recipient address cannot be changed');
                return;
            }

            if ($validator->errors()->has('address_to')) {
                return;
            }

            $addressTo = $this->getAddressTo();

            if ($addressTo === $wallet->address) {
                $validator->errors()->add('address_to', 'The address cannot be the current wallet');
            }
        });
    }

    /**
     * Recipient address format rules for the wallet's blockchain
     * @return array
     */
    private function addressFormatRules(): array
    {
        $wallet = $this->getWallet();

        if (!$wallet) {
            return [];
        }

        if ($wallet->isTrc20()) {
            return [new TronAddressRule()];
        }

        $regexp = RecipientWallet::blockChainTypesRegexp()[$wallet->blockchain_type] ?? '';

        return $regexp ? ['regex:' . $regexp] : [];
    }

    /**
     * Wallet by the address from the URL
     */
    public function getWallet(): ?Wallet
    {
        if (!$this->walletLoaded) {
            $this->walletLoaded = true;
            $this->wallet = (new WalletRepository())->getByAddress(trim((string)$this->address));
        }

        return $this->wallet;
    }

    public function getAddressTo(): string
    {
        return trim((string)$this->address_to);
    }

    public function messages(): array
    {
        return [
            'address_to.required' => 'The recipient address is required.',
            'address_to.string' => 'The recipient address must be a string.',
            'address_to.regex' => 'Invalid recipient address.',
            'address_to.max' => 'The recipient address is too long.',
        ];
    }
}
