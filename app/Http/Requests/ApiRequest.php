<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Class ApiRequest
 * @package App\Http\Requests
 */
abstract class ApiRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
            'success' => false,
        ], 400));
    }

    /**
     * Add parameters to be validated
     *
     * @return array
     */
    public function all($keys = null)
    {
        return array_replace_recursive(
            parent::all($keys),
            $this->route()->parameters()
        );
    }

}
