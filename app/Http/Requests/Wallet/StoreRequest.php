<?php

namespace App\Http\Requests\Wallet;

use App\Enums\WalletOperationsTypes;
use App\Http\Requests\ApiRequest;
use App\Models\Recipient\Wallet;
use App\Rules\TronAddressRule;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * @property string $blockchain
 * @property string $external_id
 * @property string $recipient_id
 * @property bool $is_test
 * @property string $operation_type - buy|sell
 * @property string $address_to
 */
class StoreRequest extends ApiRequest
{

    public function __construct()
    {
        parent::__construct();
    }

    public function rules(): array
    {
        return [
            'blockchain' => [
                'required',
                'string',
                Rule::in(Wallet::blockChainTypesNames())
            ],
            'external_id' => [
                'required',
                'string',
                Rule::unique('wallets', 'external_id')
            ],
            'recipient_id' => [
                'required',
                'string',
                Rule::exists('recipients', 'external_id'),
            ],
            'is_test' => [
                'nullable',
                'boolean',
            ],
            'operation_type' => [
                'nullable',
                'string',
                Rule::in(WalletOperationsTypes::namesEn()),
            ],
            'address_to' => array_merge([
                'bail',
                Rule::requiredIf(fn() => $this->isOperationSell()),
                'nullable',
                'string',
                'max:128',
            ], $this->addressFormatRules()),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('address_to')) {
                return;
            }

            $addressTo = trim((string)$this->address_to);

            // The recipient address is accepted only for payout (sell) operations
            if (!$this->isOperationSell() && $addressTo !== '') {
                $validator->errors()->add(
                    'address_to',
                    'The recipient address can only be set for payout (sell) operations'
                );
            }
        });
    }

    /**
     * Recipient address format rules for the selected blockchain
     * @return array
     */
    private function addressFormatRules(): array
    {
        $blockchainType = $this->getBlockchainType();

        if ($blockchainType === Wallet::BLOCKCHAIN_TYPE_TRC_20) {
            return [new TronAddressRule()];
        }

        $regexp = Wallet::blockChainTypesRegexp()[$blockchainType] ?? '';

        return $regexp ? ['regex:' . $regexp] : [];
    }

    /**
     * Operation type for the DB, accepted as text (buy/sell); defaults to incoming (buy)
     */
    public function getOperationType(): int
    {
        $type = WalletOperationsTypes::fromNameEn($this->operation_type)
            ?? WalletOperationsTypes::default();

        return $type->value;
    }

    /**
     * Recipient address, relevant only for payout (sell) operations
     */
    public function getAddressTo(): ?string
    {
        if (!$this->isOperationSell()) {
            return null;
        }

        return trim((string)$this->address_to) ?: null;
    }

    private function isOperationSell(): bool
    {
        return $this->getOperationType() === WalletOperationsTypes::SELL->value;
    }

    /**
     * Blockchain type by its name
     */
    private function getBlockchainType(): ?int
    {
        $types = array_flip(Wallet::blockChainTypesNames());

        return $types[$this->blockchain] ?? null;
    }

    public function messages()
    {
        return [
            'external_id.required' => 'The external ID is required.',
            'external_id.string' => 'The external ID must be a string.',
            'external_id.unique' => 'A wallet for this external_id has already been generated.',
            'recipient_id.required' => 'The recipient ID is required.',
            'recipient_id.integer' => 'The recipient ID must be an integer.',
            'recipient_id.exists' => 'No recipient with this ID exists.',
            'operation_type.in' => 'Invalid operation type (buy = incoming, sell = payout).',
            'address_to.required' => 'The recipient address is required for payout (sell) operations.',
            'address_to.regex' => 'Invalid recipient address.',
            'address_to.max' => 'The recipient address is too long.',
        ];
    }
}
