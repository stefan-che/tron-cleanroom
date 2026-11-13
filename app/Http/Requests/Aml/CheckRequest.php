<?php

namespace App\Http\Requests\Aml;

use App\Http\Requests\ApiRequest;
use App\Models\Aml\AmlAddress;
use App\Rules\AddressRule;
use Illuminate\Validation\Rule;

/**
 * @property string $blockchain
 * @property string $address
 * @property bool $is_test
 */
class CheckRequest extends ApiRequest
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
                Rule::in(AmlAddress::blockChainTypesNames())
            ],
            'address' => [
                'required',
                'string',
                new AddressRule($this->blockchain)
            ],
            'is_test' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages()
    {
        return [
            'blockchain.in' => 'Unsupported blockchain type'
        ];
    }
}
