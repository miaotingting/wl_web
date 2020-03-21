<?php

namespace App\Http\Models\Admin;
use App\Http\Models\BaseModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Finance\CustomerAccountModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Card\TCCardDateUsedModel;
use App\Exceptions\CommonException;


class IndexModel extends BaseModel
{
    
    public function getIndex($loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $flowType = [];
        $smsType = [];
        if($loginUser['is_owner'] == 1){//网来员工
            $customerAccount = 0;//网来员工无账户余额
            
        }else{//客户用户
            $customerAccountData = CustomerAccountModel::where('id',$loginUser['customer_id'])->first(['balance_amount']);
            if(empty($customerAccountData)){
                $customerAccount = 0;
            }else{
                $customerAccount = $customerAccountData->balance_amount;
            }
            $normalWhere['customer_id'] = $loginUser['customer_id'];
            $activateWhere['customer_id'] = $loginUser['customer_id'];
            $haltWhere['customer_id'] = $loginUser['customer_id'];
            $anomalyWhere['customer_id'] = $loginUser['customer_id'];
            $flowType['card.customer_id'] = $loginUser['customer_id'];
            $smsType['card.customer_id'] = $loginUser['customer_id'];
            
        }
        $normalWhere['status'] = 2;//正常
        $normalCount = CardModel::where($normalWhere)->count('id');
        $activateWhere['status'] = 1;//沉默待激活
        $activateCount = CardModel::where($activateWhere)->count('id');
        $haltWhere['status'] = 3;//停机
        $haltCount = CardModel::where($haltWhere)->count('id');
        $anomalyWhere['status'] = 4;//异常
        $anomalyCount = CardModel::where($anomalyWhere)->count('id');
        //本月流量使用最多/最少的前五张卡
        $flowData = $this->getMonthFlowData($flowType);
        //本月短信使用最多最少的前五张卡
        $smsData = $this->getMonthsmsData($smsType);
        //卡片日用量图表信息
        $dayUsedData = $this->getDayUsedData($loginUser['customer_id']);
        //
        $result = array();
        $result['SIMStatus']['normalSIM'] = $normalCount;
        $result['SIMStatus']['activateSIM'] = $activateCount;
        $result['SIMStatus']['haltSIM'] = $haltCount;
        $result['SIMStatus']['anomalySIM'] = $anomalyCount;
        $result['SIMWarning']['normalSIM'] = $normalCount;
        $result['SIMWarning']['flowWarning'] = 0;
        $result['SIMWarning']['smsWarning'] = 0;
        $result['SIMWarning']['haltWarning'] = 0;
        $result['accountStatus']['balanceAmount'] = $customerAccount;
        $result['accountStatus']['expectedExcess'] = 0;
        $result['maxFlow'] = $flowData['maxFlowFiveCardData'];
        $result['maxSms'] = $smsData['maxSmsFiveCardData'];
        $result['minFlow'] = $flowData['minFlowFiveCardData'];
        $result['minSms'] = $smsData['minSmsFiveCardData'];
        $result['dayUsed'] = $dayUsedData;//流量/短信日用量
        return $result;
        
    }
    /*
     * 本月流量使用最多/最少的前五张卡
     */
    public function getMonthFlowData($flowType){
        $flowType['cp.package_type'] = 'flow';
        $flowType['card.status'] = 2;
        /*$sqlObject->select(DB::raw('SUM(t_c_card_date_used.flow_used) as total_flow_used'),
                        DB::raw('SUM(t_c_card_date_used.sms_used) as total_sms_used'))
         * IFNULL(ROUND(cpFlow.allowance/1024,2),'0') as flowAllow
         */
        $maxFlowFiveCardData = DB::table('c_card as card')
                ->leftJoin('c_card_package as cp','cp.card_id','=','card.id')
                ->select('card.card_no',DB::raw("IFNULL(ROUND(t_cp.used/1024,2),'0') as used"))
                ->where($flowType)->orderBy('cp.used','DESC')->limit(5)->get();
        
        $minFlowFiveCardData = DB::table('c_card as card')
                ->leftJoin('c_card_package as cp','cp.card_id','=','card.id')
                ->select('card.card_no',DB::raw("IFNULL(ROUND(t_cp.used/1024,2),'0') as used"))
                ->where($flowType)->orderBy('used','ASC')->limit(5)->get();
        $result = array();
        $result['maxFlowFiveCardData'] = $maxFlowFiveCardData;
        $result['minFlowFiveCardData'] = $minFlowFiveCardData;
        return $result;
    }
    /*
     * 本月短信使用最多/最少的前五张卡
     */
    public function getMonthSmsData($smsType){
        $smsType['cp.package_type'] = 'sms';
        $smsType['card.status'] = 2;
        $maxSmsFiveCardData = DB::table('c_card as card')
                ->leftJoin('c_card_package as cp','cp.card_id','=','card.id')
                ->where($smsType)->orderBy('used','DESC')->limit(5)->get(['card.card_no','cp.used']);
        $minSmsFiveCardData = DB::table('c_card as card')
                ->leftJoin('c_card_package as cp','cp.card_id','=','card.id')
                ->where($smsType)->orderBy('used','ASC')->limit(5)->get(['card.card_no','cp.used']);  
        $result = array();
        $result['maxSmsFiveCardData'] = $maxSmsFiveCardData;
        $result['minSmsFiveCardData'] = $minSmsFiveCardData;
        return $result;
    }
   /*  public function getDayUsedData($customerId){
        $reuslt = [];
        for ($i = 8; $i >= 2; $i--) {
            $dayRes = $this->getOneDayUsedData(date("Y-m-d", strtotime("-$i day")),$customerId); 
            $reuslt['date'][] = date("d", strtotime("-$i day"));
            $reuslt['smsUsed'][] = array_has($dayRes, 'total_sms_used') ? $dayRes['total_sms_used'] : 0;
            $reuslt['flowUsed'][] = array_has($dayRes, 'total_flow_used') ? $dayRes['total_flow_used'] : 0;
        }
        return $reuslt;
        
    }
    public function getOneDayUsedData($day,$customerId){
        $result = array();
        $sqlObject = TCCardDateUsedModel::
                leftJoin('c_card as card','c_card_date_used.card_no','card.card_no');
        if(empty($customerId)){
            $sqlObject = $sqlObject->where(['c_card_date_used.use_date'=>$day]);
        }else{
            $sqlObject = $sqlObject->where(['c_card_date_used.use_date'=>$day,'card.customer_id'=>$customerId]);
        }   
        //SUM(t_c_card_date_used.flow_used)
        //IFNULL(ROUND(SUM(card_used.flow_used)/1024,2),'0')
        $dayUsedCount = $sqlObject->select(DB::raw("IFNULL(ROUND(SUM(t_c_card_date_used.flow_used)/1024,2),'0') as total_flow_used"),
                        DB::raw('SUM(t_c_card_date_used.sms_used) as total_sms_used'))
                ->first();
        
        if(empty($dayUsedCount->total_flow_used)){
            $result['total_flow_used'] = 0;
        }else{
            $result['total_flow_used'] = $dayUsedCount->total_flow_used;
        }
        if(empty($dayUsedCount->total_sms_used)){
            $result['total_sms_used'] = 0;
        }else{
            $result['total_sms_used'] = (int)$dayUsedCount->total_sms_used;
        }
        
        
        return $result;
    } */
    
    public function getDayUsedData($customerId){
        $reuslt = [];
        $beginDate = date('Y-m-d',strtotime("-8 day"));
        $endDate = date('Y-m-d',strtotime("-2 day"));
        $sqlObject = TCCardDateUsedModel::leftJoin('c_card as card','c_card_date_used.card_no','card.card_no');
        if(empty($customerId)){
            $sqlObject = $sqlObject->whereBetween('c_card_date_used.use_date',[$beginDate,$endDate]);
        }else{
            $sqlObject = $sqlObject->where('card.customer_id',$customerId)
                                   ->whereBetween('c_card_date_used.use_date',[$beginDate,$endDate]);
        }   
        $usedByDate = $sqlObject->groupBy('c_card_date_used.use_date')->get(['c_card_date_used.use_date',
                        DB::raw("IFNULL(ROUND(SUM(t_c_card_date_used.flow_used)/1024,2),'0') as total_flow_used"),
                        DB::raw('SUM(t_c_card_date_used.sms_used) as total_sms_used')])->toArray();
        $i = 8;
        foreach($usedByDate as $key=>$perDay){
            $reuslt['date'][] = date("d", strtotime("-$i day"));
            $reuslt['smsUsed'][] = array_has($perDay, 'total_sms_used') ? $perDay['total_sms_used'] : 0;
            $reuslt['flowUsed'][] = array_has($perDay, 'total_flow_used') ? $perDay['total_flow_used'] : 0;
            $i--;
        }
        return $reuslt;
        
    }
    
    
    
}
