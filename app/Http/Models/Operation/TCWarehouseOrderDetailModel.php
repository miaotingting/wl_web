<?php

namespace App\Http\Models\Operation;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Operation\TCWarehouseOrderModel;
use App\Http\Models\Operation\TCWarehouseModel;
use App\Http\Models\Admin\TypeDetailModel;


class TCWarehouseOrderDetailModel extends BaseModel
{
    protected $table = 'c_warehouse_order_detail';
    public $timestamps = false;
    
    /*
     * 导入
     */
    public function saveEntity($cards,$orderId,$user,$filePath)
    {
        // 查询订单信息
        $orderData = TCWarehouseOrderModel::where('id',$orderId)->first();
        if(empty($orderData)){
            throw new CommonException('106005');
        }
        // 没验证验证落地和网关
        // 验证是否存在重复卡片
        $iccidList = array_column($cards, 'iccid');
        $iccidCount = count(array_unique($iccidList));
        if(count($iccidList) != $iccidCount){
            throw new CommonException('106007');
        }
        // 验证仓库中是否已经存在卡片
        $checkIccidOut = $this->whereIn('iccid', $iccidList)->get(['iccid'])->toArray();
        if(!empty($checkIccidOut)){
            //导入失败，导入的部分卡片已存在于仓库中！
            throw new CommonException('103161');
        }
        DB::beginTransaction();
        try{
            $reultDetail = $this->saveOrderDetail($cards,$orderId);
            $resOrder = $this->updateOrder($orderId,$orderData,$reultDetail['dataLength']);
            $resWarehouse = $this->updateWarehouse($orderData->warehouse_id,$reultDetail['dataLength']);
            if($reultDetail['res'] == TRUE && $resOrder>0 && $resWarehouse>0){
                DB::commit();
                unlink($filePath);//清理缓存文件
                return $msg = "批量导入成功,共导入{$reultDetail['dataLength']}条数据";
            }else{
                DB::rollback();
                throw new CommonException('300005');
            }
        }catch(Exception $e){
            DB::rollback();
            throw new CommonException('300005');
        }
    }
    /*
     * 导入时存入详情表数据
     */
    public function saveOrderDetail($cards,$orderId){
        // 保存卡片到入库订单详情表
        $insertData = [];
        foreach($cards as $value){
            $isPrintCardNo = $value['isPrintCardNo'] == '是'?1:0;
            $insertData[] = [
                'id' => getUuid(),
                'order_id' => $orderId,
                'card_no' => $value['cardNo'],
                'iccid' => $value['iccid'],
                'imsi' => $value['imsi'],
                'card_maker' => $value['cardMaker'],
                'slice_type' => $value['sliceType'],
                'physical_type' => $value['physicalType'],
                'board_color' => $value['boardColor'],
                'network_standard' => $value['networkStandard'],
                'is_print_card_no' => $isPrintCardNo,
                'create_time' => date('Y-m-d H:i:s',time()),
            ];
        }
        //超过500张设置分配添加
        $max_thread_num = 500;
        $dataLength = count($insertData);
        if($dataLength <= $max_thread_num){
            $resDetail = TCWarehouseOrderDetailModel::insert($insertData);
        }else{
            $threadLen= ceil($dataLength/$max_thread_num); 
            for($i = 0; $i <= $threadLen; $i++){
                $limitThread = $i*$max_thread_num;
                $perThread = array_slice($insertData,$limitThread,$max_thread_num);
                $resDetail = TCWarehouseOrderDetailModel::insert($perThread);
            }
        }
        $result = [];
        $result['res'] = $resDetail;
        $result['dataLength'] = $dataLength;
        return $result;
    }
    
    /*
     * 导入时更新入库订单表
     */
    public function updateOrder($orderId,$orderData,$dataLength){
        $updateOrderData = array();
        $realityNum = $orderData->reality_num + $dataLength;//导入后的实际入库数量
        $updateOrderData['reality_num']=$realityNum;
        if($realityNum ==  $orderData->batch_num){
            $updateOrderData['status'] = 1;//待审核
        }
        $resOrder = TCWarehouseOrderModel::where('id',$orderId)->update($updateOrderData);
        return $resOrder;
    }
    /*
     * 导入时更新仓库表
     */
    public function updateWarehouse($warehouseId,$dataLength){
        $warehouseData = TCWarehouseModel::where('id',$warehouseId)->first(['card_total_num',
            'card_stock_num']);
        if(empty($warehouseData)){
            throw new CommonException('103102');
        }
        $updateData = [];
        $updateData['card_total_num'] = $warehouseData->card_total_num+(int)$dataLength;
        $updateData['card_stock_num'] = $warehouseData->card_stock_num+(int)$dataLength;
        $res = TCWarehouseModel::where('id',$warehouseId)->update($updateData);
        return $res;
    }
    /*
     * 入库订单详情表--数据初始化操作
     */
    public function dataInit($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        //判断是否有此入库订单
        $warehouseOrderData =(new TCWarehouseOrderModel)->getOrderInfoById($input['orderId']);
        if($warehouseOrderData->status == 2){
            //操作失败，此入库单已经审核通过，不允许数据初始化操作！
            throw new CommonException('103162');
        }
        DB::beginTransaction();
        try{
            //清除入库订单详情表卡数据
            $res1 = $this->where('order_id',$input['orderId'])->delete();
            //入库订单表更新状态为未完成及实际入库量
            $res2 = TCWarehouseOrderModel::where('id',$input['orderId'])
                    ->update(['status'=>0,'reality_num'=>0]);
            //更新仓库表：卡片总数量及库存卡片数量
            $res3 = $this->updateWarehouseByData($warehouseOrderData->warehouse_id,$warehouseOrderData->reality_num );
            if($res1>0 && $res2>0 && $res3>0){
                DB::commit();
                return TRUE;
            }else{
                DB::rollback();
                return FALSE;
            }
        } catch (Exception $ex) {
            DB::rollback();
            return FALSE;
        }  
    }
    /*
     * 数据初始化后->更新仓库表：卡片总数量及库存卡片数量
     */
    public function updateWarehouseByData($wid,$num){
        $warehouseData = TCWarehouseModel::where('id',$wid)
                ->first(['card_total_num','card_stock_num']);
        if(empty($warehouseData)){
            throw new CommonException('103102');
        }
        $updateData['card_total_num'] = $warehouseData->card_total_num-(int)$num;
        $updateData['card_stock_num'] = $warehouseData->card_stock_num-(int)$num;
        $res = TCWarehouseModel::where('id',$wid)->update($updateData);
        return $res;
    }
    /*
     * 入库订单卡片详情
     */
    public function getWareOrderCards($input,$loginUser,$type){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $where = array();
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getOrderSearchWhere($search);
        }
        if($type=='order'){
            $where[] = ['od.order_id','=',$input['orderId']];
        }elseif($type=='warehouse'){
            $where[] = ['o.warehouse_id','=',$input['warehouseId']];
        }
        
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $data = $this->getOrderPageData($where,$input['page'],$input['pageSize'],$type);
        return $data;
    }
    
    /*
     * 卡片详情列表整理
     */
    public function getOrderPageData($where,$page,$pageSize,$type){
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_warehouse_order_detail as od');
        if($type=='warehouse'){
            $sqlObject = $sqlObject->leftJoin('c_warehouse_order as o','od.order_id','=','o.id');
        }
        if(!empty($where)){
            $sqlObject =$sqlObject->where($where); 
        }
        $count = $sqlObject->count('od.id');//总条数
        $orderDetailData = $sqlObject->orderBy('od.create_time','DESC')
                ->offset($offset)->limit($pageSize)
                ->get(['od.id','od.card_no','od.iccid','od.imsi','od.card_maker','od.slice_type',
                    'od.physical_type','od.board_color','od.network_standard','od.is_print_card_no',
                    'od.create_time']);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($orderDetailData->isEmpty()){
            $result['data'] = [];
            return $result;
        }
        foreach ($orderDetailData as $value){
            $value->is_print_card_no = $value->is_print_card_no==1?'是':'否';
        }
        $result['data'] = $orderDetailData;
        return $result; 
    }
    /*
     * 卡片详情获取where条件
     */
    public function getOrderSearchWhere($input){
        $where = array();
        if(isset($input['iccid']) && !empty($input['iccid'])){
            $where[] = ['od.iccid', 'like', '%'.$input['iccid'].'%'];//入库单号
        }
        if(isset($input['cardNo']) && !empty($input['cardNo'])){
            $where[] = ['od.card_no', 'like', '%'.$input['cardNo'].'%'];//仓库名称
        }
        return $where;
    }
    
    /*
     * 导入卡片时判断此入库订单状态
     */
    public function getOrderStatus($orderId){
        $orderData = TCWarehouseOrderModel::where('id',$orderId)->first();
        if(empty($orderData)){
            throw new CommonException('106005');//订单信息有误
        }
        if($orderData->status != 0){
            //您已完成导卡，不可再次导入
            throw new CommonException('103158');
        }
        
    }
    /*
     * 库存卡片
     */
    public function getWarehouseCards($input,$loginUser){
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
     * 库存卡片：列表整理
     */
    public function getPageData($where,$page,$pageSize){
        
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_warehouse_order_detail as od')
                ->leftJoin('c_warehouse_order as wo', 'wo.id', '=', 'od.order_id')
                ->leftJoin('c_package as packageOperate', 'packageOperate.id', '=', 'od.operator_package_id')
                ->leftJoin('c_package as packageReady', 'packageReady.id', '=', 'od.ready_package_id');
        if(!empty($where)){
            $sqlObject =$sqlObject->where($where); 
        }
        $count = $sqlObject->count('od.id');//总条数
        $orderDetailData = $sqlObject->orderBy('od.create_time','DESC')
                ->offset($offset)->limit($pageSize)
                ->get(['od.id','od.card_no','od.iccid','od.imsi','od.card_maker','od.slice_type',
                    'od.physical_type','od.board_color','od.network_standard','od.is_print_card_no',
                    'od.is_white_card','od.flow_type','od.card_type','od.APN','od.is_open_sms',
                    'od.testing_period','od.silent_period','od.stock_period','od.pool_type',
                    'od.is_overflow_stop','od.create_time','packageOperate.package_name as operate_package_name',
                    'packageReady.package_name as ready_package_name','wo.operator_batch_no',
                    'wo.ware_name','wo.station_name','wo.gateway_name']);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($orderDetailData->isEmpty()){
            $result['data'] = [];
            return $result;
        }
        $operateMaintainFlowtypeGroup = TypeDetailModel::getDetailsByCode('operate_maintain_flowtype');
        $cardTypeGroup = TypeDetailModel::getDetailsByCode('card_type');
        $poolTypeGroup = TypeDetailModel::getDetailsByCode('operate_maintain_pooltype');
        foreach ($orderDetailData as $value){
            $value->is_print_card_no = $value->is_print_card_no==1?'是':'否';
            $value->is_white_card = $value->is_white_card==1?'是':'否';
            if(!empty($value->flow_type)){
                $value->flow_type = $operateMaintainFlowtypeGroup[$value->flow_type]['name'];
            }
            if(!empty($value->card_type)){
                $value->card_type = $cardTypeGroup[$value->card_type]['name'];
            }
            $value->is_open_sms = $value->is_open_sms==1?'是':'否';
            if(!empty($value->pool_type)){
                $value->pool_type = $poolTypeGroup[$value->pool_type]['name'];
            }else{
                if($value->pool_type===0){
                    $value->pool_type = $poolTypeGroup[$value->pool_type]['name'];
                }
            }
            $value->is_overflow_stop = $value->is_overflow_stop==1?'是':'否';
        }
        
        $result['data'] = $orderDetailData;
        return $result; 
    }
    /*
     * 库存卡片：查询条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['iccid']) && !empty($input['iccid'])){
            $where[] = ['od.iccid', 'like', '%'.$input['iccid'].'%'];//iccid
        }
        if(isset($input['cardNo']) && !empty($input['cardNo'])){
            $where[] = ['od.card_no', 'like', '%'.$input['cardNo'].'%'];//卡号
        }
        if(isset($input['warehouseId']) && !empty($input['warehouseId'])){
            $where[] = ['wo.warehouse_id', '=', $input['warehouseId']];//仓库名称
        }
        if(isset($input['sliceType']) && !empty($input['sliceType'])){
            $where[] = ['od.slice_type', 'like', '%'.$input['sliceType'].'%'];//切片类型
        }
        if(isset($input['physicalType']) && !empty($input['physicalType'])){
            $where[] = ['od.physical_type', 'like', '%'.$input['physicalType'].'%'];//物理类型
        }
        if(isset($input['boardColor']) && !empty($input['boardColor'])){
            $where[] = ['od.board_color', 'like', '%'.$input['boardColor'].'%'];//卡板颜色
        }
        if(isset($input['networkStandard']) && !empty($input['networkStandard'])){
            $where[] = ['od.network_standard', 'like', '%'.$input['networkStandard'].'%'];//网络制式
        }
        if(isset($input['isPrintCardNo'])){
            $where[] = ['od.is_print_card_no', '=', $input['isPrintCardNo']];//是否印制卡号
        }
        return $where;
    }
    
}
