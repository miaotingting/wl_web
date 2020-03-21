<?php

namespace App\Http\Models\Profit;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Customer\Customer;

class TSysCustomerMonthModel extends BaseModel
{
    protected $table = 'sys_customer_month';
    
    // 获取客户续费月份列表
    public function getCustomerMonthIndex($request, $search)
    {
        $where = array();
        if(!empty($search)){
            if(isset($search['customerCode']) && !empty($search['customerCode'])){
                $where[] = ['customer_code', 'like', '%'.$search['customerCode'].'%'];
            }
            if(isset($search['customerName']) && !empty($search['customerName'])){
                $where[] = ['customer_name', 'like', '%'.$search['customerName'].'%'];
            }
        }
        
        if($request->has('page') && !empty($request->get('page'))){
            $page = $request->get('page');
        }else{
            $page = 1;
        }

        if($request->has('pageSize') && !empty($request->get('pageSize'))){
            $pageSize = $request->get('pageSize');
        }else{
            $pageSize = 20;
        }
        $sql = $this->where($where);
        //总条数
        $count = $sql->count('id');
        $pageCount = ceil($count/$pageSize); #计算总页面数
        $list = $sql->orderBy('customer_code')->offset(($page-1) * $pageSize)->limit($pageSize)->get();
        $result = array();
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['data'] = $list;
        return $result; 
    }

    // 添加（设置）客户最小续费月数
    public function add($request,$user)
    {
        $TSysCustomerMonthEntity = TSysCustomerMonthModel::where('customer_id',$request->post('customerId'))->first();
        if(!empty($TSysCustomerMonthEntity)){
            throw new CommonException('111005');//客户已设置续费月份，不允许重复设置！
        }
        $TSysCustomerEntity = Customer::where('id',$request->post('customerId'))->first();
        if(empty($TSysCustomerEntity)){
            throw new CommonException('111003');//续费客户不存在
        }
        if($TSysCustomerEntity->level != 1){
            throw new CommonException('111004');//客户不是一级客户
        }
        $TSysCustomerMonthEntity = new TSysCustomerMonthModel();
        $TSysCustomerMonthEntity->id = getUuid("customerMonth");
        $TSysCustomerMonthEntity->customer_id = $request->post('customerId');
        $TSysCustomerMonthEntity->customer_code = $TSysCustomerEntity->customer_code;
        $TSysCustomerMonthEntity->customer_name = $TSysCustomerEntity->customer_name;
        $TSysCustomerMonthEntity->min_month = $request->post('minMonth');
        $TSysCustomerMonthEntity->create_user_id = $user['id'];
        $TSysCustomerMonthEntity->create_user_name = $user['real_name'];
        $re = $TSysCustomerMonthEntity->save();
        return $re ? ["Success"=>true] : ["Success"=>false];
    }

    // 查看客户续费月份信息
    public function customerMonthInfo($id){
        $TSysCustomerMonthEntity = TSysCustomerMonthModel::find($id);
        if(empty($TSysCustomerMonthEntity)){
            return ['续费月份信息不存在！'];
        }else{
            return $TSysCustomerMonthEntity;
        }
    }

    // 修改客户续费月份
    public function updateCustomerMonth($minMonth,$id){
        if($minMonth < 1 || $minMonth > 120){
            throw new CommonException('111007');//设置月份值不符合规则
        }
        $TSysCustomerMonthEntity = TSysCustomerMonthModel::where('id',$id)->update(['min_month'=>$minMonth]);
        if($TSysCustomerMonthEntity){
            return ['Success'=>true];
        }else{
            throw new CommonException('111008');//修改月份失败
        }   
    }

    
}