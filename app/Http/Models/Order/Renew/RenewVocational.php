<?php

namespace App\Http\Models\Order\Renew;

use App\Http\Models\Card\CardModel;
use App\Http\Models\Card\CardPackageModel;
use App\Http\Models\Card\TCCardPackageHisModel;
use App\Http\Models\WeChat\TCPayOrderModel;

class RenewVocational extends Renew
{

    protected $cardPackages;

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
        $card->valid_date = $this->dateToMonthLastDay($validDate,$month);
        $card->save();
        $this->card = $card;
        
        //计算服务期止和现在月份的差
        $months = $this->getMonth($validDate);

        //查询预生效套餐，看是否存在预生效套餐，如果存在直接延长，如果不存在重新插入预生效套餐
        $this->activityPackageFuture($cardNo, $packageId, $month, date('Y-m-01',strtotime($this->dateToMonthOne($validDate) . ' +1 month')));

        //判断卡片状态是否停机，然后
        $this->cardStart($cardNo);
        
        if ($months > 0) {
            //已经过期，次月或者隔月续费的时候，需要直接生效套餐
            $this->activityPackage($cardNo);
            //如果是隔月那么需要结算掉再次生效套餐
            if ($months == 2 && $this->package->time_length == 1) {
                $this->cardPackageToHis();
                //再次生效这个月的
                $this->activityPackage($cardNo);
            }
        }

        //创建续费订单
        $this->createRenewOrder($orderNo, $order->total_fee, $month / $this->package->time_length);
    }

    

    /**
     * 把生效套餐到达结束时间的卡片移动到生效套餐历史并删除生效套餐
     */
    private function cardPackageToHis() {
        foreach($this->cardPackages as $cardPackage) {
            $cardPackageHis = new TCCardPackageHisModel;
            $cardPackageHis->id = $cardPackage->id;
            $cardPackageHis->card_id = $cardPackage->card_id;
            $cardPackageHis->card_no = $cardPackage->card_no;
            $cardPackageHis->renew_id = $cardPackage->renew_id;
            $cardPackageHis->package_type = $cardPackage->package_type;
            $cardPackageHis->fees_type = $cardPackage->fees_type;
            $cardPackageHis->price = $cardPackage->price;
            $cardPackageHis->package_id = $cardPackage->package_id;
            $cardPackageHis->total = $cardPackage->total;
            $cardPackageHis->allowance = $cardPackage->allowance;
            $cardPackageHis->used = $cardPackage->used;
            $cardPackageHis->enable_date = $cardPackage->enable_date;
            $cardPackageHis->failure_date = $cardPackage->failure_date;
            $cardPackageHis->created_at = $cardPackage->created_at;
            $cardPackageHis->updated_at = $cardPackage->updated_at;
            $cardPackageHis->is_check = 0;
            $cardPackageHis->save();

            CardPackageModel::where('id', $cardPackage->id)->delete();
        }
        
    }
}







