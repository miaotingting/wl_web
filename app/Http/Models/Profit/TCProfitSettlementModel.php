<?php

namespace App\Http\Models\Profit;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Admin\RoleUser;

class TCProfitSettlementModel extends BaseModel
{
    protected $table = 'c_profit_settlement';
    
    /*
     * 直销分润明细列表
     */
    public function directSellingIndex($input,$loginUser,$type){
        $where = [];
        $orWhere = '';
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search);
            if(isset($search['customerName']) && !empty($search['customerName'])){
                $orWhere = $search['customerName'];
            }
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 20;
        }
        if($loginUser['is_owner'] == 1){//网来员工
            //网来员工（看一级客户的申请单）
            $isSeller = RoleUser::getUserRoleData($loginUser['id'], config('info.role_xiaoshou_id'));
            if($isSeller){
                //销售只看自己客户的
                $where[] = ['c.account_manager_id','=',$loginUser['id']];
            }
        }else{//客户
            if($type == 'direct'){
                //直销(查看自己的分润)
                $where[] = ['ps.customer_id','=',$loginUser['customer_id']];
            }else{
                //代理(查看自己下级的分润)
                $where[] = ['c.parent_id','=',$loginUser['customer_id']];
            }
            
        }
        $data = $this->getPageData($where,$orWhere,$input['page'],$input['pageSize']);
        return $data;
    }

    /*
     * 获取列表分类数据
     */
    public function getPageData($where,$orWhere,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_profit_settlement as ps')
                ->leftJoin('c_pay_order as po','ps.pay_order_id','=','po.id')
                ->leftJoin('sys_customer as c','ps.customer_id','=','c.id')
                ->where($where);
        if(!empty($orWhere)){
            $sqlObject = $sqlObject->where(function ($query) use ($orWhere) {
                            $query->where('ps.customer_name','like' ,'%'.$orWhere.'%')
                                    ->orWhere('c.customer_code','like','%'.$orWhere.'%');
                            });
        }
        $count = $sqlObject->count('ps.id');//总条数
        $data =  $sqlObject->orderBy('po.end_time','DESC')
            ->offset($offset)->limit($pageSize)
            ->get(['ps.id','ps.customer_name','ps.profit_price','po.trade_no','po.card_no','po.package_name','po.total_fee','po.end_time','c.customer_code']);
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
            return $result;
        }
        foreach($data as &$value){
            $value->customer_name = '('.$value->customer_code.')'.$value->customer_name;
        }
        $result['data'] = $data;
        return $result;
    }

    /*
     * 获取where条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['cardNo']) && !empty($input['cardNo'])){
            $where[] = ['po.card_no', 'like', '%'.$input['cardNo'].'%'];
        }
        if(isset($input['tradeNo']) && !empty($input['tradeNo'])){
            $where[] = ['po.trade_no', 'like', '%'.$input[''].'%'];
        }
        
        //起始时间
        if(isset($input['startTime']) && !empty($input['startTime'])){
            $where[] = ['po.end_time', '>=', $input['startTime']];
        }
        //结束时间
        if(isset($input['endTime']) && !empty($input['endTime'])){
            $where[] = ['po.end_time', '<=', $input['endTime']];
        }
        return $where;
    }
   

}