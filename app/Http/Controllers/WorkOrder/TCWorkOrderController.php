<?php

namespace App\Http\Controllers\WorkOrder;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Models\WorkOrder\TCWorkOrderModel;

class TCWorkOrderController extends Controller
{
     protected $rules = [
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'max'=>':attribute 长度最多1000个字符',
        ];
    
    /*
     * 新建工单
     * post.api/WorkOrder/addAfterOrder
     */
    public function addAfterOrder(Request $request){
        try{
            $this->rules['contact'] = 'required';
            $this->rules['tel'] = 'required|numeric';
            $this->rules['faultType'] = 'required';
            $this->rules['faultCardNo'] = 'required|max:1000';
            $this->rules['faultDesc'] = 'required|max:1000';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->addAfterOrder($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109001');
            }           
        } catch (Exception $ex) {
            throw new CommonException('109001');
        }
    }
    /*
     * 售后工单列表
     * get.api/WorkOrder/afterOrder
     */
    public function afterOrderList(Request $request)
    {
        try{
            $result = (new TCWorkOrderModel)->afterOrderList($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 查询某个售后工单的详细信息
     * get.WorkOrder/afterOrderShow
     */
    public function afterOrderShow(Request $request)
    {
        try{
            $this->rules['workOrderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->afterOrderShow($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 工单池列表
     * get.api/WorkOrder/workOrderPoolList
     */
    public function workOrderPoolList(Request $request){
        try{
            $result = (new TCWorkOrderModel)->workOrderPoolList($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 工单池工单认领
     * put.api/WorkOrder/workOrderPoolClaim
     */
    public function workOrderPoolClaim(Request $request){
        try{
            $this->rules['workOrderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->workOrderPoolClaim($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109003');
            }
        } catch (Exception $ex) {
            throw new CommonException('109003');
        }
    }
    /*
     * 工单池工单单条分配
     * put.api/WorkOrder/workOrderPoolSingleAllot
     */
    public function workOrderPoolSingleAllot(Request $request){
        try{
            $this->rules['workOrderId'] = 'required';
            $this->rules['handoverUserId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->workOrderPoolSingleAllot($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109004');
            }
        } catch (Exception $ex) {
            throw new CommonException('109004');
        }
    }
    /*
     * 工单池工单随机分配
     * put.api/WorkOrder/workOrderPoolRandomAllot
     */
    public function workOrderPoolRandomAllot(Request $request){
        try{
            $this->rules['handoverUserIdStr'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->workOrderPoolRandomAllot($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109004');
            }
        } catch (Exception $ex) {
            throw new CommonException('109004');
        }
    }
    /*
     * 工单管理
     * get.api/WorkOrder/workOrderManageList
     */
    public function workOrderManageList(Request $request){
        try{
            $result = (new TCWorkOrderModel)->workOrderManageList($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 工单管理：交接
     */
    public function handOverWorkOrder(Request $request){
        try{
            $this->rules['workOrderId'] = 'required';
            $this->rules['handoverUserId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->handOverWorkOrder($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109007');
            }
        } catch (Exception $ex) {
            throw new CommonException('109007');
        }
    }
    /*
     * 工单管理：交接中
     * 当前登录用户是交接人
     * 操作：撤销交接
     */
    public function cancelHandOver(Request $request){
         try{
            $this->rules['workOrderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->cancelHandOver($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109011');
            }
        } catch (Exception $ex) {
            throw new CommonException('109011');
        }
    }
    /*
     * 工单管理：交接中
     * 当前登录用户是被交接人
     * 操作：同意交接或不同意交接
     */
    public function operationHandOver(Request $request){
        try{
            $this->rules['workOrderId'] = 'required';
            $this->rules['operation'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->operationHandOver($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109013');//操作失败
            }
        } catch (Exception $ex) {
            throw new CommonException('109013');
        }
    }
    /*
     * 处理工单(新建交流内容)
     * post.api/WorkOrder/addWorkOrderHandleInfo
     */
    public function addWorkOrderHandleInfo(Request $request){
        try{
            $this->rules['workOrderId'] = 'required';
            $this->rules['content'] = 'required|max:1000';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->addWorkOrderHandleInfo($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109017');//添加失败
            }
        } catch (Exception $ex) {
            throw new CommonException('109017');
        }
    }
    /*
     * 关闭工单
     * put.api/WorkOrder/closeWorkOrder
     */
    public function closeWorkOrder(Request $request){
        try{
            $this->rules['workOrderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->closeWorkOrder($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109018');//关闭工单失败
            }
        } catch (Exception $ex) {
            throw new CommonException('109018');
        }
    }
    /*
     * 删除工单
     * delete.api/WorkOrder/deleteWorkOrder/54349e92a04e565a8ed8cf9bda2d31aa
     */
    public function deleteWorkOrder($id){
        try{
            $result = (new TCWorkOrderModel)->deleteWorkOrder($id,$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109019');//删除工单失败
            }
        } catch (Exception $ex) {
            throw new CommonException('109019');
        }
    }
    
    
    
    
    
    
    
    
    /*
     * 售后总监和售后人员角色的用户列表
     */
    public function getAfterSaleRoleUser(){
        try{
            $result = (new TCWorkOrderModel)->getAfterSaleRoleUser();
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 交接中操作时获取是交接人还是被交接人
     */
    public function getUserOperationType(Request $request){
        try{
            $this->rules['workOrderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWorkOrderModel)->getUserOperationType($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
                'contact'=>'联系人',
                'tel'=>'手机/座机',
                'faultType'=>'故障类型',
                'faultCardNo'=>'故障号码',
                'faultDesc'=>'故障描述',
                'workOrderId'=>'工单ID',
                'handoverUserId'=>'被交接或分配的用户ID',
                'operation'=>'是否同意交接',
                'content'=>'内容',
            ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }
    
   
    

}



