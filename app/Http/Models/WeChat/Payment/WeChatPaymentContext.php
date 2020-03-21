<?php

namespace App\Http\Models\WeChat\Payment;



class WechatPaymentContext
{
    
    private $payment;

    function __construct(string $type)
    {
        switch($type) {
            case 'jssdk':
                $this->payment = new WechatPaymentJSSdk();
                break;
        }

    }
    
    function payment($amount, $openid, $body, $no) {
        return $this->payment->payment($amount, $openid, $body, $no);
    }

    function callback() {
        return $this->payment->callback();
    }
}







