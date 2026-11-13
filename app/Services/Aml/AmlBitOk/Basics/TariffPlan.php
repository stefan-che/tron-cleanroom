<?php

namespace App\Services\Aml\AmlBitOk\Basics;

use App\Services\Aml\AmlBitOk\Aml;

class TariffPlan
{

    public function getPlan(): array
    {
        $runner = new Aml('/v1/basics/tariff-plan/');
        return $runner->execute();
    }

    private function getLocalData(): array
    {
        return [];
    }

}
