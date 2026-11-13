<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Class ViewRequest
 * @package App\Http\Requests\Event
 *
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
            'address' => [
                'required',
                'max:64',
            ],
        ];
    }
}
