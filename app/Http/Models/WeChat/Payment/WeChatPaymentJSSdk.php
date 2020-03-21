<?php

namespace App\Http\Models\WeChat\Payment;

use App\Http\Models\BaseModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Order\Renew\RenewCtx;
use App\Http\Models\WeChat\TCPayOrderModel;
use EasyWeChat\Factory;
use Illuminate\Support\Facades\DB;

class WechatPaymentJSSdk extends WechatPayment
{
    protected $type='JSAPI';

    /**
     * @return array
     */
    public function payment($amount, $openid, $body, $no) {
        $prepayId = parent::payment($amount, $openid, $body, $no);
        $jssdk = $this->app->jssdk;
        
        $this->log(date('Y-m-d H:i:s').'续费微信支付，请求jssdk,perpayId：'.$prepayId);
        $config = $jssdk->sdkConfig(strval($prepayId)); // 返回数组
        $config['prepayId'] = $prepayId;
        $this->log(date('Y-m-d H:i:s').'续费微信支付，请求jssdk,返回参数：'.json_encode($config, JSON_UNESCAPED_UNICODE));
        // dump($config);
        return $config;
    }


    /**
     * @return array
     */
    public function callback() {
        $response = $this->app->handlePaidNotify(function($message, $fail){
            $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付回调】,参数：'.json_encode($message, JSON_UNESCAPED_UNICODE));
            if ($message['return_code'] === 'SUCCESS') { // return_code 表示通信状态，不代表支付状态
                //查询订单
                $payOrderModel = new TCPayOrderModel;
                $payOrder = $payOrderModel->where('trade_no', $message['out_trade_no'])->first();
                if (empty($payOrder)) {
                    return true;
                }
                //如果已经成功或者失败直接返回
                if ($payOrder->status == TCPayOrderModel::STATUS_SUCCESS || $payOrder->status == TCPayOrderModel::STATUS_FAIL) {
                    return true;
                }

                //查询这个卡的客户
                $card = CardModel::where('card_no', $payOrder->card_no)->first();
                
                //查询一下是否成功
                $order = $this->app->order->queryByTransactionId($message['transaction_id']);
                $time = date('Y-m-d H:i:s');
                $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付回调】,查询支付结果：'.json_encode($order, JSON_UNESCAPED_UNICODE));
                DB::beginTransaction();
                if ($order['return_code'] == 'SUCCESS') {
                    //查询成功判断支付结果
                    if ($order['result_code'] == 'SUCCESS') {
                        //支付成功
                        $payOrder->status = TCPayOrderModel::STATUS_SUCCESS;
                        $payOrder->end_time = $time;
                        $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付成功】,开始续费,客户id:'.$card->customer_id);
                        //执行续费逻辑
                        $renewCtx = new RenewCtx($card->customer_id);
                        $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付成功】,开始续费,客户id:'.$card->customer_id);
                        $renewCtx->renew($message['out_trade_no']);
                    } else {
                        //支付失败
                        $payOrder->status = TCPayOrderModel::STATUS_FAIL;
                        $payOrder->end_time = $time;
                        $payOrder->err_code = $order['err_code'];
                        $payOrder->err_code_desc = $order['err_code_des'];
                    }
                } else {
                    //查询失败使用当前微信返回的支付结果
                    if ($message['result_code'] == 'SUCCESS') {
                        //支付成功
                        $payOrder->status = TCPayOrderModel::STATUS_SUCCESS;
                        $payOrder->end_time = $time;

                        //执行续费逻辑
                        $renewCtx = new RenewCtx($card->customer_id);
                        $renewCtx->renew($message['out_trade_no']);
                    
                    } elseif ($message['result_code'] == 'FAIL') {
                        // 用户支付失败
                        $payOrder->status = TCPayOrderModel::STATUS_FAIL;
                        $payOrder->end_time = $time;
                        $payOrder->err_code = $message['err_code'];
                        $payOrder->err_code_desc = $message['err_code_des'];
                    }

                }
                $this->log(date('Y-m-d H:i:s').'【续费jsapi微信支付回调】,更新支付订单：'.json_encode($payOrder, JSON_UNESCAPED_UNICODE));
                $payOrder->save();
                DB::commit();
                
            } else {
                return $fail('通信失败，请稍后再通知我');
            }
        
            return true; // 返回处理完成
        });
        return $response;
    }
}







