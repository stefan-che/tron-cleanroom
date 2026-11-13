<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Services\Aml\AmlBitOk\Aml;

class Transfers
{

    public function getTransfers(): array
    {
        $runner = new Aml('/v1/transfers/');
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            'page' => 1,
            'page_size' => 20,
            'total_pages' => 2,
            'total_items' => 40,
            'items' => []
        ];
    }

}
