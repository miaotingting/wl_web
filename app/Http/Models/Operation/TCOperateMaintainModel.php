<?php

namespace App\Http\Models\Operation;

use App\Http\Models\BaseModel;

use App\Http\Models\Admin\Gateway;
use App\Http\Models\Admin\Station;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Operation\StoreOutModel;
use App\Http\Models\Operation\StoreOutDetailModel;

class TCOperateMaintainModel extends BaseModel
{
    protected $table = 'c_operate_maintain';
    // public $timestamps = false;

    /**
     * 运营维护列表
     * @param [type] $request 请求数据
     * @param [type] $search 查询数组
     * @return void
     */
    public function getMaintainList($request, $search)
    {
        $where = array();
        $headerDB = DB::table('c_operate_maintain as maintain');
        if(!empty($search)){
            // 运营商侧套餐
            if(isset($search['operatorPackageName']) && !empty($search['operatorPackageName'])){
                $where[] = ['packageOperate.package_name', 'like', '%'.$search['operatorPackageName'].'%'];
            }
            // 订单号
            if(isset($search['orderNo']) && !empty($search['orderNo'])){
                $where[] = ['order.order_no', 'like', '%'.$search['orderNo'].'%'];
            }
            // 卡片实际套餐
            if(isset($search['readyPackageName']) && !empty($search['readyPackageName'])){
                $where[] = ['packageReady.package_name', 'like', '%'.$search['readyPackageName'].'%'];
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
        $sql = $headerDB->where($where)
                ->leftJoin('c_sale_order as order', 'maintain.order_id', '=', 'order.id')
                ->leftJoin('c_package as packageOperate', 'packageOperate.id', '=', 'maintain.operator_package_id')
                ->leftJoin('c_package as packageReady', 'packageReady.id', '=', 'maintain.ready_package_id');
        //总条数
        $count = $sql->count('maintain.id');
        $pageCount = ceil($count/$pageSize); #计算总页面数
        $list = $sql->orderBy('created_at','DESC')
                    ->offset(($page-1) * $pageSize)->limit($pageSize)
                    ->get(['maintain.id','order.id as order_id','order.order_no','maintain.station_name','maintain.gateway_name',
                        'maintain.status','maintain.updated_at',
                        'packageOperate.package_name as operatePackageName',
                        'packageReady.package_name as readyPackageName','maintain.is_white_card',
                        'maintain.flow_type','maintain.card_type','maintain.APN',
                        'maintain.is_open_sms','maintain.testing_period',
                        'maintain.silent_period','maintain.stock_period',
                        'maintain.pool_type','maintain.is_overflow_stop',
                        'maintain.maintain_user_name','maintain.created_at',
                        'maintain.remark','maintain.ware_name']); 
        $operateMaintainStatus = TypeDetailModel::getDetailsByCode('operate_maintain_status');
        $operateMaintainFlowType = TypeDetailModel::getDetailsByCode('operate_maintain_flowtype');
        $operateMaintainPoolType = TypeDetailModel::getDetailsByCode('operate_maintain_pooltype');
        $cardType = TypeDetailModel::getDetailsByCode('card_type');
        // 处理int状态
        foreach($list as &$val){
            $val->status = $operateMaintainStatus[$val->status]['name'];
            $val->flow_type = $operateMaintainFlowType[$val->flow_type]['name'];
            $val->pool_type = $operateMaintainPoolType[$val->pool_type]['name'];
            $val->card_type = $cardType[$val->card_type]['name'];
            $val->is_white_card = $val->is_white_card == 1?'是':'否';
            $val->is_overflow_stop = $val->is_overflow_stop == 1?'是':'否';
            $val->is_open_sms = $val->is_open_sms == 1?'是':'否';
        }
        $result = array();
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['data'] = $list;
        return $result; 
    }

    /**
     * 保存运营导入card实体信息
     * @param [type] $cardList 卡片列表
     * @param [type] $stationId 落地ID
     * @param [type] $gateWayId 网关ID
     * @param [type] $orderId 订单ID
     * @param [type] $cardType 卡片类型
     * @param [type] $request 请求数据
     * @author xyh
     */
    public function saveEntity($cards,$orderId,$stationId,$gateWayId,$cardType,$user,$filePath,$request)
    {
        $warehouseId = $request->post('wareId');
        // 查询订单信息
        $orderEntity = SaleOrderModel::where('id',$orderId)->first();
        if(empty($orderEntity)){
            throw new CommonException('106005');
        }
        // 验证是否重复维护
        $checkMaintainTimes = StoreOutModel::where('order_id', $orderId)->whereIn('status',[1,3])->first();
        if(!empty($checkMaintainTimes)){
            throw new CommonException('103257');//数据重复维护
        }
        // 验证非0开头11位或13位卡号
        // $reg="/^[1-9](\d{10}|\d{12})$/";
        // 订单卡数量
        $orderNum = $orderEntity->order_num;
        $cardsNum = count($cards);
        // 验证卡数量与订单是否匹配
        if($orderNum != $cardsNum){
            throw new CommonException('106006');
        }
        // 验证是否存在重复卡片
        $iccidList = array_column($cards, 'iccid');
        $iccidCount = count(array_unique($iccidList));
        if($orderNum != $iccidCount){
            throw new CommonException('106007');
        }
        // 验证仓库是否有库存卡片
        $checkIccidOut = TCWarehouseOrderDetailModel::whereIn('iccid', $iccidList)->get(['iccid','order_id'])->toArray();
        if(empty($checkIccidOut) || count($checkIccidOut) != $orderNum){
            throw new CommonException('106008');
        }
        // 验证库存落地与维护落地是否匹配
        $warehouseOrderIdList = [];
        foreach($checkIccidOut as $perValue){
            $warehouseOrderIdList[] = $perValue['order_id'];
        }
        $stationList = TCWarehouseOrderModel::whereIn('id',$warehouseOrderIdList)->get(['station_id','status','warehouse_id']);
        $stationArr = [];
        $warehouseArr = [];
        foreach($stationList as $station){
            $stationArr[] = $station['station_id'];
            $warehouseArr[] = $station['warehouse_id'];
            if($station['status'] != 2){
                throw new CommonException('103253'); //包含待审核入库卡片
            }
        }
        $stationCount = count(array_unique($stationArr));
        if($stationCount > 1){
            throw new CommonException('103251'); //维护卡片包含多个落地
        }
        if($stationCount == 1){
            if($stationArr[0] != $stationId){
                throw new CommonException('103252'); //维护落地与库存落地不匹配，请联系库管核实！
            }
        }
        // 验证多库状态下出库地址是否正确
        $warehouseCount = count(array_unique($warehouseArr));
        if($warehouseCount == 1){
            if($warehouseArr[0] != $warehouseId){
                throw new CommonException('103256'); //请选择正确仓库地址
            }
        }
        if($warehouseCount > 1){
            throw new CommonException('103255'); //请选择正确仓库地址，卡片包含多个仓库
        }

        // 验证卡片是否已经出库
        $checkIccidData = CardModel::whereIn('iccid', $iccidList)->get(['iccid'])->toArray();
        if(!empty($checkIccidData)){
            throw new CommonException('106009');
        }
        // 验证卡片是否已经维护
        $checkOperateData = StoreOutDetailModel::whereIn('iccid', $iccidList)->get(['iccid'])->toArray();
        if(!empty($checkOperateData)){
            throw new CommonException('103253');//卡片重复维护
        }
        DB::beginTransaction();
        try{
            // 验证通过处理待出库
            $outOrderId = getUuid();
            $outOrderNo = getOrderNo('CK');
            $storeOutEntity = new StoreOutModel();
            $storeOutEntity->id = $outOrderId;
            $storeOutEntity->store_out_order = $outOrderNo;
            $storeOutEntity->order_id = $orderId;
            $storeOutEntity->out_type = 4;  // 开卡订单
            $storeOutEntity->out_date = date('Y-m-d');
            $storeOutEntity->out_num = $orderNum;
            $storeOutEntity->remark = '开卡订单自动出库';
            $storeOutEntity->status = 1;    //待审核
            $storeOutEntity->create_user_id = $user['id'];
            $storeOutEntity->create_user_name = $user['real_name'];
            $storeOutEntity->card_type = $cardType;
            $storeOutEntity->station_id = $stationId;
            $storeOutEntity->gateway_id = $gateWayId;
            $storeOutEntity->out_warehouse_id = $warehouseId;
            $re = $storeOutEntity->save();
            // 保存卡片到待出库表
            $insertData = [];
            foreach($cards as $perData){
                $insertData[] = [
                    'id' => getUuid(),
                    'store_out_id' => $storeOutEntity->id,
                    'card_no' => $perData['cardNo'],
                    'iccid' => $perData['iccid'],
                    'imsi' => $perData['imsi']
                ];
            }
            //超过500张设置分配添加
            $max_thread_num = 500;
            $dataLength = count($insertData);
            if($dataLength < $max_thread_num){
                StoreOutDetailModel::insert($insertData);
            }else{
                $threadLen= ceil($dataLength/$max_thread_num); 
                for($i = 0; $i <= $threadLen; $i++){
                    $limitThread = $i*$max_thread_num;
                    $perThread = array_slice($insertData,$limitThread,$max_thread_num);
                    StoreOutDetailModel::insert($perThread);
                }
            }
            $stationEntity = Station::where('id',$stationId)->first();
            $gatewayEntity = Gateway::where('id',$gateWayId)->first();
            /**************************** 保存运营维护信息 *******************************/
            $TCWarehouseEntity = TCWarehouseModel::find($warehouseId);
            $TCOperateMaintainEntity = new TCOperateMaintainModel();
            $TCOperateMaintainEntity->id = getUuid();
            $TCOperateMaintainEntity->out_order_id = $outOrderId;
            $TCOperateMaintainEntity->order_id = $orderId;
            $TCOperateMaintainEntity->status = 1;
            $TCOperateMaintainEntity->operator_package_id = $request->has('operatorPackageId')?$request->post('operatorPackageId'):null;
            $TCOperateMaintainEntity->ready_package_id = $request->post('readyPackageId');
            $TCOperateMaintainEntity->is_white_card = $request->post('isWhiteCard');
            $TCOperateMaintainEntity->flow_type = $request->post('flowType');
            $TCOperateMaintainEntity->card_type = $cardType;
            $TCOperateMaintainEntity->APN = $request->post('APN');
            $TCOperateMaintainEntity->warehouse_id = $warehouseId;
            $TCOperateMaintainEntity->ware_name = $TCWarehouseEntity->ware_name;
            $TCOperateMaintainEntity->is_open_sms = $request->has('isOpenSms')?$request->post('isOpenSms'):0;
            $TCOperateMaintainEntity->testing_period = $request->has('testingPeriod')?$request->post('testingPeriod'):0;
            $TCOperateMaintainEntity->silent_period = $request->has('silentPeriod')?$request->post('silentPeriod'):0;
            $TCOperateMaintainEntity->stock_period = $request->has('stockPeriod')?$request->post('stockPeriod'):0;
            $TCOperateMaintainEntity->station_id = $stationId;
            $TCOperateMaintainEntity->station_name = $stationEntity->station_name;
            $TCOperateMaintainEntity->gateway_id = $gateWayId;
            $TCOperateMaintainEntity->gateway_name = $gatewayEntity->gateway_name;
            $TCOperateMaintainEntity->pool_type = $request->has('poolType')?$request->post('poolType'):0;
            $TCOperateMaintainEntity->is_overflow_stop = $request->has('isOverflowStop')?$request->post('isOverflowStop'):1;
            $TCOperateMaintainEntity->maintain_user_id = $user['id'];
            $TCOperateMaintainEntity->maintain_user_name = $user['real_name'];
            $TCOperateMaintainEntity->remark = $request->has('remark')?$request->post('remark'):null;
            $TCOperateMaintainEntity->save();
            /**************************** 维护库存卡片详情 *******************************/
            TCWarehouseOrderDetailModel::whereIn('iccid',$iccidList)->update([
                            'operator_package_id'=>$request->has('operatorPackageId')?$request->post('operatorPackageId'):null,
                            'ready_package_id'=>$request->post('readyPackageId'),
                            'is_white_card' => $request->post('isWhiteCard'),
                            'flow_type' => $request->post('flowType'),
                            'card_type' => $cardType,
                            'APN' => $request->post('APN'),
                            'is_open_sms' => $request->has('isOpenSms')?$request->post('isOpenSms'):0,
                            'testing_period' => $request->has('testingPeriod')?$request->post('testingPeriod'):0,
                            'silent_period' => $request->has('silentPeriod')?$request->post('silentPeriod'):0,
                            'stock_period' => $request->has('stockPeriod')?$request->post('stockPeriod'):0,
                            'pool_type' => $request->has('poolType')?$request->post('poolType'):0,
                            'is_overflow_stop' => $request->has('isOverflowStop')?$request->post('isOverflowStop'):1
                            ]);
            DB::commit();
            unlink($filePath);//清理缓存文件
            return $msg = "批量导入成功,共导入{$cardsNum}条数据";
        }catch(Exception $e){
            DB::rollback();
            throw new CommonException('300005');
        }
    } 
    
    /**
     * 运营维护数据初始化
     * @param $maintainId 运营维护ID
     * @author xyh
     */
    public function setResetMaintain($maintainId)
    {
        try{
            /************数据初始化：出库单、出库详情、维护订单、库存信息为初始化************/
            $TCOperateMaintainEntity = TCOperateMaintainModel::where('id',$maintainId)->first(['out_order_id','order_id','status']);
            // 验证是否已出库(如果已出库则不允许修改)
            if($TCOperateMaintainEntity->status == 2){
                throw new CommonException('103254');//订单已完成(已出库)不允许修改
            }
            // 开启事务操作
            DB::beginTransaction();
            $StoreOutDetailData = StoreOutDetailModel::where('store_out_id',$TCOperateMaintainEntity->out_order_id)->get(['iccid']);
            $iccidList = [];
            foreach($StoreOutDetailData as $perData){
                $iccidList[] = $perData->iccid;
            }
            // 1、初始化库存(更新库存)
            TCWarehouseOrderDetailModel::whereIn('iccid',$iccidList)->update([
                'operator_package_id'=>NULL,
                'ready_package_id'=>NULL,
                'is_white_card' => NULL,
                'flow_type' => NULL,
                'card_type' => NULL,
                'APN' => NULL,
                'is_open_sms' => NULL,
                'testing_period' => NULL,
                'silent_period' => NULL,
                'stock_period' => NULL,
                'pool_type' => NULL,
                'is_overflow_stop' => NULL
            ]);
            // 2、初始化出库单(删除出库单)
            $StoreOut = StoreOutModel::where('id',$TCOperateMaintainEntity->out_order_id)->delete();
            if($StoreOut == 0){
                DB::rollback();
                throw new CommonException('300005');
            }
            // 3、初始化出库详情(删除库存详情)
            $StoreOutDetail = StoreOutDetailModel::where('store_out_id',$TCOperateMaintainEntity->out_order_id)->delete();
            if($StoreOutDetail == 0){
                DB::rollback();
                throw new CommonException('300005');
            }
            // 4、初始化维护信息(删除维护单)
            $TCOperateMaintain = TCOperateMaintainModel::where('id',$maintainId)->delete();  
            if($TCOperateMaintain == 0){
                DB::rollback();
                throw new CommonException('300005');
            }
            DB::commit();
            return '数据初始化成功，请重新维护！';
        }catch(Exception $e){
            DB::rollback();
            throw new CommonException('300005');
        }
    } 

    /**
     * 查看维护订单
     * @param [type] $maintainId 维护订单ID
     * @author xyh
     */
    public function getMaintainOrderShow($maintainId)
    {
        return TCOperateMaintainModel::FROM('c_operate_maintain as maintain')->where('maintain.id',$maintainId)
                ->LEFTJOIN('c_sale_order as order','order.id','=', 'maintain.order_id')
                ->first(['maintain.order_id','order.order_no','maintain.operator_package_id',
                        'maintain.ready_package_id','maintain.is_white_card','maintain.flow_type',
                        'maintain.card_type','maintain.APN','maintain.warehouse_id',
                        'maintain.is_open_sms','maintain.testing_period','maintain.silent_period',
                        'maintain.stock_period','maintain.station_id','maintain.gateway_id',
                        'maintain.pool_type','maintain.is_overflow_stop','maintain.remark']);
    }

    /**
     * 修改维护订单
     * @param [type] $request 请求参数
     * @param [type] $maintainId 维护订单ID
     * @param [type] $stationId 落地ID
     * @param [type] $gateWayId 网关ID
     * @param [type] $user 登录用户
     * @author xyh
     */
    public function updateMaintainOrder($request,$maintainId)
    {
        $TCOperateMaintainEntity = TCOperateMaintainModel::where('id',$maintainId)->first();
        // 验证是否已出库(如果已出库则不允许修改)
        if($TCOperateMaintainEntity->status == 2){
            throw new CommonException('103254');//订单已完成(已出库)不允许修改
        }
        $outCardList = StoreOutDetailModel::where('store_out_id',$TCOperateMaintainEntity->out_order_id)->get(['iccid']);
        $TCWarehouseEntity = TCWarehouseModel::find($request->post('wareId'));;
        $iccidList = [];
        foreach($outCardList as $iccidEntity){
            $iccidList[] = $iccidEntity->iccid;
        }
        try{
            DB::beginTransaction();
            //更新运营维护订单信息
            TCOperateMaintainModel::where('id',$maintainId)->update([
                'operator_package_id'=>$request->has('operatorPackageId')?$request->post('operatorPackageId'):null,
                'ready_package_id'=>$request->post('readyPackageId'),
                'is_white_card' => $request->post('isWhiteCard'),
                'flow_type' => $request->post('flowType'),
                'APN' => $request->post('APN'),
                'warehouse_id' => $request->post('wareId'),
                'ware_name' => $TCWarehouseEntity->ware_name,
                'is_open_sms' => $request->post('isOpenSms'),
                'testing_period' => $request->has('testingPeriod')?$request->post('testingPeriod'):0,
                'silent_period' => $request->has('silentPeriod')?$request->post('silentPeriod'):0,
                'stock_period' => $request->has('stockPeriod')?$request->post('stockPeriod'):0,
                'pool_type' => $request->has('poolType')?$request->post('poolType'):0,
                'is_overflow_stop' => $request->post('isOverflowStop'),
                'remark' => $request->has('remark')?$request->post('remark'):NULL 
            ]);
            // 更新库存卡片详情
            TCWarehouseOrderDetailModel::whereIn('iccid',$iccidList)->update([
                'operator_package_id'=>$request->has('operatorPackageId')?$request->post('operatorPackageId'):null,
                'ready_package_id'=>$request->post('readyPackageId'),
                'is_white_card' => $request->post('isWhiteCard'),
                'flow_type' => $request->post('flowType'),
                'APN' => $request->post('APN'),
                'is_open_sms' => $request->has('isOpenSms')?$request->post('isOpenSms'):0,
                'testing_period' => $request->has('testingPeriod')?$request->post('testingPeriod'):0,
                'silent_period' => $request->has('silentPeriod')?$request->post('silentPeriod'):0,
                'stock_period' => $request->has('stockPeriod')?$request->post('stockPeriod'):0,
                'pool_type' => $request->has('poolType')?$request->post('poolType'):0,
                'is_overflow_stop' => $request->has('isOverflowStop')?$request->post('isOverflowStop'):1
            ]);
            DB::commit();
            return '修改成功！';
        } catch (Exception $ex) {
            DB::rollback();
            throw new CommonException('300005');
        }
    }

    /**
     * 维护订单卡片详情
     * @param [type] $request 请求参数
     * @param [type] $orderId 订单ID
     * @author xyh
     */
    public function getMaintainCards($request, $orderId)
    {
        $StoreOutEntity = StoreOutModel::where('order_id',$orderId)->where('status','<>',2)->first();
        $headerDB = DB::table('c_store_out_detail as outDetail');
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
        if($StoreOutEntity->status == 3){
            // 如果状态为已出库去已出库库存查看片详情
            $sql = $headerDB->where('store_out_id',$StoreOutEntity->id)
                ->leftJoin('c_warehouse_order_detail_out as warehouseDetail','outDetail.iccid','=', 'warehouseDetail.iccid')
                ->leftJoin('c_warehouse_order as warehouseOrder','warehouseDetail.order_id','=','warehouseOrder.id')
                ->leftJoin('sys_company as company','company.id','=','warehouseOrder.purchase_company_id');
        }else{
            $sql = $headerDB->where('store_out_id',$StoreOutEntity->id)
                ->leftJoin('c_warehouse_order_detail as warehouseDetail','outDetail.iccid','=', 'warehouseDetail.iccid')
                ->leftJoin('c_warehouse_order as warehouseOrder','warehouseDetail.order_id','=','warehouseOrder.id')
                ->leftJoin('sys_company as company','company.id','=','warehouseOrder.purchase_company_id');
        }
        //总条数
        $count = $sql->count('outDetail.id');
        $pageCount = ceil($count/$pageSize); #计算总页面数
        $list = $sql->offset(($page-1) * $pageSize)->limit($pageSize)
                    ->get(['outDetail.id','outDetail.card_no','outDetail.iccid','outDetail.imsi',
                        'warehouseOrder.operator_batch_no','warehouseOrder.warehouse_id',
                        'warehouseOrder.ware_name','warehouseOrder.purchase_company_id',
                        'company.company_name as purchase_company_name',
                        'warehouseOrder.order_no','warehouseDetail.card_maker',
                        'warehouseDetail.slice_type','warehouseDetail.physical_type',
                        'warehouseDetail.board_color','warehouseDetail.network_standard',
                        'warehouseDetail.is_print_card_no']);
        // 处理int状态
        foreach($list as &$val){
            $val->is_print_card_no = $val->is_print_card_no == 1?'是':'否';
        }
        $result = array();
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['data'] = $list;
        return $result; 
    }


}







