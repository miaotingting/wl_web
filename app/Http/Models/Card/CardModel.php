<?php

namespace App\Http\Models\Card;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Operation\StoreOutModel;
use App\Http\Models\Operation\StoreOutDetailModel;
use App\Http\Models\Customer\Customer;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\API\APIFactory;
use App\Http\Models\Admin\RoleUser;
include_once public_path('org/PHPExcel/PHPExcel.php');

class CardModel extends BaseModel
{
    protected $table = 'c_card';
    protected $marketRole = '456e33858433527eb219cffb4a133698';//销售人员角色
    

    const STATUS_WAIT_ACTICITY = 1;
    const STATUS_STOP = 3;
    const STATUS_STOP_PERTECTED_CARD = 5;
    const STATUS_WAIT_SYNC = 6;


    function station() {
        return $this->hasOne('App\Http\Models\Admin\Station', 'id', 'station_id');
    }

    /**
     * 保存运营导入card实体信息
     * @param [type] $cardList 卡片列表
     * @param [type] $stationId 落地ID
     * @param [type] $gateWayId 网关ID
     * @param [type] $orderId 订单ID
     * @param [type] $cardType 卡片类型
     * @author xyh
     */
    public function saveEntity($cards,$orderId,$stationId,$gateWayId,$cardType,$user,$filePath)
    {
        // 查询订单信息
        $orderEntity = SaleOrderModel::where('id',$orderId)->first();
        if(empty($orderEntity)){
            throw new CommonException('106005');
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
        // 没验证验证落地和网关
        // 验证是否存在重复卡片
        $iccidList = array_column($cards, 'iccid');
        $iccidCount = count(array_unique($iccidList));
        if($orderNum != $iccidCount){
            throw new CommonException('106007');
        }
        // 验证库中是否已经存在卡片
        $checkIccidOut = StoreOutDetailModel::whereIn('iccid', $iccidList)->get(['iccid'])->toArray();
        if(!empty($checkIccidOut)){
            throw new CommonException('106008');
        }
        // 验证卡片是否已经出库
        $checkIccidData = CardModel::whereIn('iccid', $iccidList)->get(['iccid'])->toArray();
        if(!empty($checkIccidData)){
            throw new CommonException('106009');
        }
        DB::beginTransaction();
        try{
            // 验证通过处理入库
            $outOrderId = getOrderNo('CK');
            $storeOutEntity = new StoreOutModel();
            $storeOutEntity->id = getUuid();
            $storeOutEntity->store_out_order = $outOrderId;
            $storeOutEntity->order_id = $orderId;
            $storeOutEntity->out_type = 4;  // 开卡订单
            $storeOutEntity->out_date = date('Y-m-d');
            $storeOutEntity->out_num = $orderNum;
            $storeOutEntity->remark = '卡开订单自动出库订单';
            $storeOutEntity->status = 1;    //待审核
            $storeOutEntity->create_user_id = $user['id'];
            $storeOutEntity->create_user_name = $user['real_name'];
            $storeOutEntity->card_type = $cardType;
            $storeOutEntity->station_id = $stationId;
            $storeOutEntity->gateway_id = $gateWayId;
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
            DB::commit();
            unlink($filePath);//清理缓存文件
            return $msg = "批量导入成功,共导入{$cardsNum}条数据";
        }catch(Exception $e){
            DB::rollback();
            throw new CommonException('300005');
        }
    }
    /*
     * 网来员工登录时：我的卡片和客户卡片都是只显示一级用户的卡片，
     * 要连接order表的客户名称显示所有一级的客户名称(即所有的卡片)
     * 客户登录时：
     * 我的卡片显示用户自己的卡片(包括卖出的和没卖出的)
     * 客户卡片显示用户下级卡片
     */
    public function myCard($input,$loginUser,$type){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $loginUserWhereData = $this->getLoginUserWhere($loginUser, $type);
        $where = $loginUserWhereData['where'];
        $noSearch = false;
        if(isset($input['search']) && !empty($input['search'])){
            if($type == 'expireCard'){
                $input['search'] = json_decode($input['search'],TRUE);
            }
            $where = $where.$this->getWhere($input['search']);
        }else{
            $noSearch = true;
        }
        $data = $this->getPageData($where,$input,$loginUserWhereData['isOwnerJoin'],$loginUser,$loginUserWhereData['emptyData'],$loginUserWhereData['customerData'],$noSearch);
        return $data;
    }
    
    /*
     * 获取所有卡片详细信息
     */
    public function getPageData($where,$input,$isOwnerJoin,$loginUser,$emptyData,$customerData,$noSearch){
        if($emptyData == 1){//无客户卡片时
            $count = 0;
            $cardData = [];
        }else{
            $sqlCount = $this->getSql('count', $where, $isOwnerJoin, $input['page'], $input['pageSize'],$noSearch);
            $sql = $this->getSql('data', $where, $isOwnerJoin, $input['page'], $input['pageSize'],$noSearch);
            $cardCount = DB::select($sqlCount);
            $count = $cardCount[0]->count;//总条数
            $cardData = DB::select($sql);
        }
        $result = array();
        $pageCount = ceil($count/$input['pageSize']); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$input['page'];
        $result['pageSize'] = $input['pageSize'];
        $result['pageCount'] = $pageCount;
        if(empty($cardData)){
            $result['data'] = [];
            return $result;
        }
        
        $operatorTypeGroup = TypeDetailModel::getDetailsByCode('operator_type');
        $statusGroup = TypeDetailModel::getDetailsByCode('card_status');
        $feesStatusGroup = TypeDetailModel::getDetailsByCode('fees_status');
        $machineStatusGroup = TypeDetailModel::getDetailsByCode('machine_status');
        $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$loginUser['id']])->first(['id']);
        foreach ($cardData as $value){
            $value->operator_type = $operatorTypeGroup[$value->operator_type];//运营商类型
            $value->status = $statusGroup[$value->status];//卡片状态
            $value->fees_status = $feesStatusGroup[$value->fees_status];//计费类型
            $value->machine_status = $machineStatusGroup[$value->machine_status];//活动状态
            
            if(empty($value->sms_total) ){
                $value->sms_residue = 0;
            }else{
                $value->sms_residue = $value->sms_used>$value->sms_total ? 0 : bcsub($value->sms_total,$value->sms_used,2);
            }
            if(empty($value->flow_total) ){
                $value->flow_residue = "";
            }else{
                $value->flow_residue = $value->flow_used>$value->flow_total ? 0 : bcsub($value->flow_total,$value->flow_used,2);
            }
            if(empty($value->voice_total) ){
                $value->voice_residue = "";
            }else{
                $value->voice_residue = $value->voice_used>$value->voice_total ? 0 : bcsub($value->voice_total,$value->voice_used,2);
            }
            if($loginUser['is_owner'] == 0){ //客户的话不显示落地名称
                unset($value->station_name);
                //客户级别是二级或三级不显示发卡时间，采购单价
                if((int)$customerData->level > 1){ 
                    unset($value->sale_date);
                    unset($value->flow_card_price);
                    unset($value->voice_card_price);
                }
            }else{
                
                if(!empty($isNoMarketRole)){
                    unset($value->station_name);
                }
                $value->operate_package_name = $value->flow_package_name;//运营侧套餐
            }
            $value->flow_expiry_date = (string)($value->flow_expiry_date * $value->flow_time_length);//开通时效
            
        }
        //print_r($cardData);exit;
        $result['data'] = $cardData;
        return $result; 
    }
    /*
     * 卡片列表登录用户条件信息
     */
    public function getLoginUserWhere($loginUser,$type){
        $where = "";
        $result = [];
        $customerData = "";
        $emptyData = 0;
        if($loginUser['is_owner'] == 1 ){
            //判断登录用户是否是销售人员（是则只显示属于自己的客户卡片）
            $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$loginUser['id']])->first(['id']);
            if(!empty($isNoMarketRole)){
                $where = "where customer.account_manager_id = '".$loginUser['id']."'";
            }else{
                $where = "where 1 = 1";
            }
            $isOwnerJoin = "LEFT JOIN t_sys_customer customer on customer.id=order.customer_id ";
        }else{//客户登录
            
            $customerData = (new Customer)->getCustomerData($loginUser['customer_id']);
            if(empty($customerData)){
                throw new CommonException('102003');//客户不存在
            }
            $cdata = array();
            $allChildId = (new Customer)->getAllChildID($cdata, $loginUser['customer_id']);
            
            if($type == "myCard"){//我的卡片
                $where = "where card.customer_id = '".$loginUser['customer_id']."'";
            }elseif($type == "customerCard"){//客户卡片
                
                if(!empty($allChildId)){
                   $subCustomerIdStr = $this->setArrayChangeStr($allChildId,'id');
                   $where = ' where card.customer_id in '.$subCustomerIdStr;
                }else{
                   $emptyData = 1;
                }
            }else{//过期卡片（客户登录：显示我的和客户全部的到期卡片）
                $allChildId[]['id'] = $loginUser['customer_id'];
                $subCustomerIdStr = $this->setArrayChangeStr($allChildId,'id');
                $where = ' where card.customer_id in '.$subCustomerIdStr;
                
            }
            
            $isOwnerJoin = "LEFT JOIN t_sys_customer customer on customer.id=card.customer_id ";
        }
        if($type == 'expireCard'){//所有卡片增加到期条件
            $monthStartDay = date('Y-m-01',time());
            $monthEndDay = date('Y-m-d', strtotime("$monthStartDay +1 month -1 day"));
            $where = $where." and card.valid_date <= '".$monthEndDay."'";
        }
        $result['where'] = $where;
        $result['emptyData'] = $emptyData;
        $result['isOwnerJoin'] = $isOwnerJoin;
        $result['customerData'] = $customerData;
        return $result;
    }
    /*
     * 卡片列表的SQL语句
     */
    public function getSql($type,$where,$isOwnerJoin,$page=1,$pageSize=10,$noSearch=false){
        $start = ($page-1) * $pageSize;
        $str = "card.valid_date,card.id AS card_id,card.iccid,card.order_id, card.status,card.imsi,
                card.card_no,card.operator_type, card.card_account,card.fees_status, card.sale_date,
                card.active_date,card.machine_status,card.is_overflow_stop,
                station.station_name,
                cp1.flowtotal AS flow_total,cp2.flowtotal AS sms_total,
                cp3.flowtotal AS voice_total,cp1.flowused AS flow_used,
                cp2.flowused AS sms_used,cp3.flowused AS voice_used, 
                order.order_no,order.flow_card_price,order.voice_card_price,order.flow_expiry_date,
                CONCAT('(',customer.customer_code,')',customer.customer_name) as customer,customer.account_manager_name,
                flowPackage.package_name as flow_package_name,flowPackage.time_length as flow_time_length,
                smsPackage.package_name as sms_package_name,
                voicePackage.package_name as voice_package_name";
        $limit = "";
        if($type == 'count'){
            $str = "count(card.id) AS count";
            if($noSearch === true && $where == 'where 1 = 1'){
                return "SELECT count(card.id) AS count FROM t_c_card card";
            }
            if($noSearch === true && $where != 'where 1 = 1'){
                return $sql = "SELECT ".$str." FROM t_c_card card 
                    LEFT JOIN t_c_sale_order `order` on order.id=card.order_id ". $isOwnerJoin . $where;
            }

        }elseif($type == 'data'){
            $limit = "  limit ".$start.",".$pageSize;
        }elseif($type == 'excel'){
            //$limit = "  limit 50";
        }
        $sql = "SELECT ".$str." FROM t_c_card card 
                LEFT JOIN t_c_sale_order `order` on order.id=card.order_id 
                LEFT JOIN t_sys_station_config station on station.id=card.station_id 
                ".$isOwnerJoin." 
                LEFT JOIN t_c_package flowPackage on flowPackage.id=`order`.flow_package_id  
                LEFT JOIN t_c_package smsPackage on smsPackage.id=`order`.sms_package_id  
                LEFT JOIN t_c_package voicePackage on voicePackage.id=`order`.voice_package_id  
                LEFT JOIN (
                SELECT cpFlow.card_id,cpFlow.id,IFNULL(ROUND(cpFlow.allowance/1024,2),'0') as flowAllow,
                IFNULL(ROUND(cpFlow.total/1024,2),'0') flowtotal,IFNULL(ROUND(cpFlow.used/1024,2),'0') as flowused
                FROM t_c_card_package cpFlow  
                LEFT JOIN t_c_package package1 ON package1.id = cpFlow.package_id 
                WHERE cpFlow.package_type = 'FLOW' and cpFlow.fees_type = '1001') cp1 on cp1.card_id = card.id
                LEFT JOIN (
                SELECT cpSms.card_id,cpSms.id,IFNULL(ROUND(cpSms.allowance/1024,2),'0') as flowAllow,
                IFNULL(ROUND(cpSms.total/1024,2),'0') flowtotal,IFNULL(ROUND(cpSms.used/1024,2),'0') as flowused 
                FROM t_c_card_package cpSms 
                LEFT JOIN t_c_package package2 ON package2.id = cpSms.package_id 
                WHERE cpSms.package_type = 'SMS' and cpSms.fees_type = '1001') cp2 on cp2.card_id = card.id
                LEFT JOIN (
                SELECT cpVoice.card_id,cpVoice.id,IFNULL(ROUND(cpVoice.allowance/1024,2),'0') as flowAllow,
                IFNULL(ROUND(cpVoice.total/1024,2),'0') flowtotal,IFNULL(ROUND(cpVoice.used/1024,2),'0') as flowused
                FROM t_c_card_package cpVoice 
                LEFT JOIN t_c_package package3 ON package3.id = cpVoice.package_id 
                WHERE cpVoice.package_type = 'VOICE' and cpVoice.fees_type = '1001') cp3 on cp3.card_id = card.id ".$where.$limit;
        return $sql;
        
    }
    /*
     * 批量开卡
     */
    public function openCard($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(empty($loginUser['customer_id'])){
            throw new CommonException('102003');//此客户不存在
        }
        if(!isset($input['childCustomerId']) || empty($input['childCustomerId'])){
            throw new CommonException('106015');//请传入要开卡的下级客户ID
        }
        
        $cardNoArr = explode(',', trim($input['cardNo'],','));
        $countAddCard = count($cardNoArr);//传过来的卡片数量
        $cardCount = $this->where('customer_id',$loginUser['customer_id'])->whereIn('card_no',$cardNoArr)->count('id');
        if($cardCount < $countAddCard){
            throw new CommonException('106012');//操作失败,失败原因:包含不归属您的卡片!
        }
        $setCustomerName = (new Customer)->getCustomerName($input['childCustomerId']);
        $res = $this->where('customer_id',$loginUser['customer_id'])->whereIn('card_no',$cardNoArr)
                ->update(['customer_id'=>$input['childCustomerId'],'customer_name'=>$setCustomerName]);
        return $res;
    }
    
    /*
     * 批量回收
     */
    public function recycleCard($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(empty($loginUser['customer_id'])){
            throw new CommonException('102003');//此客户不存在
        }
        $cardNoArr = explode(',', trim($input['cardNo'],','));
        //传过来的卡片数量
        $countAddCard = count($cardNoArr);
        //获取他下一级子客户
        $childCustomer = (new Customer)->getOneChild($loginUser,['id']);
        //下级客户并且属于传过来的卡片的卡片数量
        $cardCount = $this->whereIn('customer_id',$childCustomer)->whereIn('card_no',$cardNoArr)->count('id');
        if($cardCount <= 0){
            throw new CommonException('106019');//操作失败，部分卡片有问题或者包含不需要回收的卡片！
        }
        //多出卡片数量
        $surplusCardNum = (int)$countAddCard - (int)$cardCount;
        $setCustomerName = (new Customer)->getCustomerName($loginUser['customer_id']);
        $res = $this->whereIn('customer_id',$childCustomer)
                ->whereIn('card_no',$cardNoArr)
                ->update(['customer_id'=>$loginUser['customer_id'],'customer_name'=>$setCustomerName]);
        if($res <= 0){
            return FALSE;
        }
        $data['message'] = '操作成功，成功回收'.$cardCount.'张卡片,有'.$surplusCardNum.'张卡片未回收成功。';
        return $data;
    }
    /*
     * 条件查询
     */
    public function getWhere($input){
        $where = "";
        if(isset($input['cardNo']) ){
            $where .= " and card.card_no like '%".$input['cardNo']."%'";//卡号
        }
        if(isset($input['iccid'])){
            $where .= " and card.iccid like '%".$input['iccid']."%'";//iccid
        }
        if(isset($input['imsi'])){
            $where .= " and card.imsi like '%".$input['imsi']."%'";//imsi
        }
        if(isset($input['operatorType']) && !empty($input['operatorType'])){
            $where .= " and card.operator_type = '".$input['operatorType']."'";//运营商类型
        }
        if(isset($input['status'])){
            if(empty($input['status'])){
                if($input['status'] == "0"){
                    $where .= " and card.status = 0";//卡状态
                }
            }else{
                $where .= " and card.status = ".$input['status'];//卡状态
            }
        }
        if(isset($input['machineStatus'])){
            if(empty($input['machineStatus'])){
                if($input['machineStatus'] == "0"){
                    $where .= " and card.machine_status = 0";//活动状态
                }
            }else{
                $where .= " and card.machine_status = ".$input['machineStatus'];//活动状态
            }
        }
        if(isset($input['saleDateStart']) && !empty($input['saleDateStart'])){
            $where .= " and card.sale_date >= '".$input['saleDateStart']."'";//发卡日期起始日期
        }
        if(isset($input['saleDateEnd']) && !empty($input['saleDateEnd'])){
            $where .= " and card.sale_date <= '".$input['saleDateEnd']."'";//发卡日期结束日期
        }
        if(isset($input['customer']) && !empty($input['customer'])){//客户名称或编号
            $where .= " and (customer.customer_code like  '%".$input['customer']."%'  or (customer.customer_name like  '%".$input['customer']."%' ))";
        }
        if(isset($input['flowPackageName']) && !empty($input['flowPackageName'])){
            $where .= " and flowPackage.package_name like '%".$input['flowPackageName']."%'";//流量套餐名称
        }
        if(isset($input['voicePackageName']) && !empty($input['voicePackageName'])){
            $where .= " and voicePackage.package_name like '%".$input['voicePackageName']."%'";//语音套餐名称
        }
        if( isset($input['validDate']) && !empty($input['validDate']) ){//服务期止
            $time = date('Y-m-d H:i:s',time());
            switch ($input['validDate']){
                case 'pastDue':
                    $where .= " and card.valid_date < '".$time."'";//已过期
                    break;
                case 'threeDay':
                    $threeDay = date('Y-m-d H:i:s', strtotime('- 3 day'));
                    $where .= " and card.valid_date <= '".$time."'";//三天内
                    $where .= " and card.valid_date >= '".$threeDay."'";
                    break;
                case 'week':
                    $week = date('Y-m-d H:i:s', strtotime('- 1 week'));
                    $where .= " and card.valid_date <= '".$time."'";//一周内
                    $where .= " and card.valid_date >= '".$week."'";
                    break;
                case 'month':
                    $month = date('Y-m-d H:i:s', strtotime('- 1 month'));
                    $where .= " and card.valid_date <= '".$time."'";//一个月内
                    $where .= " and card.valid_date >= '".$month."'";
                    break;
                case 'threeMonth':
                    $threeMonth = date('Y-m-d H:i:s', strtotime('- 3 month'));
                    $where .= " and card.valid_date <= '".$time."'";//三个月内
                    $where .= " and card.valid_date >= '".$threeMonth."'";
                    break;
            }
        }
        //echo $where;exit;
        if(isset($input['stationName']) && !empty($input['stationName'])){//落地名称
            $where .= " and station.station_name like '%".$input['stationName']."%'";
        }
        if(isset($input['cardNoMany']) && !empty($input['cardNoMany'])){//批量查卡号
            $cardNoStr = explode(',',trim($input['cardNoMany'],','));
            $cardNoArr = $this->setArrayChangeStr($cardNoStr);
            $where .= ' and card.card_no in '.$cardNoArr;
        }
        if(isset($input['iccidMany']) && !empty($input['iccidMany'])){//批量查iccid
            $iccidStr = explode(',',trim($input['iccidMany'],','));
            $iccidArr = $this->setArrayChangeStr($iccidStr);
            $where .= ' and card.iccid in '.$iccidArr;
        }
        if(isset($input['orderNoMany']) && !empty($input['orderNoMany'])){//批量查订单号
            $orderNoStr = explode(',',trim($input['orderNoMany'],','));
            $orderNoArr = $this->setArrayChangeStr($orderNoStr);
            $where .= ' and order.order_no in '.$orderNoArr;
        }
        if(isset($input['imsiMany']) && !empty($input['imsiMany'])){//批量查imsi
            $imsiStr = explode(',',trim($input['imsiMany'],','));
            $imsiArr = $this->setArrayChangeStr($imsiStr);
            $where .= ' and card.imsi in '.$imsiArr;
        }
        if(isset($input['accountManagerName']) ){ //客户经理
            $where .= " and customer.account_manager_name like '%".$input['accountManagerName']."%'";
        }
        return $where;
    }
    /*
     * 把条件数组换成wherein字符串
     */
    public function setArrayChangeStr($arr,$key=""){
        $str = "(";
        foreach($arr as $value){
            if($key == ""){
                $str = $str."'".$value."',";
            }else{
                $str = $str."'".$value[$key]."',";
            }
            
        }
        return substr($str, 0,-1).")";
    }
    
    /**
     * 获取下面的卡片
     */
    function getCards(int $pageIndex, int $pageSize, string $orderId) {
        $where = ['order_id' => $orderId];
        $fields = ['id', 'card_no', 'station_id'];
        $res = $this->queryPage($pageSize, $pageIndex, $where, $fields);
        if (!empty($res['data'])) {
            foreach ($res['data'] as $card) {
                $card->station_name = empty($card->station) ? '' : $card->station->station_name;
                $card->station = '';
            }
        }
        return $res;
    }
    /*
     * 获取某个卡片的详细信息
     */
   
    public function getCardInfo($id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $sql = $this->getOneCardSql($id);
        $cardData = DB::select($sql);
        //print_r($cardData);exit;
        if(empty($cardData)){
            throw new CommonException('106016');
        }
        $result = array();
        //基本信息
        $result['card_no'] = $cardData[0]->card_no;
        $result['imsi'] = $cardData[0]->imsi;
        $result['iccid'] = $cardData[0]->iccid;
        $result['customer_name'] = $cardData[0]->customer_name;
        //$result['operator_type'] = $cardData[0]->operator_type;
        
        $result['operator_type'] = Package::getTypeDetail('operator_type', $cardData[0]->operator_type)['name'];//运营商类型
        $result['status'] = Package::getTypeDetail('card_status', $cardData[0]->status)['name'];//卡片状态
        $result['sale_date'] = $cardData[0]->sale_date;
        $result['active_date'] = $cardData[0]->active_date;
        $result['valid_date'] = $cardData[0]->valid_date;
        $result['machine_status'] = Package::getTypeDetail('machine_status', $cardData[0]->machine_status)['name'];//活动状态
        $result['card_account'] = $cardData[0]->card_account;
        $result['sp_code'] = $cardData[0]->sp_code;
        $result['is_overflow_stop'] = $cardData[0]->is_overflow_stop;
        if($loginUser['is_owner'] == 1 ){ //网来员工登录
            //判断登录用户是否是销售人员（是则只显示属于自己的客户卡片）
            $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$loginUser['id']])->first(['id']);
            if(empty($isNoMarketRole)){
                $result['station_name'] = $cardData[0]->station_name;
            }
        }
       
        //套餐信息+本期套餐
        //$packageTypeGroup = TypeDetailModel::getDetailsByCode('package_type');
        if(!empty($cardData[0]->flow_package_name)){
            $result['flow_package'] = $this->setPackageData('flow', $cardData[0]);
        }
        if(!empty($cardData[0]->sms_package_name)){
            $result['sms_package'] = $this->setPackageData('sms', $cardData[0]);
        }
        if(!empty($cardData[0]->voice_package_name)){
            $result['voice_package'] = $this->setPackageData('voice', $cardData[0]);
        }
        //下期套餐
        $nextPackageData = DB::table('c_card_package_future as future')
                ->leftJoin('c_package as package','package.id','future.package_id')
                ->where(['future.card_id'=>$id,'package.status'=>0])
                ->get(['package.package_name','package.package_type','package.time_length','package.time_unit',
                    'future.price','package.consumption','future.start_time','future.end_time',
                    'future.next_date','future.unuse_count']);
        $unuseCount = 0;
        $packageTypeGroup = TypeDetailModel::getDetailsByCode('package_type');
        foreach($nextPackageData as $value){
            if($value->package_type  == 'FLOW'){
                $unuseCount = $value->unuse_count;
                $value->settlement_time = (string)($cardData[0]->flow_expiry_date * $cardData[0]->flow_time_length).'月';
            }elseif($value->package_type  == 'SMS'){
                $value->settlement_time = (string)($cardData[0]->sms_expiry_date * $cardData[0]->sms_time_length).'月';
            }else{
                $value->settlement_time = (string)($cardData[0]->voice_expiry_date * $cardData[0]->voice_time_length).'月';
            }
            $value->package_type = $packageTypeGroup[$value->package_type]['name'];//套餐类型
            $value->end_time = date('Y-m-d', strtotime("$value->next_date + $value->time_length $value->time_unit -1 day"));
            $value->start_time  = $value->next_date;
        }
        if($unuseCount == 0){//如果未生效次数为0则无下期套餐
            $nextPackageData = [];
        }
        $result['nextPackage'] = $nextPackageData;
        return $result;
    }
    /*
     * 获取单条卡片信息的SQL
     */
    public function getOneCardSql($id){
        $where = " where card.id = '".$id."' or card.card_no = '".$id."' or card.iccid = '".$id."'";
        $sql = "SELECT card.id AS card_id,card.iccid,card.card_no,card.imsi,card.customer_name,card.operator_type,
                card.status,card.sale_date,card.active_date,card.valid_date,card.machine_status,
                card.card_account,card.is_overflow_stop,station.station_name,gateway.sp_code,
                
                cp1.package_name AS flow_package_name,cp1.flowtotal AS flow_total,cp1.price as flow_price,
                cp1.flowused AS flow_used,cp1.consumption as flow_consumption,cp1.package_type as flow_package_type,
                cp1.enable_date as flow_enable_date,cp1.failure_date as flow_failure_date,
                cp2.package_name AS sms_package_name,cp2.flowtotal AS sms_total,cp2.price as sms_price,
                cp2.flowused AS sms_used,cp2.consumption as sms_consumption,cp2.package_type as sms_package_type,
                cp2.enable_date as sms_enable_date,cp2.failure_date as sms_failure_date,
                cp3.package_name AS voice_package_name,cp3.flowtotal AS voice_total,cp3.price as voice_price,
                cp3.flowused AS voice_used,cp3.consumption as voice_consumption,cp3.package_type as voice_package_type,
                cp3.enable_date as voice_enable_date,cp3.failure_date as voice_failure_date,
                
                order.flow_expiry_date,order.sms_expiry_date,order.voice_expiry_date,order.customer_id as order_customer_id,
                
                flowPackage.time_length as flow_time_length,
                smsPackage.time_length as sms_time_length,
                voicePackage.time_length as voice_time_length
                
                FROM t_c_card card 
                LEFT JOIN t_c_sale_order `order` on order.id=card.order_id 
                LEFT JOIN t_sys_gateway_config `gateway` on gateway.id=card.gateway_id 
                LEFT JOIN t_sys_station_config station on station.id=card.station_id 
                LEFT JOIN t_c_package flowPackage on flowPackage.id=`order`.flow_package_id  
                LEFT JOIN t_c_package smsPackage on smsPackage.id=`order`.sms_package_id  
                LEFT JOIN t_c_package voicePackage on voicePackage.id=`order`.voice_package_id
                LEFT JOIN (
                SELECT cpFlow.card_id,cpFlow.id,IFNULL(ROUND(cpFlow.allowance/1024,2),'0') as flowAllow,package1.package_name,
                IFNULL(ROUND(cpFlow.total/1024,2),'0') flowtotal,IFNULL(ROUND(cpFlow.used/1024,2),'0') as flowused,cpFlow.price,
                package1.consumption,cpFlow.package_type,cpFlow.enable_date,cpFlow.failure_date
                FROM t_c_card_package cpFlow  
                LEFT JOIN t_c_package package1 ON package1.id = cpFlow.package_id 
                WHERE cpFlow.package_type = 'FLOW' and cpFlow.fees_type = '1001') cp1 on cp1.card_id = card.id
                LEFT JOIN (
                SELECT cpSms.card_id,cpSms.id,IFNULL(ROUND(cpSms.allowance/1024,2),'0') as flowAllow,package2.package_name,
                IFNULL(ROUND(cpSms.total/1024,2),'0') flowtotal,IFNULL(ROUND(cpSms.used/1024,2),'0') as flowused,cpSms.price,
                package2.consumption,cpSms.package_type,cpSms.enable_date,cpSms.failure_date 
                FROM t_c_card_package cpSms 
                LEFT JOIN t_c_package package2 ON package2.id = cpSms.package_id 
                WHERE cpSms.package_type = 'SMS' and cpSms.fees_type = '1001') cp2 on cp2.card_id = card.id
                LEFT JOIN (
                SELECT cpVoice.card_id,cpVoice.id,IFNULL(ROUND(cpVoice.allowance/1024,2),'0') as flowAllow,package3.package_name,
                IFNULL(ROUND(cpVoice.total/1024,2),'0') flowtotal ,IFNULL(ROUND(cpVoice.used/1024,2),'0') as flowused,cpVoice.price,
                package3.consumption,cpVoice.package_type,cpVoice.enable_date,cpVoice.failure_date
                FROM t_c_card_package cpVoice 
                LEFT JOIN t_c_package package3 ON package3.id = cpVoice.package_id 
                WHERE cpVoice.package_type = 'VOICE' and cpVoice.fees_type = '1001') cp3 on cp3.card_id = card.id  ".$where." limit 1";
        return $sql;
    }
    /*
     * 整理卡片套餐
     */
    public function setPackageData($type,$row,$port='web'){
        $result = array();
        if($type == 'flow'){
            $rows = ['package_name'=>$row->flow_package_name,
                    'total'=>$row->flow_total,
                    'price'=>$row->flow_price,
                    'used'=>$row->flow_used,
                    'consumption'=>$row->flow_consumption,
                    'package_type'=>$row->flow_package_type,
                    'settlement_time'=>(string)($row->flow_expiry_date*$row->flow_time_length).'月',
                    'enable_date'=>$row->flow_enable_date,
                    'failure_date'=>$row->flow_failure_date,
                    ];
        }elseif($type == 'sms'){
            $rows = ['package_name'=>$row->sms_package_name,
                    'total'=>$row->sms_total,
                    'price'=>$row->sms_price,
                    'used'=>$row->sms_used,
                    'consumption'=>$row->sms_consumption,
                    'package_type'=>$row->sms_package_type,
                    'settlement_time'=>(string)($row->sms_expiry_date*$row->sms_time_length).'月',
                    'enable_date'=>$row->sms_enable_date,
                    'failure_date'=>$row->sms_failure_date,
                    ];
        }else{
            $rows = ['package_name'=>$row->voice_package_name,
                    'total'=>$row->voice_total,
                    'price'=>$row->voice_price,
                    'used'=>$row->voice_used,
                    'consumption'=>$row->voice_consumption,
                    'package_type'=>$row->voice_package_type,
                    'settlement_time'=>(string)($row->voice_expiry_date*$row->voice_time_length).'月',
                    'enable_date'=>$row->voice_enable_date,
                    'failure_date'=>$row->voice_failure_date,
                    ];
        }
        
        $result['package_name'] = $rows['package_name'];
        if(empty($rows['total'])){
            $result['total'] = 0;
        }else{
            $result['total'] = $rows['total'];
        }
        if(empty($rows['used'])){
            $result['used'] = 0;
        }else{
            $result['used'] = $rows['used'];
        }
        $result['enable_date'] = $rows['enable_date'];
        $result['failure_date'] = $rows['failure_date'];
        if($result['used'] > $result['total']){
            $result['residue'] =  0;
        }else{
            $result['residue'] =  bcsub($result['total'],$result['used'],2);
        }
        if($port == 'web'){
            $result['price'] = $rows['price'];
            $result['consumption'] = $rows['consumption'];
            if(empty($rows['package_type'])){
                $result['package_type'] = "";
            }else{
                $result['package_type'] = Package::getTypeDetail('package_type',$rows['package_type'])['name'];//套餐类型

            }
            $result['settlement_time'] = $rows['settlement_time'];
        }
        return $result;
        
    }
    /*
     * 获取该卡片的历史用量
     */
    public function getUsedHistory($id,$input){
        $cardData = $this->where('id',$id)->first(['id']);
        if(empty($cardData)){
            throw new CommonException('106016');//卡片不存在 
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $where = array();
        if(isset($input['search']) && !empty($input['search'])){
            
            $search = json_decode($input['search'],TRUE);
            if(isset($search['usedStartDate']) && !empty($search['usedStartDate'])){
                $where[]= ['used.use_date','>=',$search['usedStartDate']];
            }
            if(isset($search['usedEndDate']) && !empty($search['usedEndDate'])){
                $where[]= ['used.use_date','<=',$search['usedEndDate']];
            }
        }
        $where[] = ['used.card_id','=',$id];
        $offset = ($input['page']-1) * $input['pageSize'];
        $sqlObject = DB::table('c_card_date_used as used')
                ->leftJoin('c_card as card','used.card_id','card.id')
                ->where($where)->orderBy('used.use_date','DESC');
        $count = $sqlObject ->count('used.id');//总条数
        $usedData = $sqlObject ->offset($offset)->limit($input['pageSize'])
                ->get(['used.card_no','used.use_date','used.flow_used',
                    'used.sms_used','used.voice_used','card.customer_name']);
        
        $result = array();
        $pageCount = ceil($count/$input['pageSize']); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $input['page'];
        $result['pageSize'] = $input['pageSize'];
        $result['pageCount'] = $pageCount;
        $result['expirationDate'] = date("Y-m-d",strtotime("-1 day"));
        $result['data'] = $usedData;
        if($usedData->isEmpty()){
            $result['data'] = [];
        }else{
            foreach($usedData as $value){
                $value->flow_used = bcdiv($value->flow_used,1024,2);
            }
            $result['data'] = $usedData;
        }
        return $result;   
    }
    /*
     * 实时更新卡片的活动状态
     */
    public function updateMachineStatus($cardNo){
        $cardData = $this->where('c_card.card_no',$cardNo)
                ->leftJoin('sys_station_config as s','s.id','=','c_card.station_id')
                ->first(['c_card.station_id','s.platform_type']);
        if(empty($cardData)){
            throw new CommonException('106016');//卡片不存在
        }
        $data = (new APIFactory)->factory($cardData['station_id'], $cardData['platform_type']);
        if ($cardData['platform_type'] == 1) {
            //老平台
            $resultMachineStatus = $data->CMIOT_API2008($cardNo);
            $resultMachineStatus = json_decode($resultMachineStatus);
        }else{
            //新平台
            $resultMachineStatus = $data->CMIOT_API25M00($cardNo,'cardNo');
        }
        if($resultMachineStatus->status != '0'){
            //调用BaseModel log(logStr)方法
            $this->log(date('Y-m-d H:i:s')."更新卡片{$cardNo}活动状态调用接口出参：".json_encode($resultMachineStatus));
            throw new CommonException('106017');//出现异常，请联系服务提供者处理
        }
        
        $status = $resultMachineStatus->result[0]->status;
        $res = $this->where(['card_no'=>$cardNo])->update(['machine_status'=>$status]);
        $result = array();
        if($res >0){
        $result = (new Package)->getTypeDetail('machine_status',$status);
            return $result;
        }else{
            throw new CommonException('106018');
        }
    }
    /*
     * 获取某客户下卡片
     */
    public function getCardCountByCustomerId($customerId){
        $data = $this->where('customer_id',$customerId)->count('id');
        return $data;
    }
   
    
    
    
    
    

}







