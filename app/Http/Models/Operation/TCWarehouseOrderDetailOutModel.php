<?php

namespace App\Http\Models\Operation;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeDetailModel;

class TCWarehouseOrderDetailOutModel extends BaseModel
{
    protected $table = 'c_warehouse_order_detail_out';
    public $timestamps = false;
    
}
