<?php

namespace App\Http\Models\Finance;

use App\Events\MatterEvent;
use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;

use App\Exceptions\CommonException;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Finance\CustomerAccountModel;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Admin\RoleUser;

class TSysCustomerWithdrawModel extends BaseModel
{
    protected $table = 'sys_customer_withdraw';
    public $timestamps = false;

    const TASK_CODE = 'txsp';

    const STATUS_CHECKING = 1;   //审核中
    const STATUS_PAYMENTING = 2; //付款中
    const STATUS_PAYMENTED = 3; //已付款
    const STATUS_DELETE = 4;  //作废
    const STATUS_REJECT = 5;  //驳回

    const PAY_STATUS_SUCCESS = 1; //付款成功
    
    
    /**
     * 修改状态
     * @param $no 单号
     * @param $status 要修改成的状态
     */
    function saveStatus($no,$status) {
        $info = $this->where(['withdraw_code' => $no])->first();
        $info->status = $status;
        $info->save();
    }

    /*
     * 列表
     */
    public function getList($input,$loginUser,$type){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $orWhere = '';
        $where = [];
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
            $input['pageSize'] = 10;
        }
        if($type == 1){
            //我的提现(只有客户有)
            $where[] = ['cw.customer_id','=',$loginUser['customer_id']];//
        }else{
            //提现申请
            if($loginUser['is_owner'] == 1){
                //网来员工（看一级客户的申请单）
                $isSeller = RoleUser::getUserRoleData($loginUser['id'], config('info.role_xiaoshou_id'));
                if($isSeller){
                    //销售只看自己客户的提现申请单
                    $where[] = ['c.account_manager_id','=',$loginUser['id']];
                }
                $where[] = ['c.level','=',1];//
            }else{
                //客户（看自己下级的申请单）
                $where[] = ['c.parent_id','=',$loginUser['customer_id']];//
            }
        }
        //print_r($loginUser);exit;
        //print_r($where);exit;
        $data = $this->getPageData($where,$orWhere,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 获取所有提现申请单详细信息
     */
    public function getPageData($where,$orWhere,$page,$pageSize){
        $str = ['cw.id','cw.withdraw_code','cw.customer_name','cw.create_time','cw.status',
            'cw.amount','cw.remark','c.customer_code'];
        $offset = ($page-1) * $pageSize;
        $object = DB::table('sys_customer_withdraw as cw')
                ->leftJoin('sys_customer as c','cw.customer_id','=','c.id')
                ->where($where);
        if(!empty($orWhere)){
            $object = $object->where(function ($query) use ($orWhere) {
                            $query->where('c.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('c.customer_code','like','%'.$orWhere.'%');
                            });
        }
        $count = $object->count('cw.id');//总条数
        $data = $object->orderBy('cw.create_time','DESC')
                ->offset($offset)->limit($pageSize)
                ->get($str);
        //print_r($data);exit;
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
        $statusGroup = TypeDetailModel::getDetailsByCode('t_sys_customer_withdraw_status');
        foreach ($data as &$value){
            $value->customer_name = '('.$value->customer_code.')'.$value->customer_name;
            $value->status = $statusGroup[$value->status];
        }
        $result['data'] = $data;
        return $result;
        
    }
    /*
     * 创建提现申请单
     */
    public function add($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if($loginUser['is_owner'] == 1){
            throw new CommonException('104102');
        }else{
            $customerData = (new Customer)->getCustomerData($loginUser['customer_id']);
            if($customerData->level > 2 ){
                throw new CommonException('104109');
            }
        }
        $balanceAmount = (new CustomerAccountModel)->getBalanceAmount($loginUser['customer_id'],'customer');
        if($balanceAmount <= 0){
            //账户余额不足，无法申请提现！
            throw new CommonException('104107');
        }
        $data = [];
        $data['id'] = getUuid($this->table);
        $data['withdraw_code'] = getOrderNo('TX');
        $data['customer_id'] = $loginUser['customer_id'];
        $data['customer_name'] = $customerData->customer_name;
        $data['amount'] = $input['amount'];
        $data['status'] = 0;
        $data['transaction_type'] = $input['transactionType'];
        $data['account_bank'] = $input['accountBank'];
        $data['account_name'] = $input['accountName'];
        $data['account_number'] = $input['accountNumber'];
        $data['create_time'] = date('Y-m-d H:i:s',time());
        $data['create_user_id'] = $loginUser['id'];
        $data['create_user_name'] = $loginUser['real_name'];
        if(isset($input['remark']) && !empty($input['remark'])){
            $data['remark'] = $input['remark'];
        }
        $res = $this->insert($data);

        //创建开卡订单给6个角色发消息提醒 
        event(new MatterEvent(self::TASK_CODE,'有新的提现订单', '有新的提现订单待处理', $loginUser));
        //开启流程
        $this->startProcess(self::TASK_CODE, $data['withdraw_code'], $loginUser, $data['customer_name']);
        return $res;
    }
    /*
     * 提现申请单详情(根据单号查询)
     */
    public function getInfo($code){
        $data = $this->where('withdraw_code',$code)
                ->first(['id','customer_id','customer_name','amount','transaction_type','status',
                    'account_bank','account_name','account_number','remark','create_user_id']);
        if(empty($data)){
            throw new CommonException('104104');
        }
        return $data;
    }
    /*
     * 修改提现申请单(根据单号修改)
     */
    public function updateMyWithdraw($id,$input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $data = [];
        $data['amount'] = $input['amount'];
        $data['transaction_type'] = $input['transactionType'];
        $data['account_bank'] = $input['accountBank'];
        $data['account_name'] = $input['accountName'];
        $data['account_number'] = $input['accountNumber'];
        if(isset($input['remark']) && !empty($input['remark'])){
            $data['remark'] = $input['remark'];
        }
        $res = $this->where('withdraw_code',$id)->update($data);
        return $res;
    }
    /*
     * 提现申请操作
     * 一级用户需确认(扣除申请本人及上级客户的账户金额)，驳回
     */
    public function operateWithdraw($input,$loginUser){
        $data = $this->getInfo($input['code']);
        switch ($input['status']){
            case 1 :
                $res = $this->agreeWithdraw($input['code'],$data->amount,$loginUser['customer_id'], $data->customer_id);
                break;
            case 2 :
                $res = $this->where('withdraw_code',$input['code'])->update(['status'=>5]);
                break;
            default :
                throw new CommonException('104105');
           
        }
        return $res;
        
    }
    /*
     * 同意提现申请
     * 更改提现申请状态及扣除相关账户余额
     */
    public function agreeWithdraw($wcode,$amount,$fid,$sid){
        DB::beginTransaction();
        $res = $this->where('withdraw_code',$wcode)->update(['status'=>3]);
        $res1 = DB::table('sys_customer_account')
                ->where('id',$fid)
                ->decrement('balance_amount',$amount);
        $res2 = DB::table('sys_customer_account')
                ->where('id',$sid)
                ->decrement('balance_amount',$amount);
        if($res == 1 && $res1 == 1 && $res2 == 1){
            DB::commit();
        }else{
            DB::rollBack();
            return 0;
        }
        return 1;
    }
    /*
     * 获取where条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['withdrawCode']) && !empty($input['withdrawCode'])){
            $where[] = ['cw.withdraw_code', 'like', '%'.$input['withdrawCode'].'%'];
        }
        if(isset($input['status'])){
            if(empty($input['status'])){
                if($input['status'] == "0"){
                    $where[] = ['cw.status', '=', 0];
                }
            }else{
                $where[] = ['cw.status', '=', $input['status']];
            }
        }
        //起始时间
        if(isset($input['startTime']) && !empty($input['startTime'])){
            $where[] = ['cw.create_time', '>=', $input['startTime']];
        }
        //结束时间
        if(isset($input['endTime']) && !empty($input['endTime'])){
            $where[] = ['cw.create_time', '<=', $input['endTime']];
        }
        //提现金额
        if(isset($input['minAmount']) && !empty($input['minAmount'])){
            $where[] = ['cw.amount', '>=', $input['minAmount']];
        }
        //提现金额
        if(isset($input['maxAmount']) && !empty($input['maxAmount'])){
            $where[] = ['cw.amount', '<=', $input['maxAmount']];
        }
        return $where;
    }
    
}
