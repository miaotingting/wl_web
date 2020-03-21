<?php

namespace App\Http\Models\Operation;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\Station;
use App\Http\Models\Admin\Gateway;
use App\Http\Models\Operation\TCWarehouseModel;
use App\Http\Models\Admin\TypeDetailModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Operation\TCWarehouseOrderDetailModel;

class TCWarehouseOrderModel extends BaseModel
{
    protected $table = 'c_warehouse_order';
    
    /*
     * 入库订单列表
     */
    public function getWarehouseOrder($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $where = array();
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $data;
        
    }
    /*
     * 列表整理
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_warehouse_order as wo')
                ->leftJoin('sys_company as c','wo.purchase_company_id','=','c.id');
        if(!empty($where)){
            $sqlObject =$sqlObject->where($where); 
        }
        $count = $sqlObject->count('wo.id');//总条数
        $warehouseOrderData = $sqlObject->orderBy('wo.created_at','DESC')
                ->offset($offset)->limit($pageSize)
                ->get(['wo.id','wo.order_no','wo.operator_batch_no','wo.status','wo.station_name',
                    'wo.gateway_name','wo.batch_num','wo.reality_num','wo.ware_name','wo.created_at',
                    'c.company_name']);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($warehouseOrderData)){
            $result['data'] = [];
            return $result;
        }
        $statusGroup = TypeDetailModel::getDetailsByCode('warehouse_order_status');
        foreach ($warehouseOrderData as $value){
            $value->status = $statusGroup[$value->status];
            $detailCount= TCWarehouseOrderDetailModel::where('order_id',$value->id)
                    ->count('id');
            $value->batchResidue= $detailCount;//批次剩余量
        }
        $result['data'] = $warehouseOrderData;
        return $result; 
    }
    /*
     * 新建入库订单
     */
    public function addWarehouseOrder($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $data['id'] = getUuid();
        $data['order_no'] = getOrderNo('RK');
        $data['operator_batch_no'] = $input['operatorBatchNo'];
        $data['station_id'] = $input['stationId'];
        //$stationName = Station::where('id',$input['station_Id'])->first(['station_name']);
        $data['station_name'] = (new Station)->getStationName($input['stationId']);
        $data['gateway_id'] = $input['gatewayId'];
        $data['gateway_name'] = (new Gateway)->getGatewayName($input['gatewayId']);
        $data['batch_num'] = $input['batchNum'];
        $data['warehouse_id'] = $input['warehouseId'];
        $data['ware_name'] = (new TCWarehouseModel)->getWarehouseName($input['warehouseId']);
        $data['purchase_company_id'] = $input['purchaseCompanyId'];
        if(isset($input['expressNo']) && !empty($input['expressNo'])){
            $data['express_no'] = $input['expressNo'];
        }
        if(isset($input['remark']) && !empty($input['remark'])){
            $data['remark'] = $input['remark'];
        }
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $res = $this->insert($data);
        return $res;
         
    }
    
    /*
     * 编辑入库订单
     */
    public function updateWarehouseOrder($input,$orderId,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        //查询此订单信息
        $orderData = $this->getWarehouseOrderInfo($orderId);
        if($orderData->status == 2){
            throw new CommonException('103159');//此入库单已审核通过，不能修改！
        }
        //修改入库订单时，如果 批次数量==实际入库数量 ，则修改状态为待审核
        if((int)$input['batchNum'] == $orderData->reality_num){
            $data['status'] = 1;
        }elseif((int)$input['batchNum'] < $orderData->reality_num){
            throw new CommonException('103166');//修改失败，批次数量不能小于实际入库数量！
        }
        $data['operator_batch_no'] = $input['operatorBatchNo'];
        $data['station_id'] = $input['stationId'];
        $data['station_name'] = (new Station)->getStationName($input['stationId']);
        $data['gateway_id'] = $input['gatewayId'];
        $data['gateway_name'] = (new Gateway)->getGatewayName($input['gatewayId']);
        $data['batch_num'] = $input['batchNum'];
        $data['warehouse_id'] = $input['warehouseId'];
        $data['ware_name'] = (new TCWarehouseModel)->getWarehouseName($input['warehouseId']);
        $data['purchase_company_id'] = $input['purchaseCompanyId'];
        if(isset($input['expressNo']) && !empty($input['expressNo'])){
            $data['express_no'] = $input['expressNo'];
        }
        if(isset($input['remark']) && !empty($input['remark'])){
            $data['remark'] = $input['remark'];
        }
        $res = $this->where('id',$orderId)->update($data);
        return $res;
         
    }
    /*
     * 获取某入库订单信息
     */
    public function getWarehouseOrderInfo($id){
        $data = $this->where('id',$id)->first(['id','order_no','operator_batch_no','status',
            'station_id','gateway_id','batch_num','warehouse_id','purchase_company_id','express_no',
            'remark']);
        if(empty($data)){
            throw new CommonException('103153');
        }
        return $data;
    }
    /*
     * 获取where条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['orderNo']) && !empty($input['orderNo'])){
            $where[] = ['order_no', 'like', '%'.$input['orderNo'].'%'];//入库单号
        }
        if(isset($input['wareName']) && !empty($input['wareName'])){
            $where[] = ['ware_name', 'like', '%'.$input['wareName'].'%'];//仓库名称
        }
        if(isset($input['stationName']) && !empty($input['stationName'])){
            $where[] = ['station_name', 'like', '%'.$input['stationName'].'%'];//落地名称
        }
        if(isset($input['status'])){
            $where[] = ['status', '=', $input['status']];//入库单状态 
        }
        
        return $where;
    }
    //查询导入数据是否超过此订单总量
    public function estimateNum($orderId,$importNum){
        $orderData = $this->where('id',$orderId)->first(['batch_num','reality_num']);
        if(empty($orderData)){
            throw new CommonException('103153');
        }
        $realityNum = $orderData->reality_num+(int)$importNum;
        if($realityNum >$orderData->batch_num){
            //导入失败，导入的卡片数量超过了此订单批次数量！
            throw new CommonException('103160');
        }
    }
    /*
     * 获取入库订单的实际数量
     */
    public function getOrderInfoById($id){
        $data = $this->where('id',$id)->first();
        if(empty($data)){
            throw new CommonException('103153');
        }
        return $data;
    }
    /*
     * 运营审核
     */
    public function operationCheck($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        //查询此订单信息
        $orderData = $this->getWarehouseOrderInfo($input['orderId']);
        if($orderData->status != 1){
            //此入库单状态不是待审核103163
            throw new CommonException('103163');
        }
        if($input['status'] != 2 && $input['status'] != 3){
            //传入状态有问题
            throw new CommonException('103164');
        }
        $res = $this->where('id',$input['orderId'])->update(['status'=>$input['status']]);
        return $res;
    }
    
    
    
    
}
