<?php

namespace App\Http\Models\Matter;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;

/**
 * 节点model
 */
class ProcessFactory {

    private $classArr = [
        'kksp' => 'App\Http\Models\Matter\Handle\KkspHandle',
        'tksp' => 'App\Http\Models\Matter\Handle\TkspHandle',
        'zfsp' => 'App\Http\Models\Matter\Handle\XfzfHandle',
        'txsp' => 'App\Http\Models\Matter\Handle\TxspHandle',
        'frsp' => 'App\Http\Models\Matter\Handle\FrspHandle',
    ];

    function factory($type)
    {
        $className = $this->classArr[$type];
        $obj = false;
        if (class_exists($className)) {
            $obj = new $className;
        }
        return $obj;
    }
}
