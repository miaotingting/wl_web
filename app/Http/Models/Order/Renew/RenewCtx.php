<?php

namespace App\Http\Models\Order\Renew;

use App\Http\Models\BaseModel;

class RenewCtx extends BaseModel
{
    protected $renew;

    function __construct(string $id)
    {
        $config = config('info.special_customer_id');
        $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付成功】,开始逻辑:'.$config);
        if ($id == $config) {
            //特定用户
            $this->renew = new RenewSpecial;
            $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付成功】,特定用户:'.json_encode($this->renew, JSON_UNESCAPED_UNICODE));
        } else {
            //行业卡
            $this->renew = new RenewVocational;
            $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付成功】,行业卡用户:'.json_encode($this->renew, JSON_UNESCAPED_UNICODE));
        }
        
    }
    
    function renew($no) {
        $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付成功】,开始续费，单号:'.$no);
        return $this->renew->renew($no);
    }
    
}







