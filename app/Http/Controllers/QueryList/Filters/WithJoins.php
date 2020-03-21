<?php

namespace App\Http\Controllers\QueryList\Filters;

interface WithJoins
{
    /**
     * @return array
     */
    public function getJoins(): array;
}
