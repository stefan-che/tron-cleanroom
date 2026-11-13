<?php

return [
    'attributes' => [
        'fio' => 'full name of the card owner',
    ],
    'multiply' => 'The :attribute must be a multiple of :multiplier.',
    'custom' => [
        'address' => [
            'required' => 'Field "Wallet Address" is mandatory for filling.',
        ],
        'method' => [
            'required' => 'Field "Method" is mandatory for filling.',
            'exists' => 'The selected method is invalid.',
        ],
        'amount' => [
            'required' => 'Field "Sum" is mandatory for filling out.',
            'numeric' => 'The “sum” field should be a numerical value.',
            'min' => 'The “sum” field should be at least :min.',
            'lt' => 'The “sum” field should be less than 99999.',
        ],

        'file' => [
            'mimes' => 'The Avatar field should be a file with the extension: JPG, JPEG, PNG.',
        ],
    ],
    'recaptcha' => 'Recaptcha is empty',
    'The password cannot contain spaces.' => 'The password cannot contain spaces.',
    'The password must contain at least one letter or number.' => 'The password must contain at least one letter and number.',
];
