<?php

namespace App\Http\Controllers\QueryList\Filters;

interface WithOrderBy
{
    /**
     * @return array
     */
    public function getOrderBy(): array;
}
