<?php

namespace App\Http\Models\Order\Renew;


use App\Http\Models\Card\CardModel;
use App\Http\Models\WeChat\TCPayOrderModel;

class RenewSpecial extends Renew
{

    /**
     * @return array
     */
    public function renew($orderNo) {
        $order = TCPayOrderModel::where('trade_no', $orderNo)->first();
        $cardNo = $order->card_no;
        $packageId = $order->package_id;
        $month = $order->order_num;

        //查询卡片信息并增加服务期止，服务期止=服务期止+续费时间
        $card = CardModel::where('card_no', $cardNo)->first();
        $validDate = $card->valid_date;
        //计算服务期止和现在月份的差
        $months = $this->getMonth($validDate);
        $this->log(date('Y-m-d H:i:s').'【修改服务期】原来服务期：'.$validDate);
        // $this->log(date('Y-m-d H:i:s').'修改后服务期'.date('Y-m-d', strtotime('+' . $month . ' month', $validDate)));
        $card->valid_date = $months == 2 ? $this->dateToMonthLastDay(date('Y-m-01',strtotime($this->dateToMonthOne(date('Y-m-d')) . '-1 month')),$month) : $this->dateToMonthLastDay($validDate,$month);
        $this->log(date('Y-m-d H:i:s').'【修改服务期】原来服务期：'.$validDate.'修改后服务期'.$card->valid_date);
        $card->save();
        $this->card = $card;
        
        $startDate = date('Y-m-01');
        if ($months == 0) {
            //最后一个月续费的下月生效
            $startDate = date('Y-m-01',strtotime($this->dateToMonthOne(date('Y-m-d')) . '+1 month'));
        }
        //查询预生效套餐，看是否存在预生效套餐，如果存在直接延长，如果不存在重新插入预生效套餐
        $this->activityPackageFuture($cardNo, $packageId, $month, $startDate);
        //判断卡片状态是否停机，然后
        $this->cardStart($cardNo);        
            
        
        if ($months > 0) {
            //已经过期，次月或者隔月续费的时候，需要直接生效套餐
            $this->activityPackage($cardNo);
        }
        $this->log(date('Y-m-d H:i:s').'【开始创建续费订单】'.$months);
        //创建续费订单
        $this->createRenewOrder($orderNo, $order->total_fee, $month);
    }

    
}







