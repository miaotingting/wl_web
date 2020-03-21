<?php

namespace App\Http\Models\Operation;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeDetailModel;

class StoreOutModel extends BaseModel
{
    protected $table = 'c_store_out';
    
    // 出库管理列表数据
    public function getStoreOutList($request, $search)
    {
        $where = array();
        $headerDB = DB::table('c_store_out as out');
        if(!empty($search)){
            if(isset($search['storeOutOrder']) && !empty($search['storeOutOrder'])){
                $where[] = ['out.store_out_order', 'like', '%'.$search['storeOutOrder'].'%'];
            }
            if(isset($search['orderNo']) && !empty($search['orderNo'])){
                $where[] = ['order.order_no', 'like', '%'.$search['orderNo'].'%'];
            }
            if(isset($search['outType']) && !empty($search['outType'])){
                $where[] = ['out.out_type', '=', $search['outType']];
            }
            if(isset($search['status']) && !empty($search['status'])){
                $where[] = ['out.status', '=', $search['status']];
            }
            if(isset($search['customerName']) && !empty($search['customerName'])){
                $headerDB = $headerDB->orWhere('customer.customer_code', 'like', '%'.$search['customerName'].'%')
                        ->orWhere('customer.customer_name', 'like', '%'.$search['customerName'].'%');
            }
            if(isset($search['startDate']) || isset($search['endDate'])){
                $startDate = empty($search['startDate'])?date('Y-m-d',time()-3600*24*6):$search['startDate'];
                $endDate = empty($search['endDate'])?date('Y-m-d'):$search['endDate'];
                $headerDB = $headerDB->whereBetween('out.out_date', [$startDate,$endDate]);
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
            $pageSize = 15;
        }
        $sql = $headerDB->where($where)
                ->leftJoin('c_sale_order as order', 'out.order_id', '=', 'order.id')
                ->leftJoin('sys_customer as customer', 'order.customer_id', '=', 'customer.id')
                ->leftJoin('sys_station_config as station', 'out.station_id', '=', 'station.id');
        //总条数
        $count = $sql->count('out.id');
        $pageCount = ceil($count/$pageSize); #计算总页面数
        $list = $sql->orderBy('created_at','DESC')
                    ->offset(($page-1) * $pageSize)->limit($pageSize)
                    ->get(['out.id','out.store_out_order','out.order_id','order.order_no','out.out_type','out.created_at',
                        'out.status','out.out_date','out.out_num','out.remark','out.create_user_name',
                        DB::raw("CONCAT('(',t_customer.customer_code,')',t_customer.customer_name) AS customer_name"),
                        'station.station_name','order.order_no','out.auditor_name']); 
        $outType = TypeDetailModel::getDetailsByCode('store_out_type');
        $outStatus = TypeDetailModel::getDetailsByCode('store_out_status');
        // 处理int状态
        foreach($list as &$val){
            $val->out_type = $outType[$val->out_type];
            $val->status = $outStatus[$val->status];
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
     * 根据审核处理卡片出库
     * @param [type] $request
     * @return void
     */
    public function toExamineSet($request,$storeOutId,$user)
    {
        $examineMsg = $request->post('toExamine');//adopt_通过，unadopt_驳回
        if($examineMsg == 'adopt'){
            // 审核通过 验证订单号是否匹配
            $storeOutEntity = self::find($storeOutId);
            if($storeOutEntity->status != 1){
                throw new CommonException('103057');
            }
            if(!empty($storeOutEntity)){
                // 卡片出库
                // 1.校验对应订单是否匹配
                $saleOrderEntity =  SaleOrderModel::find($storeOutEntity->order_id);
                if(empty($saleOrderEntity)){
                    throw new CommonException('103052');
                }
                // 2.校验是否已经出库
                if($storeOutEntity->status == 3){
                    throw new CommonException('103055');
                } 
                // 3.校验订单数量是否符合
                $outCardCount = StoreOutDetailModel::where('store_out_id',$storeOutEntity->id)->count('iccid');
                if($outCardCount != $saleOrderEntity->order_num){
                    throw new CommonException('103053');
                } 
                // 3.校验卡片是否已经导入平台
                $cardCount = CardModel::where('order_id',$saleOrderEntity->id)->count('id');
                if($cardCount > 0){
                    throw new CommonException('103054');
                }
                // 4.批量新增卡片信息
                //**** 出库审核通过，卡片状态状态由“待激活”改为“待同步”，并且平台上查询不到该卡片数据 ****//
                // 订单状态 0:已提交 1:审核中 2:订单结束 3:驳回 4:作废 5.待收款 6待发货(开卡单状态)7待同步
                // 预计激活日期=发卡时间+沉默月数
                $silentDate = $saleOrderEntity->silent_date;
                $estimatedActivationTime = date('Y-m-01',strtotime("+{$silentDate} month"));
                $cardInfo = array(
                    'status' => 6,
                    'industry_type' => $saleOrderEntity->industry_type,
                    'customer_id' => $saleOrderEntity->customer_id,
                    'customer_name' => $saleOrderEntity->customer_name,
                    'operator_type' => $saleOrderEntity->operator_type,
                    'card_type' => $saleOrderEntity->card_type,
                    'card_style' => $saleOrderEntity->card_style,
                    'standard_type' => $saleOrderEntity->standard_type,
                    'model_type' => $saleOrderEntity->model_type,
                    'order_id' => $saleOrderEntity->id,
                    'station_id' => $storeOutEntity->station_id,
                    'gateway_id' => $storeOutEntity->gateway_id,
                    'fees_status' => 1, //1正常
                    'machine_status' => 1,  //0开机 1关机 2未知
                    'create_user_id' => $user['id'],
                    'create_user_name' => $user['real_name'],
                    'is_overflow_stop' => $saleOrderEntity->is_overflow_stop,
                    'sale_date' => date('Y-m-d'),
                    'pay_type' => $saleOrderEntity->pay_type,
                    'estimated_activation_time' => $estimatedActivationTime
                );

                DB::beginTransaction();
                // $bantchSaveCardSql = DB::insert('insert into users (id, name) values (?, ?)', [1, '学院君']);
                $bantchSaveCardSql = "INSERT INTO t_c_card (id,order_id,card_no,imsi,iccid,station_id,gateway_id,customer_id,customer_name,operator_type,sale_date,";
                $bantchSaveCardSql .=  "card_type,card_style,standard_type,model_type,`status`,fees_status,pay_type,create_user_id,create_user_name,created_at,updated_at,industry_type,is_overflow_stop,estimated_activation_time)";
                $bantchSaveCardSql .= "SELECT wl_uuid()";
                $bantchSaveCardSql .= ",'" . $cardInfo['order_id'] . "',card_no,imsi,iccid";
                $bantchSaveCardSql .= ",'" . $cardInfo['station_id'] . "'";
                $bantchSaveCardSql .= ",'" . $cardInfo['gateway_id'] . "'";
                $bantchSaveCardSql .= ",'" . $cardInfo['customer_id'] . "'";
                $bantchSaveCardSql .= ",'" . $cardInfo['customer_name'] . "'";
                $bantchSaveCardSql .= ",'" . $cardInfo['operator_type'] . "'";
                $bantchSaveCardSql .= ",'" . $cardInfo['sale_date'] . "'";
                $bantchSaveCardSql .= ",'" . $cardInfo['card_type'] . "'";
                $bantchSaveCardSql .= "," . $cardInfo['card_style'];
                $bantchSaveCardSql .= ",'" . $cardInfo['standard_type'] . "'";
                $bantchSaveCardSql .= "," . $cardInfo['model_type'];
                $bantchSaveCardSql .= "," . $cardInfo['status'];
                $bantchSaveCardSql .= "," . $cardInfo['fees_status'];
                $bantchSaveCardSql .= "," . $cardInfo['pay_type'];
                $bantchSaveCardSql .= ",'" . $cardInfo['create_user_id'] . "'";
                $bantchSaveCardSql .= ",'" . $cardInfo['create_user_name'] . "',now(),now()";
                $bantchSaveCardSql .= ",'" . $cardInfo['industry_type'] . "'";
                $bantchSaveCardSql .= "," . $cardInfo['is_overflow_stop'];
                $bantchSaveCardSql .= ",'" . $cardInfo['estimated_activation_time'] . "'";
                $bantchSaveCardSql .= " FROM t_c_store_out_detail WHERE store_out_id = '$storeOutEntity->id'";
                DB::select($bantchSaveCardSql);
                
                // 5.批量新增预生效信息
                $flowPackageEntity = null;//订单中的流量套餐
                $smsPackageEntity = null;//订单中的短信套餐
                $voicePackageEntity = null;//订单中的语音套餐
                if(!empty($saleOrderEntity->flow_package_id)){
                    $flowPackageEntity = Package::where('id',$saleOrderEntity->flow_package_id)->first();
                }
                if(!empty($saleOrderEntity->sms_package_id)){
                    $smsPackageEntity = Package::where('id',$saleOrderEntity->sms_package_id)->first();
                }
                if(!empty($saleOrderEntity->voice_package_id)){
                    $voicePackageEntity = Package::where('id',$saleOrderEntity->voice_package_id)->first();
                }
                $dividePrice = $saleOrderEntity->flow_card_price/$saleOrderEntity->flow_expiry_date;
                $flowPrice = floor($dividePrice*100)/100; //区两位小数但不四舍五入
                $flowCardPriceBack = $flowPrice*$saleOrderEntity->flow_expiry_date;
                $compensate = round(($saleOrderEntity->flow_card_price-$flowCardPriceBack),2);//补偿价
                $timeUnit =  $flowPackageEntity->time_unit;// 套餐表时间单位
                $timeLength = $flowPackageEntity->time_length;// 套餐表时长
                $endTime = $this->getPackageValidDate($estimatedActivationTime, $timeLength, $timeUnit, $saleOrderEntity->flow_expiry_date);
                $flowPackage = array(
                    'fees_type' => '1001',
                    'order_id' => $saleOrderEntity->id,
                    'package_id' => $flowPackageEntity->id,
                    'package_type' => $flowPackageEntity->package_type,
                    'price' => $flowPrice,
                    'compensate' =>  $compensate,
                    'use_count' => 0,
                    'unuse_count' => $saleOrderEntity->flow_expiry_date,
                    'order_num' => $saleOrderEntity->order_num,
                    'start_time' =>  $estimatedActivationTime,   //开始时间=预计激活时间月份的1号
                    'end_time' => $endTime,
                    'next_date' => $estimatedActivationTime,  //下个生效时间 = 开始时间(因为是首次)
                );
                // 流量预生效套餐
                $bantchSaveFlowPackageFutureSql = $this->getBantchSavePackageFutureSql($storeOutEntity,$flowPackage);
                DB::select($bantchSaveFlowPackageFutureSql);

                // 短信预生效套餐
                if($smsPackageEntity != null){
                    $dividePrice = $saleOrderEntity->sms_card_price/$saleOrderEntity->sms_expiry_date;
                    $smsPrice = floor($dividePrice*100)/100; //去两位小数但不四舍五入
                    $smsCardPriceBack = $smsPrice*$saleOrderEntity->sms_expiry_date;
                    $compensate = round(($saleOrderEntity->sms_card_price-$smsCardPriceBack),2);//补偿价
                    $timeUnit =  $smsPackageEntity->time_unit;// 套餐表时间单位
                    $timeLength = $smsPackageEntity->time_length;// 套餐表时长
                    $endTime = $this->getPackageValidDate($estimatedActivationTime, $timeLength, $timeUnit, $saleOrderEntity->sms_expiry_date);
                    $smsPackage = array(
                        'fees_type' => '1001',
                        'order_id' => $saleOrderEntity->id,
                        'package_id' => $smsPackageEntity->id,
                        'package_type' => $smsPackageEntity->package_type,
                        'price' => $smsPrice,
                        'compensate' =>  $compensate,
                        'use_count' => 0,
                        'unuse_count' => $saleOrderEntity->sms_expiry_date,
                        'order_num' => $saleOrderEntity->order_num,
                        'start_time' =>  $estimatedActivationTime,   //开始时间=预计激活时间月份的1号
                        'end_time' => $endTime,
                        'next_date' => $estimatedActivationTime,  //下个生效时间 = 开始时间(因为是首次)
                    );
                    $bantchSaveSmsPackageFutureSql = $this->getBantchSavePackageFutureSql($storeOutEntity,$smsPackage);
                    DB::select($bantchSaveSmsPackageFutureSql);
                }
                // 语音预生效套餐
                if($voicePackageEntity != null){
                    $dividePrice = $saleOrderEntity->voice_card_price/$saleOrderEntity->voice_expiry_date;
                    $voicePrice = floor($dividePrice*100)/100; //去两位小数但不四舍五入
                    $voiceCardPriceBack = $voicePrice*$saleOrderEntity->voice_expiry_date;
                    $compensate = round(($saleOrderEntity->voice_card_price-$voiceCardPriceBack),2);//补偿价
                    $timeUnit =  $voicePackageEntity->time_unit;// 套餐表时间单位
                    $timeLength = $voicePackageEntity->time_length;// 套餐表时长
                    $endTime = $this->getPackageValidDate($estimatedActivationTime, $timeLength, $timeUnit, $saleOrderEntity->voice_expiry_date);
                    $voicePackage = array(
                        'fees_type' => '1001',
                        'order_id' => $saleOrderEntity->id,
                        'package_id' => $voicePackageEntity->id,
                        'package_type' => $voicePackageEntity->package_type,
                        'price' => $voicePrice,
                        'compensate' =>  $compensate,
                        'use_count' => 0,
                        'unuse_count' => $saleOrderEntity->voice_expiry_date,
                        'order_num' => $saleOrderEntity->order_num,
                        'start_time' =>  $estimatedActivationTime,   //开始时间=预计激活时间月份的1号
                        'end_time' => $endTime,
                        'next_date' => $estimatedActivationTime,  //下个生效时间 = 开始时间(因为是首次)
                    );
                    $bantchSaveVoicePackageFutureSql = $this->getBantchSavePackageFutureSql($storeOutEntity,$voicePackage);
                    DB::select($bantchSaveVoicePackageFutureSql);
                }
                // 6.更新已出库信息
                $storeOutEntity->out_date = date('Y-m-d');
                $storeOutEntity->status = 3;    //已出库
                $storeOutEntity->auditor_id = $user['id'];
                $storeOutEntity->auditor_name = $user['real_name'];
                $storeOutEntity->save();

                // 7.更新运营维护状态：订单已出库
                TCOperateMaintainModel::where('order_id',$storeOutEntity->order_id)->update(['status'=>2]);

                // 8.转移库存到已出库仓库中(pluck获取列)
                $iccidList = StoreOutDetailModel::where('store_out_id',$storeOutId)->pluck('iccid')->toArray();
                $iccidStr = implode("','",$iccidList);
                $bantchMoveCardsSql = "INSERT INTO t_c_warehouse_order_detail_out (id,order_id,card_no,iccid,imsi,card_maker,slice_type,physical_type,board_color,network_standard,is_print_card_no,operator_package_id,ready_package_id,is_white_card,flow_type,card_type,APN,is_open_sms,testing_period,silent_period,stock_period,pool_type,is_overflow_stop,create_time) SELECT * FROM t_c_warehouse_order_detail WHERE iccid IN ('$iccidStr')";
                DB::select($bantchMoveCardsSql);

                // 9.去库存
                $delSql = "DELETE FROM t_c_warehouse_order_detail WHERE iccid IN ('$iccidStr')";
                DB::select($delSql);

                // 10、更新库存卡片数量
                $TCWarehouseEntity = TCWarehouseModel::find($storeOutEntity->out_warehouse_id);
                $cardStockNum = (int)$TCWarehouseEntity->card_stock_num - (int)$saleOrderEntity->order_num;
                TCWarehouseModel::where('id',$storeOutEntity->out_warehouse_id)->update(['card_stock_num'=>$cardStockNum]);  

                DB::commit();
                return '出库成功！';
            }else{
                throw new CommonException('103051');
            }
        }else{
            try{
                /************数据初始化：出库单、出库详情、维护订单、库存信息初始化************/
                $TCOperateMaintainEntity = TCOperateMaintainModel::where('out_order_id',$storeOutId)->first(['out_order_id','order_id']);
                $StoreOutDetailData = StoreOutDetailModel::where('store_out_id',$storeOutId)->get(['iccid']);
                // 开启事务操作
                DB::beginTransaction();
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
                // 2、初始化出库单(更新为驳回状态)
                // $StoreOut = StoreOutModel::where('id',$TCOperateMaintainEntity->out_order_id)->delete();
                $storeOutEntity = StoreOutModel::where('id',$storeOutId)
                                    ->update(['status'=>2,'auditor_id'=>$user['id'],'auditor_name'=>$user['real_name']]);//status = 2 驳回
                // 3、初始化出库详情(删除库存详情)
                $StoreOutDetail = StoreOutDetailModel::where('store_out_id',$storeOutId)->delete();
                if($StoreOutDetail == 0){
                    DB::rollback();
                    throw new CommonException('300005');
                }
                // 4、初始化维护信息(删除维护单) 
                $TCOperateMaintain = TCOperateMaintainModel::where('out_order_id',$storeOutId)->delete();  
                if($TCOperateMaintain == 0){
                    DB::rollback();
                    throw new CommonException('300005');
                }
                DB::commit();
                return "驳回成功,数据已初始化,联系支撑重新维护！";
            }catch(Exception $e){
                DB::rollback();
                throw new CommonException('300005');
            }
        }
    }  
    
    /**
     * 组装预生效套餐Sql
     * @param [type] $storeOutEntity 出库实体类
     * @param [type] $packageArray 套餐信息数组
     * @return void
     * @author xyh
     */
    public function getBantchSavePackageFutureSql($storeOutEntity,$packageArray)
    {
        $bantchSavePackageFutureSql = "INSERT INTO t_c_card_package_future (id,card_id,card_no,fees_type,order_id,package_id,package_type,price,compensate,use_count,unuse_count,order_num,start_time,end_time,created_time,next_date)";
        $bantchSavePackageFutureSql .= "SELECT wl_uuid(),t_c_card.id,t_c_card.card_no";
        $bantchSavePackageFutureSql .= ",'" . $packageArray['fees_type'] . "'";
        $bantchSavePackageFutureSql .= ",'" . $packageArray['order_id'] . "'";
        $bantchSavePackageFutureSql .= ",'" . $packageArray['package_id'] . "'";
        $bantchSavePackageFutureSql .= ",'" . $packageArray['package_type'] . "'";
        $bantchSavePackageFutureSql .= "," . $packageArray['price'];
        $bantchSavePackageFutureSql .= "," . $packageArray['compensate'];
        $bantchSavePackageFutureSql .= "," . $packageArray['use_count'];
        $bantchSavePackageFutureSql .= "," . $packageArray['unuse_count'];
        $bantchSavePackageFutureSql .= "," . $packageArray['order_num'];
        $bantchSavePackageFutureSql .= ",'" . $packageArray['start_time'] . "'";
        $bantchSavePackageFutureSql .= ",'" . $packageArray['end_time'] . "'";
        $bantchSavePackageFutureSql .= ",now()";
        $bantchSavePackageFutureSql .= ",'" . $packageArray['next_date'] . "'";
        $bantchSavePackageFutureSql .= " FROM t_c_card ";
        $bantchSavePackageFutureSql .= " LEFT JOIN t_c_store_out_detail ON t_c_card.iccid = t_c_store_out_detail.iccid ";
        $bantchSavePackageFutureSql .= " WHERE store_out_id = '$storeOutEntity->id'";
        return $bantchSavePackageFutureSql;
    }

    /**
     * 获取订单卡片详情
     * @param [type] $request 请求参数
     * @param [type] $orderId 订单ID
     * @author xyh
     */
    public function getOrderCards($request, $orderId)
    {
        $StoreOutEntity = StoreOutModel::where('order_id',$orderId)->where('status','<>',2)->first();
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
        if ($StoreOutEntity->status == 3) {
            // 如果状态为已出库去已出库库存查看片详情
            $sql = DB::table('c_store_out_detail as outDetail')->where('store_out_id',$StoreOutEntity->id)
                ->leftJoin('c_warehouse_order_detail_out as warehouseDetail','outDetail.iccid','=', 'warehouseDetail.iccid')
                ->leftJoin('c_warehouse_order as warehouseOrder','warehouseDetail.order_id','=','warehouseOrder.id');
        }else{
            $sql = DB::table('c_store_out_detail as outDetail')->where('store_out_id',$StoreOutEntity->id)
                ->leftJoin('c_warehouse_order_detail as warehouseDetail','outDetail.iccid','=', 'warehouseDetail.iccid')
                ->leftJoin('c_warehouse_order as warehouseOrder','warehouseDetail.order_id','=','warehouseOrder.id');
        }
        //总条数
        $count = $sql->count('outDetail.id');
        $pageCount = ceil($count/$pageSize); #计算总页面数
        $list = $sql->offset(($page-1) * $pageSize)->limit($pageSize)
                    ->get(['outDetail.id','outDetail.card_no','outDetail.iccid','outDetail.imsi',
                        'warehouseOrder.operator_batch_no','warehouseOrder.warehouse_id',
                        'warehouseOrder.ware_name','warehouseDetail.card_maker',
                        'warehouseDetail.slice_type','warehouseDetail.physical_type',
                        'warehouseDetail.board_color','warehouseDetail.network_standard',
                        'warehouseDetail.is_print_card_no','warehouseDetail.flow_type',
                        'warehouseDetail.card_type','warehouseDetail.APN','warehouseDetail.is_open_sms',
                        'warehouseDetail.testing_period','warehouseDetail.silent_period',
                        'warehouseDetail.stock_period','warehouseDetail.pool_type',
                        'warehouseDetail.is_overflow_stop']);
        $operateMaintainFlowType = TypeDetailModel::getDetailsByCode('operate_maintain_flowtype');
        $operateMaintainPoolType = TypeDetailModel::getDetailsByCode('operate_maintain_pooltype');
        $cardType = TypeDetailModel::getDetailsByCode('card_type');
        // 处理int状态
        foreach($list as &$val){
            $val->flow_type = empty($val->flow_type)?null:$operateMaintainFlowType[$val->flow_type]['name'];
            if(empty($val->pool_type)){
                $val->pool_type = $operateMaintainPoolType[0]['name'];
            }else{
                $val->pool_type = $operateMaintainPoolType[$val->pool_type]['name'];
            }
            $val->card_type = empty($val->card_type)?null:$cardType[$val->card_type]['name'];
            $val->is_open_sms = $val->is_open_sms == 1?'是':'否';
            $val->is_overflow_stop = $val->is_overflow_stop == 1?'是':'否';
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