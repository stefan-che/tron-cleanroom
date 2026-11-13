<?php

namespace App\Http\Requests\Aml;

use App\Http\Requests\ApiRequest;

/**
 * Class ViewRequest
 * @package App\Http\Requests\Event
 *
 * @property string $blockchain
 * @property string $address
 */
class ViewRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules(): array
    {
        return [
            'blockchain' => [
                'nullable',
                'max:64',
            ],
            'address' => [
                'required',
                'max:64',
            ],
        ];
    }
}
