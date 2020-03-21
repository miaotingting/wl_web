<?php

namespace App\Http\Controllers\WeChat;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redis;
use App\Http\Models\Admin\User;
use App\Http\Models\Customer\Customer;
use App\Http\Models\WeChat\WeChatCardModel;

class WeChatCardController extends Controller
{
    /**
     * 获取卡片信息
     * get.api/WeChat/getCardInfo/{卡号或ICCID}
     */
    public function getCardInfo($id)
    {
        try{
            $result = (new WeChatCardModel)->getCardInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }
    /*
     * 卡片的短信日志
     */
    public function cardList(Request $request){
        try{
            $result = (new WeChatCardModel)->cardList($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 充值续费显示卡片信息
     */
    public function getCardPayInfo($id)
    {
        try{
            $result = (new WeChatCardModel)->getCardPayInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }

}
