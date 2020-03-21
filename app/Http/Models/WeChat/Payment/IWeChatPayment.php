<?php

namespace App\Http\Models\WeChat\Payment;



Interface IWechatPayment
{
    
    /**
     * @return array
     */
    public function payment($amount, $openid, $body, $no);

}







