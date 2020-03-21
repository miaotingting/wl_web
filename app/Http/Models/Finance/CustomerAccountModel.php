<?php

namespace App\Http\Models\Finance;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\User;
use App\Exceptions\CommonException;

class CustomerAccountModel extends BaseModel
{
    protected $table = 'sys_customer_account';
    
    /*
     * 获取用户账户账户余额
     */
    public function getBalanceAmount($id,$type='user'){
        if($type == 'user'){
            $data = User::where('id',$id)->first(['customer_id']);
            if(empty($data)){
                throw new CommonException('102003');
            }
            $customerId = $data->customer_id;
        }else{
            $customerId = $id;
        }
        $amount = $this->where('id',$customerId)->first(['balance_amount']);
        if(empty($amount)){
            return '0.00';
        }
        return $amount->balance_amount;
    }
   
    
}
