<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;

abstract class Repository
{
    abstract protected function query(): Builder;
}
