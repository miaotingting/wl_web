<?php

namespace App\Http\Models\WeChat\Payment;

use App\Exceptions\ValidaterException;
use App\Http\Models\BaseModel;
use EasyWeChat\Factory;

class WechatPayment extends BaseModel implements IWechatPayment
{
    protected $type;
    protected $app;

    function __construct()
    {
        $config = config('wechat.payment.default');
        $this->app = Factory::payment($config);
    }

    /**
     * @return array
     */
    public function payment($amount, $openid, $body, $no) {
        //调用微信支付统一下单api
        // $openid = $this->getOpenid($code);
        // dd($res);
        // $openid = $this->app->authCodeToOpenid($code);
        // dd($openid);
        // dump(intval($amount * 100));
        $amount = intval(bcmul(strval($amount), strval(100), 2));
        $datas = [
            'body' => $body,
            'out_trade_no' => $no,
            'total_fee' => $amount,  //单位是分
            // 'spbill_create_ip' => '123.12.12.123', // 可选，如不传该参数，SDK 将会自动获取相应 IP 地址
            // 'notify_url' => 'https://che.dev/wechat/payment', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
            'trade_type' => $this->type, // 请对应换成你的支付方式对应的值类型
            'openid' => $openid,
            ];
        $this->log(date('Y-m-d H:i:s').'续费微信支付，请求统一下单接口，参数：'.json_encode($datas, JSON_UNESCAPED_UNICODE));
        $result = $this->app->order->unify($datas);
        $this->log(date('Y-m-d H:i:s').'续费微信支付，统一下单接口返回：'.json_encode($result, JSON_UNESCAPED_UNICODE));
        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            return $result['prepay_id'];
        }
        $arr = [
            'code' => $result['err_code'],
            'msg' => $result['err_code_des'],
        ];
        //返回错误
        throw new ValidaterException(json_encode($arr));
        // dump($result);
        
    }
}







