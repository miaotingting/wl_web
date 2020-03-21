<?php

namespace App\Http\Controllers\WeChat;

use App\Exceptions\CommonException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\WeChat\Payment\WechatPaymentContext;
use App\Http\Models\WeChat\TCPayOrderModel;
use App\Http\Models\WeChat\WeChatSmsModel;
use Illuminate\Support\Facades\Validator;

class WeChatPaymentController extends Controller
{
    protected $rules = [
        'no'=>'required',
        'openid'=>'required',
        'cardNo'=>'required',
        'month'=>'required',
    ];
    protected $messages = [
        'no.required'=>'单号为必填项',
        'openid.required'=>'openid为必填项',
        'cardNo.required'=>'卡号为必填项',
        'month.required'=>'续费月数为必填项',
    ];
    /**
     * 续费支付
     * 
     */
    public function renewPayment(Request $request, TCPayOrderModel $model)
    {
        try{
            //参数验证
            $this->valid($request);

            $no = $request->input('no');
            $openid = $request->input('openid');
            $cardNo = $request->input('cardNo');
            $month = $request->input('month');
            $amount = $request->input('amount');

            $result = $model->renew($no, $openid, $cardNo, $month, $amount);
            return $this->success($result);
        } catch (Exception $ex) {
        }
    }


    /**
     * 续费支付的微信回调接口
     * 
     */
    public function paymentCallback(Request $request)
    {
        try{
            $ctx = new WechatPaymentContext('jssdk');
            return $ctx->callback();
        } catch (Exception $ex) {
            
        }
    }


    /**
     * 获取openid
     */
    function getOpenid(Request $request,TCPayOrderModel $model) {
        try{
            $rules = [
                'code'=>'required',
            ];
            $messages = [
                'code.required'=>'code为必填项',
            ];
            //参数验证
            $this->valid($request, $rules, $messages);

            $code = $request->input('code');
            $openid = $model->getOpenid($code);
            return $this->success($openid);
        } catch (Exception $ex) {
            
        }
    }

    /**
     * 删除订单
     */
    function deleteOrder(Request $request,TCPayOrderModel $model) {
        try{
            $rules = [
                'no'=>'required',
            ];
            $messages = [
                'no.required'=>'单号为必填项',
            ];
            //参数验证
            $this->valid($request, $rules, $messages);

            $no = $request->input('no');
            $info = $model->where('trade_no',$no)->first();
            if ($info->status == TCPayOrderModel::STATUS_WAIT) {
                $model->where('trade_no',$no)->delete();
            }
            return $this->success(true);
        } catch (Exception $ex) {
            
        }
    }

    


}
