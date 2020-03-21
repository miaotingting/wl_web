<?php

namespace App\Http\Models\Order;

use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeModel;
use App\Http\Models\BaseModel;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Operation\Package;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Order\TCRenewOrderDetailModel;
use App\Http\Models\Customer\Customer;
use App\Http\Models\WeChat\TCPayOrderModel;
use App\Http\Models\Card\CardPackageModel;
use App\Http\Models\Card\TCCardPackageHisModel;
use App\Http\Models\Order\TCOrderTemplateModel;
use App\Http\Models\Order;
use App\Http\Models\Card\TCCardFileModel;

class TCRenewOrderModel extends BaseModel
{
    //
    protected $table = 'c_renew_order';
    public $timestamps = false;

    //计费类型
    /*const FEES_TYPE_WECHAT = 1;  //微信续费

    //续费套餐生效类型
    const EFFECT_TYPE_NOW = 1;  //当月生效

    //付款方式
    const PAYMENT_METHOD_WECHAT = 1;  //微信支付*/

    public function getList($input,$loginUser){
       $where = [];
       if(empty($loginUser)){
           throw new CommonException('300001');
       }
       //print_r($loginUser);exit;
       //获取客户续费方式
       if($loginUser['is_owner'] == 1){
           throw new CommonException('109014');
       }else{
           $customerData = (new Customer)->getCustomerData($loginUser['customer_id']);
           if(empty($customerData)){
               throw new CommonException('102003');
           }
           $renewalWay = $customerData->renewal_way;
       }
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

       $data = $this->getPageData($where,$input['page'],$input['pageSize'],$renewalWay);
       return $data;
    }
    /*
     * 获取列表分类数据
     */
    public function getPageData($where,$page,$pageSize,$renewalWay){
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_renew_order as ro')
                ->leftJoin('sys_customer as c','ro.customer_id','=','c.id')
                ->where($where);
        $count = $sqlObject->count('ro.id');//总条数
        $orders = $sqlObject->orderBy('ro.create_time','DESC')
                ->offset($offset)->limit($pageSize)
                ->get(['ro.id','ro.trade_no','c.customer_code','c.customer_name',
                    'ro.operator_type','ro.card_type','ro.status','ro.model_type','ro.standard_type',
                    'ro.industry_type','ro.amount','ro.order_num','ro.silent_date','ro.create_time',
                    'ro.update_time','ro.is_sms','ro.is_flow','ro.is_voice','ro.sms_package_id',
                    'ro.flow_package_id','ro.voice_package_id','ro.contacts_name',
                    'ro.contacts_mobile']);
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['renewalWay'] = $renewalWay;
        if(!$orders->isEmpty()){
            $operatorTypeGroup = TypeDetailModel::getDetailsByCode('operator_type');
            $cardTypeGroup = TypeDetailModel::getDetailsByCode('card_type');
            $renewOrderStatusGroup = TypeDetailModel::getDetailsByCode('renew_order_status');
            $modelTypeGroup = TypeDetailModel::getDetailsByCode('model_type');
            $standardTypeGroup = TypeDetailModel::getDetailsByCode('standard_type');
            $industryTypeGroup = TypeDetailModel::getDetailsByCode('industry_type');
            foreach ($orders as $order) {
                $order->operator_type = $operatorTypeGroup[$order->operator_type];
                $order->card_type = $cardTypeGroup[$order->card_type];
                $order->status = $renewOrderStatusGroup[$order->status];
                $order->model_type = $modelTypeGroup[$order->model_type];
                $order->standard_type = $standardTypeGroup[$order->standard_type];
                $order->industry_type = $industryTypeGroup[$order->industry_type];
                //查询套餐
                $packageModel = new Package;
                $smsPackage = null;
                if (intval($order->is_sms) === 1) {
                    $smsPackage = $packageModel->where('id', $order->sms_package_id)->first(['id', 'package_name']);
                }
                $order->sms_package_name = empty($smsPackage) ? '' : $smsPackage->package_name;

                $flowPackage = null;
                if (intval($order->is_flow) === 1) {
                    $flowPackage = $packageModel->where('id', $order->flow_package_id)->first(['id', 'package_name']);
                }
                $order->flow_package_name = empty($flowPackage) ? '' : $flowPackage->package_name;

                $voicePackage = null;
                if (intval($order->is_voice) === 1) {
                    $voicePackage = $packageModel->where('id', $order->voice_package_id)->first(['id', 'package_name']);
                }
                $order->voice_package_name = empty($voicePackage) ? '' : $voicePackage->package_name;
            }
        }
        $result['data'] = $orders;
        
        //print_r($result);exit;
        return $result;
    }
    /*
     * 搜索条件查询
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['customerCode']) && !empty($input['customerCode'])){
            $where[] = ['c.customer_code', 'like', '%'.$input['customerCode'].'%'];//客户编号
        }
        if(isset($input['customerName']) && !empty($input['customerName'])){
            $where[] = ['c.customer_name', 'like', '%'.$input['customerName'].'%'];//客户名称
        }
        if(isset($input['status']) && !empty($input['status'])){
            $where[] = ['ro.status', '=', $input['status']];//状态
        }
        if(isset($input['tradeNo']) && !empty($input['tradeNo'])){
            $where[] = ['ro.trade_no', 'like', '%'.$input['tradeNo'].'%'];//续费编号
        }
        if(isset($input['operatorType']) && !empty($input['operatorType'])){
            $where[] = ['ro.operator_type', '=', $input['operatorType']];//运营商类型
        }
        if(isset($input['standardType']) && !empty($input['standardType'])){
            $where[] = ['ro.standard_type', '=', $input['standardType']];//通讯制式
        }
        if(isset($input['placeOrderStartTime']) && !empty($input['placeOrderStartTime'])){
            $where[] = ['ro.create_time', '>=', $input['placeOrderStartTime']];//下单时间起始时间
        }
        if(isset($input['placeOrderEndTime']) && !empty($input['placeOrderEndTime'])){
            $where[] = ['ro.create_time', '<=', $input['placeOrderEndTime']];//下单时间结束时间
        }
        if(isset($input['endOrderStartTime']) && !empty($input['endOrderStartTime'])){
            $where[] = ['ro.update_time', '>=', $input['endOrderStartTime']];//结束订单起始时间
        }
        if(isset($input['endOrderEndTime']) && !empty($input['endOrderEndTime'])){
            $where[] = ['ro.update_time', '<=', $input['endOrderEndTime']];//结束订单结束时间
        }
        
        return $where;
    }
    /*
     * 续费单明细
     */
    public function getInfo($id){
        $order = $this->where('id',$id)->first(['id','trade_no','customer_name','contacts_name',
            'contacts_mobile','express_arrive_day','operator_type','industry_type','model_type',
            'standard_type','card_type','is_overflow_stop','describe','is_flow','is_sms','is_voice',
            'flow_package_id','sms_package_id','voice_package_id','flow_expiry_date','sms_expiry_date',
            'voice_expiry_date','silent_date','real_name_type','flow_card_price','sms_card_price',
            'voice_card_price','order_num','amount','payment_method']);
        if(empty($order)){
            throw new CommonException('107101');
        }
        //查询套餐
        $packageModel = new Package;
        $smsPackage = null;
        if (intval($order->is_sms) === 1) {
            $smsPackage = $packageModel->where('id', $order->sms_package_id)->first(['id', 'package_name']);
        }
        $order->sms_package_name = empty($smsPackage) ? '' : $smsPackage->package_name;

        $flowPackage = null;
        if (intval($order->is_flow) === 1) {
            $flowPackage = $packageModel->where('id', $order->flow_package_id)->first(['id', 'package_name']);
        }
        $order->flow_package_name = empty($flowPackage) ? '' : $flowPackage->package_name;

        $voicePackage = null;
        if (intval($order->is_voice) === 1) {
            $voicePackage = $packageModel->where('id', $order->voice_package_id)->first(['id', 'package_name']);
        }
        $order->voice_package_name = empty($voicePackage) ? '' : $voicePackage->package_name;
        return $order;
    }
    /*
     * 续费订单卡片明细 
     */
    public function renewOrderCards($renewOid,$input){
        $renewOrder = $this->where('id',$renewOid)->first(['order_id']);
        if(empty($renewOrder)){
            throw new CommonException('107101');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $offset = ($input['page']-1) * $input['pageSize'];
        $sqlObject = DB::table('c_card as c')
                ->leftJoin('c_sale_order as o','c.order_id','=','o.id')
                ->leftJoin('sys_station_config as s','c.station_id','=','s.id')
                ->leftJoin('c_package as cp','o.flow_package_id','=','cp.id')
                ->where(['c.order_id'=>$renewOrder->order_id]);
        $count = $sqlObject->count('c.id');
        $data = $sqlObject->offset($offset)->limit($input['pageSize'])
                ->get(['c.card_no','s.station_name','cp.package_name']);
        $result = array();
        $pageCount = ceil((int)$count/(int)$input['pageSize']); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $input['page'];
        $result['pageSize'] = $input['pageSize'];
        $result['pageCount'] = $pageCount;
        $result['data'] = $data;
        return $result;
    }
    /*
     * 续费时公共验证条件
     */
    public function renewValidate($input,$loginUser){
       $numArr = explode(',',$input['numStr']);//提交过来的卡片数组
       $numArrCount = count($numArr);//提交过来的卡片数量
       // 验证是否存在重复卡片
       $distinctCount = count(array_unique($numArr));//去掉数组中重复值
       if($numArrCount != $distinctCount){
           throw new CommonException('107103');
       }
       //判断如果是iccid则全部转成卡号操作
       if($input['type'] == 'iccid'){
           $cardNoData = CardModel::whereIn('iccid',$numArr)->get(['card_no']);
       }
       //验证是否有不属于客户的卡
       $where = [];
       $where[] = ['o.customer_id','=',$loginUser['customer_id']];
       $customerCardCount = CardModel::from('c_card as c')
                ->leftjoin('c_sale_order as o','c.order_id','o.id')
                ->where($where)
                ->whereIn('c.card_no',$numArr)
                ->count('c.id');
       if($customerCardCount != $numArrCount){
           throw new CommonException('107104');
       }
       
       //验证这些卡是否属于一个订单
       $orderId = CardModel::whereIn('card_no',$numArr)->distinct()->get(['order_id']);
       if(count($orderId) > 1){
           throw new CommonException('107105');
       }
       $orderId = $orderId[0]->order_id;//续费卡片的订单ID
       //验证是否有白卡，白卡不允许续费
       $whiteCardCount = CardModel::where(['status'=>0])
               ->whereIn('card_no',$numArr)->count('id');
       if($whiteCardCount > 0){
           throw new CommonException('107106');
       }
       //验证卡片的实际运营商与客户选择的运营商是否一致
       $operatorCardCount = CardModel::where(['operator_type'=>$input['operatorType']])
               ->whereIn('card_no',$numArr)->count('id');
       if($operatorCardCount != $numArrCount){
           throw new CommonException('107107');
       }
       //验证是否有卡号存在于未支付的订单中
       $where = [];
       $where[] = ['ro.customer_id','=',$loginUser['customer_id']];
       $where[] = ['po.status','=',1];
       $payOrderCount = TCPayOrderModel::from('c_pay_order as po')
               ->leftjoin('c_renew_order as ro','po.trade_no','ro.trade_no')
               ->where($where)
               ->count('po.id');
       if($payOrderCount > 0){
           throw new CommonException('107108');
       }
       //套餐续费时验证是否与当前套餐一致
       
       $cardFlowPackage = CardPackageModel::where(['package_type'=>'FLOW'])->whereIn('card_no',$numArr)
               ->distinct('package_id')->get(['package_id']);
       if(count($cardFlowPackage) > 1 ){
           throw new CommonException('107109');
       }
       $cardFlowPackageCount = CardPackageModel::where(['package_type'=>'FLOW'])
               ->whereIn('card_no',$numArr)->count('id');
       if($cardFlowPackageCount != $numArrCount){
          $cardFlowPackageHis = $this->getCardPackageHisPid($input['numStr'],'FLOW');
          if(count($cardFlowPackageHis) > 1){
              throw new CommonException('107109');
          }
          if(count($cardFlowPackage) > 0){
              if($cardFlowPackage[0]->package_id != $cardFlowPackageHis[0]->package_id){
                  throw new CommonException('107109');
              }
          }
       }
       if(count($cardFlowPackage) > 0){
           $oldSmsPackageId = $cardFlowPackage[0]->package_id;
       }else{
           $oldSmsPackageId = $cardFlowPackageHis[0]->package_id;
       }
       return $oldSmsPackageId;
    }
    /*
     * 创建续费订单：套餐续费/升级
     */
    public function addPlanRenew($input,$loginUser){
       $oldFlowcardPackageId = $this->renewValidate($input, $loginUser);
       //查询所选资费计划的套餐ID
       $templatePackageId = TCOrderTemplateModel::where('id',$input['templateOrderId'])->first(['flow_package_id']);
       if($input['feesType'] == '1001'){//续费
           if($oldFlowcardPackageId != $templatePackageId->flow_package_id){
               throw new CommonException('107111');
           }
       }else{//升级
           $cardPackage = Package::where('id',$oldFlowcardPackageId)->first(['time_unit','consumption']);
           $cardPackageSize = bcdiv($cardPackage->consumption,$cardPackage->time_unit,'2');
           $templatePackage = Package::where('id',$templatePackageId->flow_package_id)->first(['time_unit','consumption']);
           $templatePackageSize = bcdiv($templatePackage->consumption,$templatePackage->time_unit,'2');
           if($templatePackageSize <= $cardPackageSize){
               throw new CommonException('107112');
           }
       }
    }
    /*
     * 订单续费
     */
   public function addOrderRenew($input,$loginUser){
       if($input['type'] == 'order'){
          $orderOperatorType = Order::where(['order_no'=>$input['orderNo']])->first(['operator_type']);
          if($orderOperatorType->operator_type != $input['operatorType']){
               throw new CommonException('107110');
          }
       }else{
          $oldFlowcardPackageId = $this->renewValidate($input, $loginUser);
          //$packageId = CardModel::whereIn('card_no',$numArr)->distinct()->get(['order_id']);
       }
   }
    /*
     * 生成续费记录单
     */
   public function createRenewOrder($input,$loginUser){
       $numArr = explode(',',$input['numStr']);//提交过来的卡片数组
       $numArrCount = count($numArr);//提交过来的卡片数量
       $renewOrderId = getUuid();
       $tradeNo = getOrderNo('XF');
       $saleOrderData = SaleOrderModel::where('id',$input['orderId'])->first();
       //$sqlObject = "CardModel::";
       if($input['renewalWay'] == 1){//订单续费
          if($input['type'] == 'orderNo'){
             $orderNum = $saleOrderData->order_num;
             $cardNoArr = CardModel::where('order_id',$input['orderId'])
                       ->get(['id','card_no','status','iccid']);
          }else{
             $orderNum = $numArrCount;
             $cardNoArr = CardModel::whereIn('card_no',$numArr)->get(['id','card_no','status','iccid']);
          }
          $amount = $saleOrderData->amount;
          
       }else{//资费计划续费
           $orderNum = $numArrCount;
           $cardNoArr = CardModel::whereIn('card_no',$numArr)->get(['id','card_no','status','iccid']);
           //查询所选资费计划的套餐ID
           $orderTemplateData = TCOrderTemplateModel::where('id',$input['templateOrderId'])
                   ->first();
           $amount = $orderTemplateData->flow_card_price*$orderNum+$orderTemplateData->sms_card_price*$orderNum;
           if($input['feesType'] == '1001'){//套餐续费
               $feesType = 3;
               //$amount = 0;
           }else{//升级套餐
               $feesType = 2;
               //$amount = 0;
           }
       }
       $renewOrderdata = [
                'id'=> $renewOrderId,
                'order_id'=>$saleOrderData->id,
                'trade_no'=>$tradeNo,
                'order_num'=>$orderNum,
                'status'=>1,//已提交
                'customer_id'=>$saleOrderData->customer_id,
                'customer_name'=>$saleOrderData->customer_name,
                'contacts_name'=>$saleOrderData->contacts_name,
                'contacts_mobile'=>$saleOrderData->contacts_mobile,
                'operator_type'=>$saleOrderData->operator_type,
                'industry_type'=>$saleOrderData->industry_type,
                'card_type'=>$saleOrderData->card_type,
                'standard_type'=>$saleOrderData->standard_type,
                'model_type'=>$saleOrderData->model_type,
                'is_flow'=>$saleOrderData->is_flow, 
                'is_sms'=>$saleOrderData->is_sms,
                'is_voice'=>$saleOrderData->is_voice,
                'flow_package_id'=>$saleOrderData->flow_package_id,
                'sms_package_id'=>$saleOrderData->sms_package_id,
                'voice_package_id'=>$saleOrderData->voice_package_id,
                'real_name_type'=>$saleOrderData->real_name_type,
                'silent_date'=>$saleOrderData->silent_date,
                'pay_type'=>$saleOrderData->pay_type,
                'amount'=>$amount,
                'create_time'=>date('Y-m-d H:i:s',time()),
                'describe'=>$saleOrderData->describe,
                'fees_type'=>3,//平台续费
                'flow_expiry_date'=>$saleOrderData->flow_expiry_date,
                'sms_expiry_date'=>$saleOrderData->sms_expiry_date,
                'voice_expiry_date'=>$saleOrderData->voice_expiry_date,
                'is_open_card'=>$saleOrderData->is_open_card,
                'express_arrive_day'=>$saleOrderData->express_arrive_day,
                'flow_card_price'=>$saleOrderData->flow_card_price,
                'sms_card_price'=>$saleOrderData->sms_card_price,
                'voice_card_price'=>$saleOrderData->voice_card_price,
                'is_special'=>$saleOrderData->is_special,
                'is_pool'=>$saleOrderData->is_pool,
                'is_overflow_stop'=>$saleOrderData->is_overflow_stop,
                'payment_method'=>$saleOrderData->payment_method,
                'effect_type'=>$saleOrderData->effect_type,
                'effect_type'=>$saleOrderData->effect_type,
                'is_imsi'=>$saleOrderData->is_imsi,
                'resubmit'=>$saleOrderData->resubmit,
                'create_user_id'=>$loginUser['id'],
                'package_type'=>$saleOrderData->package_type,
                'card_style'=>$saleOrderData->card_style
          ];
            
          $cardFileData = [];
          $renewDetailCard = [];
          $payOrder = [];
          foreach($cardNoArr as $k=>$v){
                //
            $renewDetailCard[$k]['id'] = $v->id;
            $renewDetailCard[$k]['card_no'] = $v->card_no;
            $renewDetailCard[$k]['renew_id'] = $renewOrderId;
            //
            $cardFileData[$k]['id'] = getUuid();
            $cardFileData[$k]['card_no'] = $v->card_no;
            $cardFileData[$k]['iccid'] = $v->iccid;
            $cardFileData[$k]['old_package'] = $input['oldPackageId'];
            $cardFileData[$k]['new_package'] = $input['newPackageId'];
            $cardFileData[$k]['card_status'] = $v->status;
            $cardFileData[$k]['change_type'] = 6;
            $cardFileData[$k]['operate_user_id'] = $loginUser['id'];
            $cardFileData[$k]['operate_user_name'] = $loginUser['real_name'];
            $cardFileData[$k]['operate_time'] = date('Y-m-d H:i:s',time());
            $cardFileData[$k]['change_describe'] = '卡片续费';
            //
            $payOrder[$k]['id'] = getUuid();
            $payOrder[$k]['trade_no'] = $tradeNo;
            $payOrder[$k]['card_id'] = $v->id;
            $payOrder[$k]['card_no'] = $v->card_no;
            $payOrder[$k]['package_id'] = $input['newPackageId'];
            $payOrder[$k]['package_name'] = $input['newPackageId'];
            $payOrder[$k]['status'] = 1;
            $payOrder[$k]['total_fee'] = $saleOrderData->amount;
            $payOrder[$k]['pay_time'] = date('Y-m-d H:i:s',time());
            $payOrder[$k]['create_time'] = date('Y-m-d H:i:s',time());
            $payOrder[$k]['order_type'] = 2;//卡片续费
                
          }
          DB::beginTransaction();
          $resRenewOrder = TCRenewOrderModel::insert($renewOrderdata);
          $resRenewOrderDetail = TCRenewOrderDetailModel::insert($renewDetailCard);
          $resCardFile = TCCardFileModel::insert($cardFileData);
          $resPayOrder = TCPayOrderModel::insert($payOrder);
          $desc = $saleOrderData->describe.'/接口续费于：'.date('Y-m-d H:i:s',time());
          $resSaleOrder = SaleOrderModel::where('id',$input['orderId'])
                    ->update(['fees_type'=>2,'describe'=>$desc]);
          if($resRenewOrder == TRUE && $resRenewOrderDetail == TRUE && $resCardFile == TRUE 
                    && $resPayOrder == TRUE && $resSaleOrder == 1){
              DB::commit();
          }else{
              DB::rollBack();
              throw new CommonException('107113');
          }
       
       
        
        
        
        
        
        
        
    }
    /*
    * 获取历史生效套餐的套餐ID
    */ 
   public function getCardPackageHisPid($cardStr,$packageType){
       $sql = "SELECT DISTINCT t_c_card_package_his.package_id
                FROM  t_c_card_package_his,(
			select card_no,valid_date from t_c_card
                        where card_no in ( ".$cardStr." ) 
                        and status = 5
                    ) tt
                where t_c_card_package_his.card_no = tt.card_no
                and t_c_card_package_his.failure_date = tt.valid_date
                and t_c_card_package_his.package_type = ".$packageType."
                and fees_type <> '1003'";
       $res = DB::select($sql);
       return $res;
   }
   
   
   
   
    
}
