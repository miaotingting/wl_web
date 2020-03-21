<?php

namespace App\Http\Models\WeChat;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use App\Exceptions\ValidaterException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Operation\Package;
use App\Http\Models\Card\CardModel;
use App\Http\Models\WeChat\Payment\WechatPaymentContext;

class TCPayOrderModel extends BaseModel
{
    protected $table = 'c_pay_order';
    public $timestamps = false;

    const STATUS_WAIT = 1;  //未支付
    const STATUS_SUCCESS = 2; //成功
    const STATUS_FAIL = 3; //失败

    /**
     * 调用微信接口获取openid
     * @param $code 前端传过来的授权码
     */
    function getOpenid($code) {
        $params['appid'] = 'wx5eb6da47ed1e5a08';
        $params['secret'] = '2832e1826505441b19732d0ba30c3a62';
        $params['code'] = $code;
        $params['grant_type'] = 'authorization_code';
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
        // $url='https://api.weixin.qq.com/sns/oauth2/access_token?appid=wx5eb6da47ed1e5a08&secret=2832e1826505441b19732d0ba30c3a62&code='.$code.'&grant_type=authorization_code';
        $res = $this->getApi($url, $params);
        $res = json_decode($res);
        if (property_exists($res,'openid')) {
            return $res->openid;
        } else {
            $arr = [
                'code' => $res->errcode,
                'msg' => $res->errmsg,
            ];
            throw new ValidaterException(json_encode($arr));
        }
        
    }
    

    /**
     * 创建微信支付的续费订单
     */
    public function add($prepayId, $no, $openid, $card_no, $card_id, $package_id, $package_name, $amount, $trade_type, $orderNum) {
        $this->id = getUuid('XF');
        $this->trade_no = $no;
        $this->openid = $openid;
        $this->card_id = $card_id;
        $this->card_no = $card_no;
        $this->package_id = $package_id;
        $this->package_name = $package_name;
        $this->transaction_id = $prepayId;
        $this->status = self::STATUS_WAIT;   //未支付
        $this->total_fee = $amount;
        $this->trade_type = $trade_type;
        // $this->bank_type = $bank_type;
        $this->pay_time = now();
        $this->create_time = now();
        $this->order_type = 2;  //卡片续费
        $this->order_num = $orderNum;
        $this->save();
    }

    /**
     * 发起续费，进行微信支付
     * @param $no 续费订单号
     * @param $openid openid
     * @param $cardNo 卡号
     * @param $month 续费几个月
     */
    public function renew($no, $openid, $cardNo, $month, $frontAmount) {
        //计算金额
        $cardData = CardModel::leftJoin('c_sale_order as order','c_card.order_id','order.id')
                ->leftJoin('c_package as p','p.id','=','order.flow_package_id')
                ->leftJoin('sys_station_config as s','s.id','=','c_card.station_id')
                ->where('c_card.card_no',$cardNo)
                ->first(['c_card.card_no','c_card.id','c_card.valid_date','c_card.card_account','c_card.customer_id',
                        'order.flow_expiry_date','order.flow_card_price',
                        'p.package_name','p.id as package_id','p.time_length',
                        's.platform_type']);

        
        if (empty($cardData)) {
            throw new CommonException(106016);
        }

        $flowMonth = $cardData->flow_expiry_date * $cardData->time_length;
        $flow_price = round($cardData->flow_card_price/$flowMonth,2);
        $amount = $this->getAmount($flow_price, $month, $cardData->valid_date, $cardData->platform_type, $cardData->card_account, $cardData->customer_id);
        // dd($amount);
        if (bccomp($frontAmount, $amount, 2) != 0) {
            //两边金额不一样
            throw new CommonException(107102);
        }
        //调用微信支付
        DB::beginTransaction();
        $wechatPaymentCtx = new WechatPaymentContext('jssdk');
        $res = $wechatPaymentCtx->payment($amount,$openid,'套餐续费-'.$cardData->package_name,$no);

        //创建微信支付订单
        $this->add($res['prepayId'], $no, $openid, $cardNo,$cardData->id, $cardData->package_id, $cardData->package_name, $amount, 'jsapi', $month);
        DB::commit();
        return $res;
    }

    /**
     * 计算支付金额函数
     * @param $price 每月单价
     * @param $month 几个月
     */
    private function getAmount($price, $month, $validDate, $type, $cardAccount, $customerId) {
        $amount = bcmul(strval($price), strval($month), 2);
        $this->log(date('Y-m-d H:i:s') . "【微信支付】续费时候单价：【{$price}】月份：【{$month}】金额：【{$amount}】");
        $config = config('info.special_customer_id');
        if ($customerId == $config) {
            //特定用户才打折
            if ($month >= 12) {
                //一年以上的续费打9折
                $amount = $this->onSale($amount, 0.9);
            } else  if ($month == 6) {
                //半年续费打95折
                $amount = $this->onSale($amount, 0.95);
            }
        }
        
        $this->log(date('Y-m-d H:i:s') . "【微信支付】续费时候单价：【{$price}】月份：【{$month}】【打折】后金额：【{$amount}】");
        //计算服务期止和现在月份的差
        $months = $this->getMonth($validDate);
        if ($months == 2 && $type == 2) {
            //新平台隔月续费需要缴纳2元停机保号费
            $amount = bcadd(strval($amount), '2', 2);
        }
        $this->log(date('Y-m-d H:i:s') . "【微信支付】续费时候单价：【{$price}】月份：【{$month}】【停机保号费】后金额：【{$amount}】");
        //如果卡欠费了，需要补缴
        if ($cardAccount < 0) {
            $amount += abs($cardAccount);
        }
        $this->log(date('Y-m-d H:i:s') . "【微信支付】续费时候单价：【{$price}】月份：【{$month}】【卡欠费】后金额：【{$amount}】");
        return $amount;
    }

    /**
     * 打折函数
     * @param $amount 金额
     * @param $sale 折扣
     */
    private function onSale($amount, $sale) {
        return bcmul(strval($amount), strval($sale), 2);
    }
}







