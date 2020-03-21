<?php

namespace App\Http\Models\Profit;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeDetailModel;

class TCProfitDetailModel extends BaseModel
{
    protected $table = 'c_profit_detail';
    
    /*
     * 分润明细列表
     */
    public function profitDetails($profitCode,$input){
        $where = [];
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 20;
        }

        $TCProfitEntity = TCProfitModel::where('profit_code',$profitCode)->first();
        $where[] = ['profit_id','=',$TCProfitEntity->id];
        $offset = ($input['page']-1) * $input['pageSize'];
        $sqlObject = $this->where($where);
        $count = $sqlObject->count('id');//总条数
        $dataList =  $sqlObject->offset($offset)->limit($input['pageSize'])
                        ->get(['id','package_id','package_name','is_sale','sale_price','cost_price']);
        $data = array();
        $pageCount = ceil((int)$count/(int)$input['pageSize']); #计算总页面数    
        $data['count'] = $count;
        $data['page'] = $input['page'];
        $data['pageSize'] = $input['pageSize'];
        $data['pageCount'] = $pageCount;
        $data['data'] = $dataList;
        return $data;
    }
   

}