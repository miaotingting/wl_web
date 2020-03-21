<?php

namespace App\Http\Controllers\Operation;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\CommonException;
use App\Http\Models\Operation\TCWarehouseModel;
use App\Http\Models\Operation\TCWarehouseOrderModel;
use App\Http\Models\Operation\TCWarehouseOrderDetailModel;

class TCWarehouseOrderController extends Controller
{
    protected $rules = [
        ];
    protected $messages = [
            'required'=>':attribute为必填项',
            'mobile'=>':attribute格式错误',
            'unique'=>'该:attribute已经被注册',
        ];
    
    /*
     * 入库订单列表
     * get.api/Operation/warehouseOrder
     */
    public function index(Request $request)
    {
        try{
            $result = (new TCWarehouseOrderModel)->getWarehouseOrder($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /* 
     * 新建入库订单
     * post.api/Operation/warehouseOrder
     */
    public function store(Request $request)
    {
        try{
            $this->rules['operatorBatchNo']='required|unique:c_warehouse_order,operator_batch_no,id';
            $this->rules['stationId'] = 'required';
            $this->rules['gatewayId'] = 'required';
            $this->rules['batchNum'] = 'required';
            $this->rules['warehouseId'] = 'required';
            $this->rules['purchaseCompanyId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseOrderModel)->addWarehouseOrder($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('103151');
            } 
        } catch (Exception $ex) {
            throw new CommonException('103151');
        }
    }
    /**
     * 修改入库订单
     * put.api/Operation/warehouseOrder/{id}
     */
    public function update(Request $request, $id){
        try{
            $this->rules['operatorBatchNo'] = 'required|unique:c_warehouse_order,operator_batch_no,'.$id;
            $this->rules['stationId'] = 'required';
            $this->rules['gatewayId'] = 'required';
            $this->rules['batchNum'] = 'required';
            $this->rules['warehouseId'] = 'required';
            $this->rules['purchaseCompanyId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseOrderModel)->updateWarehouseOrder($request->all(),$id, $this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('103152');
            }
        } catch (Exception $ex) {
            throw new CommonException('103152');
        }
        
    }
    /**
     * 获取某订单信息
     * get.api/Operation/warehouseOrder/{id}
     */
    public function show($id){
        try{
            $result = (new TCWarehouseOrderModel)->getWarehouseOrderInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 运营审核
     */
    public function operationCheck(Request $request){
        try{
            $this->rules['orderId'] = 'required';
            $this->rules['status'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseOrderModel)->operationCheck($request->all(),$this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('103165');
            }
        } catch (Exception $ex) {
            throw new CommonException('103165');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            'operatorBatchNo'=>'运营商批次号',
            'stationId'=>'落地',
            'gatewayId'=>'网关',
            'batchNum'=>'批次卡片数量',
            'warehouseId'=>'仓库',
            'purchaseCompanyId'=>'采购主体',
            'orderId'=>'入库订单ID',
            'status'=>'审核状态'
        ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }
    

}
