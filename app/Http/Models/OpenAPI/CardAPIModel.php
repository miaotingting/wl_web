<?php

namespace App\Http\Models\OpenAPI;

use App\Http\Models\BaseModel;

use App\Http\Models\Customer\Customer;
use App\Http\Models\Operation\Package;
use App\Http\Models\Card\CardPackageModel;


class CardAPIModel extends BaseModel
{
    protected $table = 'c_card';
    /*
     * 获取某个卡片的详细信息
     */
    public function getCardInfo($input){
        $customerData = (new Customer)->getCustomerData($input['clientId']);
        if(empty($customerData)){
            return ['status'=>FALSE,'code'=>600002,'msg'=>'调用能力ID错误'];
        }
        $customerCode = $customerData->customer_code;
        $setSign = md5('clientId='.$input['clientId'].'cardNo='.$input['cardNo'].$customerCode);
        if($setSign != $input['sign']){
            return ['status'=>FALSE,'code'=>600003,'msg'=>'签名验证失败'];
        }
        $cardIdInfo = $this->where('card_no',$input['cardNo'])
                ->orWhere('iccid',$input['cardNo'])->first(['id']);
        if(empty($cardIdInfo)){
            return ['status'=>FALSE,'code'=>600004,'msg'=>'卡号错误'];
        }
        
        $sqlObject = $this->from('c_card as card')
                    ->leftJoin('c_sale_order as order','card.order_id','order.id')
                    ->orWhere(['card.iccid'=>$input['cardNo'],'card.card_no'=>$input['cardNo']]);
        if($customerData->level == 1){//一级客户
            $sqlObject = $sqlObject->where(['order.customer_id'=>$input['clientId']]);
        }else{//如果二级及以下客户
            
            $eData = array();
            $childCustomerID = (new Customer)->getAllChildID($eData, $input['clientId']);//查找所有下级客户
            if(empty($childCustomerID)){
                $sqlObject = $sqlObject->where(['card.customer_id'=>$input['clientId']]);
            }else{
                $childCustomerID[]['id'] = $input['clientId'];
                $sqlObject = $sqlObject->whereIn('card.customer_id',$childCustomerID);
            }
        }
        $cardData = $sqlObject->first(['card.id' ,'card.card_no','card.iccid','card.operator_type','card.status',
                        'card.machine_status','card.sale_date','card.active_date','card.valid_date',
                        'card.imsi','card.card_account']);
        if(empty($cardData)){
            return ['status'=>FALSE,'code'=>600005,'msg'=>'调用能力id和卡号不匹配'];
        }
        $cardID = $cardData->id;
        if(!empty($cardData->operator_type)){
            $cardData->operator_type = Package::getTypeDetail('operator_type',$cardData->operator_type)['name'];//运营商类型
        }
        if(!empty($cardData->status)){
            $cardData->status = Package::getTypeDetail('card_status',$cardData->status)['name'];//运营商类型
        }
        if(!empty($cardData->machine_status)){
            $cardData->machine_status = Package::getTypeDetail('machine_status',$cardData->machine_status)['name'];//运营商类型
        }
        unset($cardData->id);
        $result['card'] = $cardData->toArray();
        $packageData = CardPackageModel::where(['card_id'=>$cardID])
                ->get(['package_type','total','used','enable_date','failure_date']);
        
        if($packageData->isEmpty()){
            $result['package'] = [];
            return ['status'=>TRUE,'data'=>$result];
        }
        foreach($packageData as $value){
            $value->package_type = Package::getTypeDetail('package_type',$value->package_type)['name'];//套餐类型
            $value->residue = $value->used>$value->total ? 0 : bcsub($value->total,$value->used,2);//剩余量
        }
        $result['package'] = $packageData->toArray();
//        dump($result);exit;
        return ['status'=>TRUE,'data'=>$result];
    }
    
   
    
    
    

}







