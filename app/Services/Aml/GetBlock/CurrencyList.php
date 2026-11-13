<?php

namespace App\Services\Aml\GetBlock;


class CurrencyList
{

    public function list(): array
    {
        $runner = new GetBlock([
            'id' => '16343012800',
            'method' => 'checkup.currencylist',
        ]);

        return $runner->post();
    }
}
