<?php

namespace App\Http\Models\OpenAPI;

use App\Http\Models\BaseModel;

use App\Http\Models\Customer\Customer;
use App\Http\Models\API\ChinaMobileAPI_Shandong;
use App\Http\Models\Card\CardModel;
use App\Http\Models\API\APIFactory;
use App\Exceptions\CommonException;
use App\Http\Models\Sms\SmsSendingModel;
use App\Http\Models\Sms\SmsSendLogModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Admin\TypeDetailModel;


class SmsAPIModel extends BaseModel
{
    
    /*
     * 发送短信
     */
    public function smsSend($input){
        
        $customerData = (new Customer)->getCustomerData($input['clientId']);
        if(empty($customerData)){
            return ['status'=>FALSE,'code'=>600002,'msg'=>'调用能力ID错误'];
        }
        $customerCode = $customerData->customer_code;
        $setSign = md5('clientId='.$input['clientId'].'mobile='.$input['mobile'].'content='.$input['content'].$customerCode);
        //echo 'clientId'.$input['clientId'].'mobile'.$input['mobile'].'content'.$input['content'].$customerCode;exit;
        if($setSign != $input['sign']){
            return ['status'=>FALSE,'code'=>600003,'msg'=>'签名验证失败'];
        }
        
        $cardStr = trim($input['mobile'],',');
        $cardArr = explode(',', $cardStr);
        $setCardCount = count($cardArr);//传过来的卡片数量
        if($setCardCount > 100){
            return ['status'=>FALSE,'code'=>600004,'msg'=>'卡片不能超过100张'];
        }
        
        $customerCardData = CardModel::whereIn('card_no',$cardArr)
                ->where('customer_id',$input['clientId'])->get(['id','iccid','card_no','station_id','gateway_id','card_type']);
        $customerCardCount = count($customerCardData);//属于他的卡片数量
        if($customerCardCount <= 0){
            return ['status'=>FALSE,'code'=>600005,'msg'=>'卡号错误'];
        }
        $data = array();
        $result = array();
        $i = 0;
        foreach($customerCardData as $key=>$value){
            $beforeThreeMinutes = $this->getSmsCount($value->card_no,$input['clientId'],$input['content']);
            if(!empty($beforeThreeMinutes)){
                continue;
            }
            $data['mobile'] = $value->card_no;
            $data['gateway_id'] = $value->gateway_id;
            $data['content'] = $input['content'];
            $data['create_time'] = date('Y-m-d H:i:s',time());
            $data['belong_customer_id'] = $input['clientId'];
            $data['card_type'] = $value->card_type;
            $data['iccid'] = $value->iccid;
            //$res = SmsSendingModel::insert($data);
            $insertId = SmsSendingModel::insertGetId($data);
            if($insertId >= 1){
                $result[$i]['mobile'] = $value->card_no;
                $result[$i]['msg_id'] = (string)$insertId;
                $i++;
            }
            
        }
        //print_r($result);exit;
        if(empty($result)){
            return ['status'=>FALSE,'code'=>999999,'msg'=>'提交失败'];
        }else{
            return ['status'=>TRUE,'data'=>$result];
        }
    }
    /*
     * 短信状态查询
     */
    public function getSmsStatus($input){
        $customerData = (new Customer)->getCustomerData($input['clientId']);
        if(empty($customerData)){
            return ['status'=>FALSE,'code'=>600002,'msg'=>'调用能力ID错误'];
        }
        $customerCode = $customerData->customer_code;
        $setSign = md5('clientId='.$input['clientId'].'msgId='.$input['msgId'].$customerCode);
        //echo $setSign;exit;
        if($setSign != $input['sign']){
            return ['status'=>FALSE,'code'=>600003,'msg'=>'签名验证失败'];
        }
        
        $setMsgIdStr = trim($input['msgId'],',');
        $setMsgIdArr = explode(',',$setMsgIdStr);
        if(count($setMsgIdArr) > 100){
            return ['status'=>FALSE,'code'=>600004,'msg'=>'一次最多查询100个号码'];
        }
        $result = array();
        $i = 0;
        //$smsSendingStatusGroup = TypeDetailModel::getDetailsByCode('sms_sending_status');
        //$smsSendLogStatusGroup = TypeDetailModel::getDetailsByCode('sms_send_log_status');
        
        foreach($setMsgIdArr as $value){
            $smsSendLogData = SmsSendLogModel::where(['id'=>$value,'belong_customer_id'=>$input['clientId']])
                    ->first(['status']);
            if(empty($smsSendLogData)){
                
                $smsSendingData = SmsSendingModel::where(['id'=>$value,'belong_customer_id'=>$input['clientId']])
                    ->first(['status']);
                if(!empty($smsSendingData)){
                    $result[$i]['msg_id'] = $value;
                    if($smsSendingData->status == 0){  //待提交
                        $result[$i]['status'] = '600104';
                    }elseif($smsSendingData->status == 1){  //提交失败
                        $result[$i]['status'] = '600105';
                    }else{  //监听失败
                        $result[$i]['status'] = '600106';
                    }
                    //$result[$i]['status'] = $smsSendingStatusGroup[$smsSendingData->status]['name'];
                    $i++;
                }
            }else{
                $result[$i]['msg_id'] = $value;
                if($smsSendLogData->status == 0){  //短信已提交
                    $result[$i]['status'] = '600101';
                }elseif($smsSendLogData->status == 1){  //发送成功
                    $result[$i]['status'] = '600102';
                }else{  //发送失败
                    $result[$i]['status'] = '600103';
                }
                //$result[$i]['status'] = $smsSendLogStatusGroup[$smsSendLogData->status]['name'];
                $i++;
            }
        }
        
        if(empty($result)){
            return ['status'=>FALSE,'code'=>999999,'msg'=>'查询失败'];
        }else{
            return ['status'=>TRUE,'data'=>$result];
        }
        
    }
    /*
     * 查询号码发送短信是否频繁
     */
    public function getSmsCount($mobile,$customerId,$content){
        $beforeThreeMinutes = date('Y-m-d H:i:s',time()-60*3);
        $data = SmsSendingModel::where([
            'mobile'=>$mobile,
            'belong_customer_id'=>$customerId,
            'content'=>$content
            ])->where('create_time','>=',$beforeThreeMinutes)->first(['id']);
        return $data;
    }
    
   
    
    
    

}







