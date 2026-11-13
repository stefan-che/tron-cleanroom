<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

abstract class Controller
{

    public function returnError(string $message, $statusCode = Response::HTTP_NOT_FOUND): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $message
            ], $statusCode)
        );
    }
}
