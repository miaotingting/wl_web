<?php

namespace App\Http\Models\OpenAPI;

use App\Http\Models\BaseModel;

use App\Http\Models\Customer\Customer;


class TestAPIModel extends BaseModel
{
    /*
     * 获取签名
     */
    public function getSign($input){
        $customerData = (new Customer)->getCustomerData($input['clientId']);
        if(empty($customerData)){
            return ['status'=>FALSE,'code'=>'600002','msg'=>'客户ID错误'];
        }
        $sign = '';
        foreach($input as $key => $value){
            if($key == 'token'){
                continue;
            }
            $sign = $sign.$key.'='.$value;
        }
        
        $sign = $sign.$customerData->customer_code;
        //echo $sign;exit;
        return ['status'=>TRUE,'data'=>md5($sign)];
        
    }
    
    
   
    
    
    

}







