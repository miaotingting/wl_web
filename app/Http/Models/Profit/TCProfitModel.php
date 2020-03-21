<?php

namespace App\Http\Models\Profit;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeDetailModel;

class TCProfitModel extends BaseModel
{
    protected $table = 'c_profit';

    const TASK_CODE = 'frsp';

    const PREFIX = 'FR';

    const BEGIN_STATUS = 1;  //未审核
    const CHECKING_STATUS = 2; //审核中
    const CHECKED_STATUS = 3; //审核通过
    const REJECT_STATUS = 4;  //驳回
    const DELETE_STATUS = 5; //删除
    /*
    * 我的分润
    */
    public function myProfit($loginUSer){ 
        if($loginUSer['is_owner'] == 1){
            return [];
        }else{
            $datas = $this->from('c_profit as p')
            ->leftJoin('sys_customer as c','p.customer_id','=','c.id')
            ->where('p.customer_id',$loginUSer['customer_id'])
            ->get(['p.id','p.profit_code','c.customer_code','c.customer_name','p.created_at','p.profit_type','p.status']);
            // if(empty($data)){
            //     return [];
            // }else{
                foreach ($datas as $data) {
                    $typeGroup = TypeDetailModel::getDetailsByCode('t_c_profit_type');
                    $statusGroup = TypeDetailModel::getDetailsByCode('t_c_profit_status');
                    $data->profit_type = $typeGroup[$data['profit_type']];
                    $data->status = $statusGroup[$data['status']];
                }

                return $datas;
            // }
        }
    }

    /**
     * 修改状态
     */
    function saveStatus(string $no, $status) {
        $model = $this->where('profit_code', $no)->first();
        $model->status = $status;
        $model->save();
    }




}