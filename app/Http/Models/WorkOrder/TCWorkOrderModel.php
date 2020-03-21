<?php

namespace App\Http\Models\WorkOrder;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\WorkOrder\TCWorkOrderDetailModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Admin\User;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Admin\NoticeModel;
use App\Http\Models\Admin\TypeDetailModel;


class TCWorkOrderModel extends BaseModel
{
    protected $table = 'c_work_order';
    
    /*
     * 新建工单
     */
    public function addAfterOrder($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if($loginUser['is_owner'] == 1){
            throw new CommonException('109014');//你不是客户，不能创建售后工单
        }
        $workOrderId = getUuid();
        $resWorkOrder = $this->addWorkOrder($workOrderId, $input,$loginUser);
        $noticeData['title'] = $loginUser['real_name'].'客户提交了一个售后工单';
        $noticeData['content'] = $loginUser['real_name'].'客户提交了一个售后工单';
        $noticeData['type'] = 0;
        $noticeData['level'] = 1;
        $noticeData['impowerIds'] = config('info.role_shouhou_id').','.config('info.role_shzj_id');
        $resNotice = (new NoticeModel)->addNoticeCaution($noticeData, $loginUser);
        return $resWorkOrder;
    }
    /*
     * 新建工单表
     */
    public function addWorkOrder($workOrderId,$input,$loginUser){
        $data = array();
        $data['id'] = $workOrderId;
        $data['work_no'] = getOrderNo('GD');
        $data['status'] = 2;//待处理
        $data['contact'] = $input['contact'];
        $data['tel'] = $input['tel'];
        if(isset($input['qqWechat']) && !empty($input['qqWechat'])){
            $data['qq_wechat'] = $input['qqWechat'];
        }
        $data['fault_type'] = $input['faultType'];
        $data['fault_card_no'] = strip_tags($input['faultCardNo']);
        $data['fault_desc'] = strip_tags($input['faultDesc']);
        $data['customer_id'] = $loginUser['customer_id'];
        $customerName =  (new Customer)->getCustomerName($loginUser['customer_id']);
        $data['customer_name'] = $customerName;
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $data['updated_at'] = date('Y-m-d H:i:s',time());
        $res = TCWorkOrderModel::insert($data);
        return $res;
    }
    /*
     * 新建工单详细表
     */
    public function addWorkOrderDetail($workOrderId,$content,$type){
        $data = array();
        $data['id'] = getUuid();
        $data['work_order_id'] = $workOrderId;
        $data['handle_info'] = strip_tags($content);
        $data['create_time'] = date('Y-m-d H:i:s',time());
        $data['status'] = $type;
        $res = TCWorkOrderDetailModel::insert($data);
        return $res;
    }
    /*
     * 售后工单列表
     */
    public function afterOrderList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $str = ['wo.id','wo.work_no','wo.status','wo.fault_desc','wo.contact','wo.tel',
            'wo.fault_type','wo.fault_card_no','wo.created_at','wo.end_order_time'];
        $where[] = ['wo.customer_id','=',$loginUser['customer_id']];
        $data = $this->getPageData($input,$loginUser,$where,$str);
        
        return $data;
        
    }
    /*
     * 工单池列表
     */
    public function workOrderPoolList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $str = ['wo.id','wo.work_no','wo.status','wo.fault_desc','wo.contact','wo.tel','wo.fault_type',
                'wo.fault_card_no','wo.created_at','wo.end_order_time','wo.action_status',
                'wo.handover_user_name','wo.action_user_name','wo.customer_name','wo.handover_status',
                'customer.customer_code'];
        $where = array();
        $data = $this->getPageData($input,$loginUser,$where,$str);
        return $data;
        
    }
    /*
     * 工单管理
     */
    public function workOrderManageList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $str = ['wo.id','wo.work_no','wo.status','wo.fault_desc','wo.contact','wo.tel','wo.fault_type',
                'wo.fault_card_no','wo.created_at','wo.end_order_time','wo.action_status',
                'wo.handover_user_name','wo.action_user_name','wo.customer_name','wo.handover_status',
                'customer.customer_code'];
        $where = array();
        $data = $this->getPageData($input,$loginUser,$where,$str,1);
        return $data;
    }
    /*
     * 获取所有分页数据
     */
    public function getPageData($input,$loginUser,$where,$str,$orWhere=0){
        
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $orWhereCustomer = '';
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search,$where);
            if(isset($search['customer']) && !empty($search['customer'])){
                $orWhereCustomer = $search['customer'];
            }
        }
        $offset = ($input['page']-1) * $input['pageSize'];
        $sqlObject = DB::table('c_work_order as wo')
                ->leftJoin('sys_customer as customer','wo.customer_id','=','customer.id');
        
        if($orWhere == 1){
            $sqlObject = $sqlObject->where(function ($query) use ($loginUser) {
                        $query->orWhere(function ($query1) use($loginUser) {
                            $query1->orWhere(function ($query2) use($loginUser){
                                $query2->orWhere('wo.handover_user_id','=' ,$loginUser['id'])
                                  ->orWhere('wo.action_user_id','=',$loginUser['id']);
                            });
                            $query1->where('wo.handover_status','=',1);
                        })
                        ->orWhere(function ($query1) use($loginUser) {
                            $query1->orWhere(function ($query2) use($loginUser){
                                $query2->orWhere('wo.handover_status','=',2)
                                       ->orWhere('wo.handover_status','=',0);
                            });
                            $query1->where('wo.handover_user_id','=',$loginUser['id']);
                        });
                 });
            
        }
        if(!empty($orWhereCustomer)){
            $sqlObject = $sqlObject->where(function ($query) use ($orWhereCustomer) {
                            $query->where('customer.customer_name','like' ,'%'.$orWhereCustomer.'%')
                                  ->orWhere('customer.customer_code','like','%'.$orWhereCustomer.'%');
                            });
        }
        if(!empty($where)){
            $sqlObject = $sqlObject->where($where);
        } 
        $count = $sqlObject->count('wo.id');//总条数
        $data = $sqlObject->orderBy('wo.status','DESC')->orderBy('wo.created_at','DESC')
                ->offset($offset)->limit($input['pageSize'])
                ->get($str);
        
        $result = array();
        $pageCount = ceil($count/$input['pageSize']); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$input['page'];
        $result['pageSize'] = $input['pageSize'];
        $result['pageCount'] = $pageCount;
        
        if($data->isEmpty()){
            $result['data'] = [];
        }else{
            $statusGroup = TypeDetailModel::getDetailsByCode('work_order_status');
            $faultTypeGroup = TypeDetailModel::getDetailsByCode('work_order_fault_type');
            $actionStatusGroup = TypeDetailModel::getDetailsByCode('work_order_action_status');
            $handoverStatusGroup = TypeDetailModel::getDetailsByCode('work_order_handover_status');
            
            foreach($data as $value){
                $value->status = $statusGroup[$value->status];
                
                $value->fault_type = $faultTypeGroup[$value->fault_type];
                if(isset($value->action_status)){
                    $value->action_status = $actionStatusGroup[$value->action_status];
                }
                if(isset($value->handover_status)){
                    $value->handover_status = $handoverStatusGroup[$value->handover_status];
                }
                if(isset($value->customer_code)){
                    $value->customer_name = '('.$value->customer_code.')'.$value->customer_name;
                }
                
            }
            $result['data'] = $data;
        }
        return $result;
    }
    /*
     * 获取查询的where条件
     */
    public function getSearchWhere($input,$where){
        //$where = array();
        if(isset($input['workNo']) && !empty($input['workNo'])){//单号
            $where[] = ['wo.work_no', 'like', '%'.$input['workNo'].'%'];
        }
        if(isset($input['workOrderStatus'])){//工单状态
            if(!empty($input['workOrderStatus'])){
                $where[] = ['wo.status', '=', $input['workOrderStatus']];
            }elseif($input['workOrderStatus'] == '0'){
                $where[] = ['wo.status', '=', 0];
            }
        }
        if(isset($input['faultType'])){//故障类型
            if(!empty($input['faultType'])){
                $where[] = ['wo.fault_type', '=', $input['faultType']];
            }elseif($input['faultType'] == '0'){
                $where[] = ['wo.fault_type', '=',0];
            }
        }
        if(isset($input['createTimeStart']) && !empty($input['createTimeStart'])){//工单提交时间开始时间
            $where[] = ['wo.created_at', '>=', $input['createTimeStart']];
        }
        if(isset($input['createTimeEnd']) && !empty($input['createTimeEnd'])){//工单提交时间结束时间
            $where[] = ['wo.created_at', '<=', $input['createTimeEnd']];
        }
        if(isset($input['actionStatus'])){//认领状态
            if(!empty($input['actionStatus'])){
                $where[] = ['wo.action_status', '=', $input['actionStatus']];
            }elseif($input['actionStatus'] == '0'){
                $where[] = ['wo.action_status','=',0];
            }
        }
        return $where;
    }
    /*
     * 查询某个售后工单的详细信息
     */
    public function afterOrderShow($input,$loginUser){
        $result = array();
        $workOrderData = $this->where(['id'=>$input['workOrderId']])
                ->first(['id','fault_desc','status','work_no','created_at','customer_name',
                    'end_order_time','delete_type']);
        if(empty($workOrderData)){
            throw new CommonException('109002');
        }
        if($workOrderData->status == 0){
            //工单已关闭(工单时长：工单结束时间-创建时间)
            $createTime = strtotime($workOrderData->created_at);
            $endOrderTimes = strtotime($workOrderData->end_order_time);
            $subtractTime = $endOrderTimes-$createTime;
            $subtractTimeMinute = floor(($subtractTime%3600)/60);
            $subtractTimeHour = floor($subtractTime/3600);
        }else{
            //工单未关闭(工单时长：当前时间-创建时间)
            $createTime = strtotime($workOrderData->created_at);
            $subtractTime = time()-$createTime;
            $subtractTimeMinute = floor(($subtractTime%3600)/60);
            $subtractTimeHour = floor($subtractTime/3600);
            unset($workOrderData->end_order_time);
            unset($workOrderData->delete_type);
        }
        
        $result = $workOrderData->toArray();
        $result['status'] = Package::getTypeDetail('work_order_status',$result['status']);
        
        $duration = $subtractTimeHour.'小时'.$subtractTimeMinute.'分钟';
        $result['duration'] =$duration;//实际处理时长
        $detailData = (new TCWorkOrderDetailModel)->getAfterOrdersDetail($input['workOrderId']);
        $result['detailData'] = $detailData;
        return $result;
    }
    /*
     * 工单池工单认领
     */
    public function workOrderPoolClaim($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $workOrderData = $this->isWorkOrderExist($input['workOrderId']);
        if($workOrderData->status == 1){
            throw new CommonException('109015');//该工单已被受理或关闭，请勿重复操作
        }
        $this->isNoAfterSaleRole($loginUser['id']);//查询认领的人员是否是售后角色
        $where = ['id'=>$input['workOrderId']];
        $res = $this->updatePendingWorkOrder($where, $loginUser['id'],$loginUser['real_name']);
        if($res == 1){
            return TRUE;
        }
        return FALSE;
    }
    
    
    /*
     * 工单池工单单条分配
     * 只有售后总监才能分配工单
     */
    public function workOrderPoolSingleAllot($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $this->isWorkOrderExist($input['workOrderId']);
        $this->isNoAfterSaleMajordomo($loginUser['id']);//查询当前登录用户是否是售后总监
        $where = ['id'=>$input['workOrderId']];
        $res = $this->updatePendingWorkOrder($where,$input['handoverUserId']);
        if($res == 1){
            return TRUE;
        }
        return FALSE;
    }
    /*
     * 工单池工单随机分配
     * 只有售后总监才能随机分配工单
     */
    public function workOrderPoolRandomAllot($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $this->isNoAfterSaleMajordomo($loginUser['id']);//查询当前登录用户是否是售后总监
        $pendingOrderData = $this->where('status',2)->get(['id']);//待处理的工单
        if(count($pendingOrderData) <= 0){
            throw new CommonException('109005');//无待处理的工单
        }
        //传过来的等待随机分配的售后人员ID
        $handoverUserIdArr = explode(',',trim($input['handoverUserIdStr'],','));
        if(count($handoverUserIdArr) == 1){//如果只有一个人则全部分给他
            $where = ['status'=>2];
            DB::beginTransaction();
            $res = $this->updatePendingWorkOrder($where,$handoverUserIdArr[0]);
            if($res == count($pendingOrderData)){
                DB::commit();
                return TRUE;
            }else{
                DB::rollBack();
                return FALSE;
            }
        }else{//随机分配
            $res = TRUE;
            DB::beginTransaction();
            foreach($pendingOrderData as $value){
                //随机取得选择的售后人员的ID
                $userData = $this->randomAllotUserId($handoverUserIdArr);
                $where = ['id'=>$value->id];
                $resChild = $this->updatePendingWorkOrder($where,$userData);
                if($resChild != 1){
                    $res = FALSE;
                    break;
                }
            }
            if($res){
                DB::commit();
            }else{
                DB::rollBack();
            }
            return $res;
        }
        
    }
    /*
     * 工单管理中交接
     */
    public function handOverWorkOrder($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
       
        $workData = $this->isWorkOrderExist($input['workOrderId']);//查询此工单是否存在
        if($workData->handover_status == 1){//交接中的工单
            throw new CommonException('109021');//此工单正在交接中，不允许在交接
        }
        $handoverUserData = (new User)->getAdminInfo($input['handoverUserId']);//查询交接给的售后人员是否存在
        $this->isNoAfterSaleRole($input['handoverUserId']);//查询交接给的人员是否是售后角色
        if($workData->handover_user_id == $input['handoverUserId']){
            throw new CommonException('109020');//本人的工单不可以在交接给本人
        }
        
        $updateData['handover_status'] = 1;//交接中
       
        $updateData['action_user_id'] = $loginUser['id'];
        $updateData['action_user_name'] = $loginUser['real_name'];
        $updateData['handover_user_id'] = $input['handoverUserId'];
        $updateData['handover_user_name'] = $handoverUserData->real_name;
        $where = ['id'=>$input['workOrderId']];
        $res = $this->where($where)->update($updateData);
        if($res == 1){
            return TRUE;
        }
        return FALSE;
    }
    /*
     * 工单管理：交接中
     * 当前登录用户是交接人
     * 操作：撤销交接
     */
    public function cancelHandOver($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $this->isWorkOrderExist($input['workOrderId']);//查询此工单是否存在
        //判断登录用户是否是此工单交接人
        $this->isNoWorkOrderActionUser($input['workOrderId'],$loginUser['id'],1);
        //作撤销交接操作
        $updateData['handover_status'] = 0;//未交接
        $updateData['handover_user_id'] = $loginUser['id'];//认领人换成自己
        $updateData['handover_user_name'] = $loginUser['real_name'];
        $updateData['action_user_id'] = '';//发起交接人为空
        $updateData['action_user_name'] = '';
        $where = ['id'=>$input['workOrderId']];
        $res = $this->where($where)->update($updateData);
        if($res == 1){
            return TRUE;
        }
        return FALSE;
    }
    /*
     * 工单管理：交接中
     * 当前登录用户是被交接人
     * 操作：同意交接或不同意交接
     */
    public function operationHandOver($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $workOrderData = $this->isWorkOrderExist($input['workOrderId']);//查询此工单是否存在
        //判断登录用户是否是此工单被交接人
        $this->isNoWorkOrderActionUser($input['workOrderId'],$loginUser['id'],2);
        //作同意/驳回交接操作
        if($input['operation'] == 1){
            //同意
            $updateData['handover_status'] = 2;//交接完成
        }elseif($input['operation'] == 2){
            //驳回
            $updateData['handover_status'] = 0;//未交接
            $updateData['handover_user_id'] = $workOrderData->action_user_id;//认领人换成交接人
            $updateData['handover_user_name'] = $workOrderData->action_user_name;
            $updateData['action_user_id'] = '';//发起交接人为空
            $updateData['action_user_name'] = '';
        }else{
            throw new CommonException('109012');//操作失败，交接的操作状态有误
        }
        $where = ['id'=>$input['workOrderId']];
        $res = $this->where($where)->update($updateData);
        if($res == 1){
            return TRUE;
        }
        return FALSE;
    }
    /*
     * 处理工单(新建交流内容)
     */
    public function addWorkOrderHandleInfo($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $this->isWorkOrderExist($input['workOrderId']);//查询此工单是否存在
        
        if($loginUser['is_owner'] == 1){
            $this->isNoAfterSaleRole($loginUser['id']);//查询交流的人员是否是售后角色
            //判断登录用户是否是此工单认领人或被分配人
            $this->isNoWorkOrderActionUser($input['workOrderId'],$loginUser['id'],2);
            $type = 1;//售后工程师
        }else{
            //判断工单是否是此登录用户的工单
            $this->isNoCustomerWorkOrder($input['workOrderId'],$loginUser['customer_id']);
            $type = 2;//客户
        }
        $res = $this->addWorkOrderDetail($input['workOrderId'], $input['content'],$type);
        return $res;
    }
    /*
     * 关闭工单
     */
    public function closeWorkOrder($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        
        $workData = $this->isWorkOrderExist($input['workOrderId']);//查询此工单是否存在
        
        if($loginUser['is_owner'] == 1){
            $this->isNoAfterSaleRole($loginUser['id']);//查询登录人员是否是售后角色
            //判断登录用户是否是此工单认领人或被分配人
            $this->isNoWorkOrderActionUser($input['workOrderId'],$loginUser['id'],2);
            $type = 1;//售后工程师
        }else{
            //判断工单是否是此登录用户的工单
            $this->isNoCustomerWorkOrder($input['workOrderId'],$loginUser['customer_id']);
            $type = 2;//客户
        }
        $updateData['status'] = 0;//工单已关闭
        $updateData['action_status'] = 2;//已关闭
        $updateData['delete_type'] = $type;
        $updateData['end_order_time'] = date('Y-m-d H:i:s',time());
        $res = $this->where('id',$input['workOrderId'])->update($updateData);
        if($res == 1){
            return TRUE;
        }
        return FALSE;
    }
    /*
     * 删除工单
     */
    public function deleteWorkOrder($id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $data = $this->where('id',$id)->first(['id']);//查询此工单是否存在
        if(empty($data)){
            throw new CommonException('109002');
        }
        if($loginUser['is_owner'] == 1){
            throw new CommonException('109010');//操作失败，你没有操作此工单权限
        }
        //判断工单是否是此登录用户的工单
        $this->isNoCustomerWorkOrder($id,$loginUser['customer_id']);
        $res = $this->where('id',$id)->delete();
        if($res == 1){
            return TRUE;
        }
        return FALSE;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    /*
     * 售后总监和售后人员角色的用户列表
     */
    public function getAfterSaleRoleUser(){
        
        $afterSaleZJId = config('info.role_shzj_id');
        $afterSaleId = config('info.role_shouhou_id');
        $data = RoleUser::leftJoin('sys_user as user','sys_role_user.user_id','user.id')
                ->orWhere(function ($query) use ($afterSaleZJId,$afterSaleId) {
                            $query->where('sys_role_user.role_id','=' ,$afterSaleZJId)
                                  ->orWhere('sys_role_user.role_id','=',$afterSaleId);
                        })
                ->where('user.is_delete',0)
                ->get(['sys_role_user.user_id','user.real_name']);           
        return $data;
    }
    /*
     * 交接中操作时获取是交接人还是被交接人
     */
    public function getUserOperationType($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $workOrderData = $this->isWorkOrderExist($input['workOrderId']);
        $result = array();
        if($workOrderData->action_user_id == $loginUser['id']){
            $result['type'] = 1;//发起交接的人
        }elseif($workOrderData->handover_user_id == $loginUser['id']){
            $result['type'] = 2;//被交接的人
        }else{
            throw new CommonException('109009');
        }
        return $result;
    }
    
    
    
    /*
     * 查询此条申请单是否存在
     */
    public function isWorkOrderExist($id){
        $data = $this->where('id',$id)
                ->first(['id','status','handover_user_id','handover_user_name','action_user_id',
                    'action_user_name','action_status','created_at','handover_status']);
        if(empty($data)){
            throw new CommonException('109002');
        }
        if($data->status == 0){
            throw new CommonException('109008');//操作失败，工单已关闭
        }
        return $data;
    }
    /*
     * 更新待认领的工单
     */
    public function updatePendingWorkOrder($where,$handoverUserId,$handoverUserName=null){
        $updateData['status'] = 1;//已受理
        $updateData['action_status'] = 1;//已分配已认领
        $updateData['handover_user_id'] = $handoverUserId;
        if(empty($handoverUserName)){
            $handoverUserData = (new User)->getAdminInfo($handoverUserId);
            $updateData['handover_user_name'] = $handoverUserData->real_name;
        }else{
            $updateData['handover_user_name'] = $handoverUserName;
        }
        $res = $this->where($where)->update($updateData);
        return $res;
    }
    /*
     * 获取随机分配的售后人员的ID
     */
    public function randomAllotUserId($userIdArr){
        $countUser = count($userIdArr)-1;
        $randNum = rand(0,$countUser);
        return $userIdArr[$randNum];
    }
    /*
     * 查询此用户是否是售后人员
     */
    public function isNoAfterSaleRole($userId){
        $afterSaleZJId = config('info.role_shzj_id');
        $afterSaleId = config('info.role_shouhou_id');
        $data = RoleUser::orWhere(function ($query) use ($afterSaleZJId,$afterSaleId) {
                            $query->where('role_id','=' ,$afterSaleZJId)
                                  ->orWhere('role_id','=',$afterSaleId);
                        })
                        ->where('user_id',$userId)
                        ->first(['id']);
        if(empty($data)){
            throw new CommonException('109006');//操作失败，此用户不是售后人员
        }
        return $data;
    }
    /*
     * 查询此用户是否是售后总监
     */
    public function isNoAfterSaleMajordomo($userId){
        $afterSaleZJId = config('info.role_shzj_id');
        $data = RoleUser::where(['user_id'=>$userId,'role_id'=>$afterSaleZJId])->first(['id']);
        if(empty($data)){
            throw new CommonException('109016');//你不是售后总监，无此操作权限
        }
        return $data;
    }
    /*
     * 判断登录用户是否是此工单交接人
     */
    public function isNoWorkOrderActionUser($workOrderId,$userId,$type){
        if($type == 1){//交接人
            $where = ['id'=>$workOrderId,'action_user_id'=>$userId];
        }else{//被交接人
            $where = ['id'=>$workOrderId,'handover_user_id'=>$userId];
        }
        $data = $this->where($where)->first(['id']);
        
        if(empty($data)){
            throw new CommonException('109010');//操作失败，你没有操作此工单权限
        }
        return $data;
    }
    /*
     * 判断此工单是否是此客户的
     */
    public function isNoCustomerWorkOrder($workOrderId,$customerId){
        $data = $this->where(['id'=>$workOrderId,'customer_id'=>$customerId])
                ->first(['id']);
        if(empty($data)){
            throw new CommonException('109010');//操作失败，你没有操作此工单权限
        }
        return $data;
        
    }
    
    
    
    
    
    
    
    
    
    
}
