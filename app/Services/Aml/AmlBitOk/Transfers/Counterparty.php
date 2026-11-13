<?php

namespace App\Services\Aml\AmlBitOk\Transfers;

use App\Services\Aml\AmlBitOk\Aml;

class Counterparty
{
    public function getCounterparty(string $id): array
    {
        $runner = new Aml(sprintf('/v1/transfers/%s/counterparty/', $id));
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [
            'check_status' => 'none',
            'checked_at' => null,
            'entity_category' => null,
            'entity_name' => null,
            'exposure' => null,
        ];
    }

}
