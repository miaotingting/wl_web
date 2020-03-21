<?php

namespace App\Http\Models\Finance;

use App\Http\Models\BaseModel;
use App\Http\Models\Customer\Customer;
use App\Exceptions\CommonException;
use App\Http\Models\Finance\CustomerAccountModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Admin\RoleUser;

class PayApplyModel extends BaseModel
{
    protected $table = 'c_pay_apply';

    /*
     * 获取所有申请单列表
     */
    public function getPayApplys($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(isset($input['search']) && !empty($input['search'])){
            
            $search = json_decode($input['search'],TRUE);
            $where = $this->getWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $where[] = ['status','<>',-1];//只查询未被删除记录
        if($loginUser['is_owner'] == 0){//客户
            if(empty($loginUser['customer_id'])){
                throw new CommonException('102003');//此客户不存在
            }
            $where[] = ['customer_id','=',$loginUser['customer_id']];
        }
        
        $data = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 获取所有申请单详细信息
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $count = $this->where($where)->get(['id'])->count();//总条数
        $payApplyData = $this
                ->where($where)
                ->orderBy('status','ASC')->orderBy('created_at','DESC')
                ->offset($offset)->limit($pageSize)
                ->get(['id','pay_code','customer_name','pay_amount','pay_at','pay_type','status','remark','created_at'])
                ->toArray();
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($payApplyData)){
            $result['data'] = [];
            return $result;
        }
        $payTypeGroup = TypeDetailModel::getDetailsByCode('pay_type');
        $statusGroup = TypeDetailModel::getDetailsByCode('pay_apply_status');
        foreach ($payApplyData as &$value){
            $value['pay_type'] = $payTypeGroup[$value['pay_type']];
            $value['status'] = $statusGroup[$value['status']];
           
        }
        $result['data'] = $payApplyData;
        return $result; 
    }
    
    /*
     * 添加充值申请单
     */
    public function addPayApply($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $customerId = $loginUser['customer_id'];
        if(empty($customerId) ){//如果没有客户ID
            $isNoFinance = $this->isNoFinance($loginUser['id']);
            if(empty($isNoFinance)){//也不是财务
                throw new CommonException('102025');//您无创建充值申请单权限
            }else{//是财务
                if(isset($input['customerId']) == FALSE || empty($input['customerId']) == TRUE){
                    throw new CommonException('102027');//客户是必填项
                }
                $customerId = $input['customerId'];
            }
        }
        
        $customerName = (new Customer)->getCustomerName($customerId);
        $PayApplyModel = new PayApplyModel();
        $PayApplyModel->id = getUuid();
        $PayApplyModel->pay_code = getOrderNo('CZ');
        $PayApplyModel->customer_id = $customerId;
        $PayApplyModel->customer_name = $customerName;
        $PayApplyModel->pay_type = $input['payType'];
        $PayApplyModel->pay_name = $input['payName'];
        $PayApplyModel->pay_at = $input['payTime'];
        $PayApplyModel->pay_amount = $input['payAmount'];
        $PayApplyModel->status = 0;//待审核
        $PayApplyModel->create_user_id = $loginUser['id'];
        $PayApplyModel->create_user_name = $loginUser['real_name'];
        if(isset($input['remark']) && !empty($input['remark'])){
            $PayApplyModel->remark = $input['remark'];
        }
        $res = $PayApplyModel->save();
        return $res;
    }
    /*
     * 编辑充值申请单
     */
    public function updatePayApply($input,$id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        /*if(empty($loginUser['customer_id']) ){
            $isNoFinance = $this->isNoFinance($loginUser['id']);
            if(empty($isNoFinance)){
                throw new CommonException('102026');//您无编辑充值申请单权限
            }
        }*/
        $status = $this->getStatus($id);
        if($status == 1 ){
            throw new CommonException('104003');//此申请单已通过审核，不能修改！
        }
        $data = array();
        $customerId = $loginUser['customer_id'];
        if(empty($customerId) ){//不是客户时（是财务）
            $isNoFinance = $this->isNoFinance($loginUser['id']);
            if(empty($isNoFinance)){//也不是财务
                throw new CommonException('102026');//您无创建充值申请单权限
            }else{//是财务
                if(isset($input['customerId']) == FALSE || empty($input['customerId']) == TRUE){
                    throw new CommonException('102027');//客户是必填项
                }
                $customerId = $input['customerId'];
                $customerName = (new Customer)->getCustomerName($customerId);
                $data['customer_id'] = $customerId;
                $data['customer_name'] = $customerName;
            }
        }
        $data['pay_type'] = $input['payType'];
        $data['pay_name'] = $input['payName'];
        $data['pay_at'] = $input['payTime'];
        $data['pay_amount'] = $input['payAmount'];
        $data['status'] = 0;//待审核
        if(isset($input['remark']) && !empty($input['remark'])){
            $data['remark'] = $input['remark'];
        }
        $res = $this->where('id',$id)->update($data);
        return $res;
    }
    /*
     * 查看此申请单的状态
     */
    public function getStatus($id){
        $data = $this->where('id',$id)->first(['status']);
        if(empty($data)){
            throw new CommonException('104009');
        }
        return $data->status;
    }
    /*
     * 删除申请单
     */
    public function destroyPayApply($id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $status = $this->getStatus($id);
        if($status == 1 ){
            throw new CommonException('104004');//此申请单已通过审核，不能删除！
        }
        if(empty($loginUser['customer_id']) ){
            $isNoFinance = $this->isNoFinance($loginUser['id']);
            if(empty($isNoFinance)){
                throw new CommonException('102028');//您无删除充值申请单权限
            }
        }
        $time = date('Y-m-d H:i:s',time());
        $res = $this->where('id',$id)->update(['status'=>-1,'deleted_at'=>$time]);
        return $res;
    }
    /*
     * 确认/驳回申请单
     */
    public function operatePayApply($input,$id){
        if(!isset($input['status']) || empty($input['status'])){
            throw new CommonException('104008');
        }
        
        $idStatus = $this->getStatus($id);
        if($idStatus == -1){
            throw new CommonException('104011');//此申请单已删除，无法操作!
        }elseif($idStatus == 1){
            throw new CommonException('104012');//此申请单已确认，请勿重复操作!
        }elseif($idStatus == 2){
            throw new CommonException('104013');//此申请单已驳回，请勿重复操作!
        }
        
        $info = $this->where('id',$id)->first(['customer_id','pay_amount']);
        $payAmount = $info->pay_amount;
        
        
        if($input['status'] != 1 &&  $input['status'] != 2){
            throw new CommonException('104010');
        }
        $accountData = CustomerAccountModel::where('id',$info->customer_id)->first(['id']);
        if($input['status'] == 1){
            DB::beginTransaction();
            $res = $this->where('id',$id)->update(['status'=>1]);
            if(empty($accountData)){
                //新建一条记录
                $customerAccountModel = new CustomerAccountModel();
                $customerAccountModel->id = $info->customer_id;
                $customerAccountModel->balance_amount = $payAmount;
                $resCA = $customerAccountModel->save();
            }else{
                //修改金额
                $resCA = CustomerAccountModel::where('id',$info->customer_id)->increment('balance_amount',$payAmount);
                if($resCA > 0){
                    $resCA = TRUE;
                }else{
                    $resCA = FALSE;
                }
            }
            if($res >0 && $resCA == TRUE){
                DB::commit();
                return TRUE;
            }else{
                DB::rollBack();
                return FALSE;
            }
        }else{
            $res = $this->where('id',$id)->update(['status'=>2]);
            return $res;
        }

    }
    
    /*
     * 获取某个申请单详细信息
     */
    public function getInfo($id){
        $data = $this->where('id',$id)
                ->first(['id','pay_code','customer_id','customer_name','pay_type','pay_at','status','pay_amount','remark','pay_name']);
        if(empty($data)){
            throw new CommonException('104009');
        }
        return $data;
    }
    
    function getStatusAttribute($value) {
        return strval($value);
    }
    
    function getPayTypeAttribute($value) {
        return strval($value);
    }
    
   
    
    /*
     * 获取where条件
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['payCode']) && !empty($input['payCode'])){
            $where[] = ['pay_code', 'like', '%'.$input['payCode'].'%'];
        }
        if(isset($input['customerName']) && !empty($input['customerName'])){
            $where[] = ['customer_name', 'like', '%'.$input['customerName'].'%'];
        }
        //充值状态
        if(isset($input['status'])){
            if(empty($input['status'])){
                    if($input['status'] == "0"){
                        $where[] = ['status', '=',0];
                    }
                }else{
                    $where[] = ['status', '=', $input['status']];
                }
        }
        //充值方式
        if(isset($input['payType'])){
            if(empty($input['payType'])){
                    if($input['payType'] == "0"){
                        $where[] = ['pay_type', '=',0];
                    }
                }else{
                    $where[] = ['pay_type', '=', $input['payType']];
                }
        }
        //价格区间最大值
        if(isset($input['maxPrice']) && !empty($input['maxPrice'])){
            $where[] = ['pay_amount', '<=', $input['maxPrice']];
        }
        //价格区间最小值
        if(isset($input['minPrice']) && !empty($input['minPrice'])){
            $where[] = ['pay_amount', '>=', $input['minPrice']];
        }
        
        //下单时间起始时间
        if(isset($input['startTime']) && !empty($input['startTime'])){
            $where[] = ['pay_at', '>=', $input['startTime']];
        }
        //下单时间结束时间
        if(isset($input['endTime']) && !empty($input['endTime'])){
            $where[] = ['pay_at', '<=', $input['endTime']];
        }
        return $where;
    }
    /*
     * 判断是不是财务人员
     */
    public function isNoFinance($userId){
        $financeRoleId = config('info.role_finance_id');
        $data = RoleUser::where(['role_id'=>$financeRoleId,'user_id'=>$userId])->first(['id']);
        return $data;
    }
   
    
}
