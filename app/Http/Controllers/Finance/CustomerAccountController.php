<?php

namespace App\Http\Controllers\Finance;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Finance\CustomerAccountModel;

class CustomerAccountController extends Controller
{
    
    /*
     * 获取客户账户余额
     */
    public function getBalanceAmount($userid)
    {
        try{
            $result = (new CustomerAccountModel)->getBalanceAmount($userid);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    

}
