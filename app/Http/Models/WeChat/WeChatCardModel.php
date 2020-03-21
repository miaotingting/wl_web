<?php

namespace App\Http\Models\WeChat;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Operation\Package;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Sms\SmsSendLogModel;


class WeChatCardModel extends BaseModel
{
    protected $saveNumPay = 2;//新平台停机保号费
    /*
     * 微信查询卡片信息
     */
    public function getCardInfo($id){
        /*if(empty($loginUser)){
            throw new CommonException('300001');
        }*/
        $cardModel = new CardModel();
        $sql = $cardModel->getOneCardSql($id);
        $cardData = DB::select($sql);
        if(empty($cardData)){
            throw new CommonException('106021');//卡号或ICCID错误
        }
        $result = array();
        $result['card_no'] = $cardData[0]->card_no;
        $result['iccid'] = $cardData[0]->iccid;
        $result['operator_type'] = $cardData[0]->operator_type;//运营商类型
        $result['status'] = Package::getTypeDetail('card_status', $cardData[0]->status)['name'];//卡片状态
        $result['machine_status'] = Package::getTypeDetail('machine_status', $cardData[0]->machine_status)['name'];//活动状态
        $result['valid_date'] = $cardData[0]->valid_date;
        $date = date('Y-m-d',time());
        if($cardData[0]->valid_date > $date){
            $result['residueMonth']= $this->getMonthNum($date,$cardData[0]->valid_date);
        }else{
            $result['residueMonth']= 0;
        }
        $result['card_account'] = $cardData[0]->card_account;
        //判断是否是特定用户
        $specialCustomerId = config('info.special_customer_id');//（env里面取出的）
        $isNoSpecialCustomer = 2;//不是特定客户
        if($cardData[0]->order_customer_id == $specialCustomerId){
            //如果是特定用户
            $isNoSpecialCustomer = 1;//是特定客户
        }
        $result['isNoSpecialCustomer'] = (string)$isNoSpecialCustomer;
        //套餐信息
        if(!empty($cardData[0]->flow_package_name)){
            $result['flow_package'] = $cardModel->setPackageData('flow', $cardData[0],'weChat');
        }
        if(!empty($cardData[0]->sms_package_name)){
            $result['sms_package'] = $cardModel->setPackageData('sms', $cardData[0],'weChat');
        }
        if(!empty($cardData[0]->voice_package_name)){
            $result['voice_package'] = $cardModel->setPackageData('voice', $cardData[0],'weChat');
        }
        return $result;
    }
    /*
     * 查询剩余月数
     */
    function getMonthNum( $date1, $date2, $tags='-' ){
        $date1 = explode($tags,$date1);
        $date2 = explode($tags,$date2);
        //return abs($date1[0] - $date2[0]) * 12 + abs($date1[1] - $date2[1]);
        if($date1[1]<$date2[1]){ //判断月份大小，进行相应加或减
           $month_number= abs($date1[0] - $date2[0]) * 12 + abs($date1[1] - $date2[1]);
      }else{
           $month_number= abs($date1[0] - $date2[0]) * 12 - abs($date1[1] - $date2[1]);
      }
      return $month_number;

    }
    /*
     * 卡片的短信日志
     */
    public function cardList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $where = [];
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search);
        }
        $where[] = ['customer_id','=',$loginUser['customer_id']];
        $data = $this->getCardPageData($where,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 短信列表
     */
    public function getCardPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_card as card')
                ->leftJoin('sms_send_log as send','send.mobile','=','card.card_no')
                ->where($where);
        $count = $sqlObject->count('card.id');//总条数
        $data = $sqlObject->orderBy('card.created_at','DESC')
                ->offset($offset)->limit($pageSize)
                ->get(['card.card_no','send.content']);
        
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
        }
        //foreach($data as $value){
            /*$smsData = SmsSendLogModel::where('mobile',$value->card_no)
                    ->orderBy('send_time','DESC')->first(['content']);
            //print_r($smsData);exit;
            if(empty($smsData)){
                $value['content'] = '';
            }else{
                $value['content'] = $smsData->content;
            }*/
        //}
        $result['data'] = $data;
        return $result;
    }
    /*
     * 获取where条件
     */
    public function getSearchWhere($input){
        
        $where = array();
        if(isset($input['cardNo']) && !empty($input['cardNo'])){
            $where[] = ['card_no', 'like', '%'.$input['cardNo'].'%'];
        }
        return $where;
    }
    /*
     * 获取卡片充值前显示信息
     */
    public function getCardPayInfo($id){
        $pay_code = getComOrderNo('WXHY');
        //$cardModel = new CardModel();
        $cardData = CardModel::leftJoin('c_sale_order as order','c_card.order_id','order.id')
                ->leftJoin('c_package as p','p.id','=','order.flow_package_id')
                ->leftJoin('sys_station_config as s','s.id','=','c_card.station_id')
                ->where(function ($query) use ($id) {
                    $query->where('c_card.card_no',$id)
                        ->orWhere('c_card.iccid',$id);
                    })
                ->first(['c_card.card_no','c_card.iccid','c_card.valid_date','c_card.card_account',
                    'order.flow_expiry_date','order.flow_card_price','order.customer_id',
                    'p.package_name','s.platform_type','p.time_length']);
        if(empty($cardData)){
            throw new CommonException('106021');//卡号或ICCID错误
        }
        $cardValidDate = $cardData->valid_date;//服务期止
        $saveNumPay = 0;//停机保号费
        $specialCustomerId = config('info.special_customer_id');//（env里面取出的）
        $isNoSpecialCustomer = 2;//不是特定客户
        $validDate = date('Y-m-01', strtotime($cardValidDate));//卡片的服务期止的当月的第一天
        $validMonthFirstDay = date("Y-m-d", strtotime("+1 months", strtotime($validDate)));
        $validMonthLastDay = date('Y-m-d 23:59:59', strtotime("$validMonthFirstDay +1 month -1 day"));
        if($cardData->platform_type == 2){
            //新平台
            if(time() > strtotime($validMonthLastDay)){
                $saveNumPay = $this->saveNumPay;
            }
        }
        if(time() > strtotime($validMonthLastDay)){
            if($cardData->customer_id == $specialCustomerId){
                //如果是特定用户
                $cardValidDate = date('Y-m-t', strtotime('-1 month'));
                //$isNoSpecialCustomer = 1;//是特定客户
            }
        }
        if($cardData->customer_id == $specialCustomerId){
            //如果是特定用户
            $isNoSpecialCustomer = 1;//是特定客户
        }
        $arrearage = 0;//欠费金额
        if($cardData->card_account < 0){
            $arrearage = abs($cardData->card_account);
        }
        //生效套餐的月数（开通时效*套餐时长）
        $flowMonth = $cardData->flow_expiry_date * $cardData->time_length;
        $flow_price = round($cardData->flow_card_price/$flowMonth,2);
        $result = array();
        $result['order_no'] = $pay_code;//订单号
        $result['card_no'] = $cardData->card_no;//卡号
        $result['iccid'] = $cardData->iccid;//iccid
        $result['flow_package_name'] = $cardData->package_name;//流量套餐名称
        $result['flow_package_price'] = (string)$flow_price;//流量套餐价格
        $result['valid_date'] = $cardValidDate;//服务期止
        $result['card_account'] = (string)$cardData->card_account;//卡账户余额
        $result['save_num_pay'] = (string)$saveNumPay;//停机保号费
        $result['isNoSpecialCustomer'] = (string)$isNoSpecialCustomer;//是否是特定客户
        $result['arrearage'] = (string)$arrearage;//欠费金额
        return $result;
    }
    

}







