<?php

namespace App\Services\Aml\GetBlock;


class Ping
{

    public function ping(): array
    {
        $runner = new GetBlock([
            'id' => time(),
            'method' => 'checkup.ping',
        ]);

        return $runner->post();
    }
}
