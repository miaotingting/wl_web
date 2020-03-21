<?php

namespace App\Http\Models\WorkOrder;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Customer\Customer;


class TCWorkOrderDetailModel extends BaseModel
{
    protected $table = 'c_work_order_detail';
    
    /*
     * 获取工单交流详情
     */
    public function getAfterOrdersDetail($id){
        $data = $this->where(['work_order_id'=>$id])->orderBy('create_time', 'ASC')
                ->get(['id','handle_info','create_time','status']);
        if($data->isEmpty()){
            return $data;
        }
        return $data->toArray();
    }
    
    
}
