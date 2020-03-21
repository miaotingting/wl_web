<?php

namespace App\Http\Controllers\QueryList\Filters;

interface WithFields
{
    /**
     * @return array
     */
    public function getFields(): array;
}
