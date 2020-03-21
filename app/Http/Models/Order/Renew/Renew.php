<?php

namespace App\Http\Models\Order\Renew;

use App\Http\Models\BaseModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Card\CardPackageModel;
use App\Http\Models\Card\TCCardPackageFutureHisModel;
use App\Http\Models\Card\TCCardPackageFutureModel;
use App\Http\Models\Card\TCCardRestartModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Order\TCRenewOrderDetailModel;
use App\Http\Models\Order\TCRenewOrderModel;
use Illuminate\Support\Facades\DB;

class Renew extends BaseModel implements IRenew 
{
    protected $card;
    protected $package;
    protected $futures;
    protected $cardPackages;


    /**
     * @return array
     */
    public function renew($orderNo) {
        
    }

    

    /**
     * 创建续费订单
     * @param $tradeNo 支付订单号
     * @param $amount 支付金额
     */
    protected function createRenewOrder($tradeNo, $amount, $month, $orderNum = 1) {
        //查询这个卡的开卡订单
        $order = SaleOrderModel::where('id', $this->card->order_id)->first();

        $model = new TCRenewOrderModel;
        $model->id = getUuid('RO');
        $model->order_id = $order->id;
        $model->order_num = $orderNum;
        $model->trade_no = $tradeNo;
        $model->status = SaleOrderModel::STATUS_END;
        $model->customer_id = $order->customer_id;
        $model->customer_name = $order->customer_name;
        $model->contacts_name = $order->contacts_name;
        $model->contacts_mobile = $order->contacts_mobile;
        $model->operator_type = $order->operator_type;
        $model->industry_type = $order->industry_type;
        $model->card_type = $order->card_type;
        $model->standard_type = $order->standard_type;
        $model->model_type = $order->model_type;
        $model->is_flow = $order->is_flow;
        $model->is_sms = $order->is_sms;
        $model->is_voice = $order->is_voice;
        $model->flow_package_id = $order->flow_package_id;
        $model->sms_package_id = $order->sms_package_id;
        $model->voice_package_id = $order->voice_package_id;
        $model->real_name_type = $order->real_name_type;
        $model->silent_date = 0;
        $model->pay_type = $order->pay_type;
        $model->amount = $amount;
        $model->create_time = date('Y-m-d H:i:s');
        $model->update_time = date('Y-m-d H:i:s');
        $model->describe = $order->describe;
        $model->fees_type = TCRenewOrderModel::FEES_TYPE_WECHAT;
        $model->flow_expiry_date = $month;
        $model->sms_expiry_date = $month;
        $model->voice_expiry_date = $order->voice_expiry_date;
        $model->is_open_card = $order->is_open_card;
        $model->express_arrive_day = 0;
        $model->flow_card_price = $amount;
        $model->sms_card_price = $order->sms_card_price;
        $model->voice_card_price = $order->voice_card_price;
        $model->is_special = $order->is_special;
        $model->is_pool = $order->is_pool;
        $model->is_overflow_stop = $order->is_overflow_stop;
        $model->payment_method =TCRenewOrderModel::PAYMENT_METHOD_WECHAT;
        $model->effect_type = TCRenewOrderModel::EFFECT_TYPE_NOW;
        $model->is_imsi = $order->is_imsi;
        $model->resubmit = $order->resubmit;
        $model->package_type = $order->package_type;
        $model->card_style = $order->card_style;
        $model->save();

        $detailModel = new TCRenewOrderDetailModel;
        $detailModel->id = getUuid('ROD');
        $detailModel->renew_id = $model->id;
        $detailModel->card_no = $this->card->card_no;
        $detailModel->save();
    }

    private function activityPackageFutureHis($where, $packageType, $unuse, $startDate) {
        $futureHisFlow = TCCardPackageFutureHisModel::where($where)->where('package_type',$packageType)->orderBy('end_time','desc')->first();
            if (!empty($futureHisFlow)) {
                $future = new TCCardPackageFutureModel;
                $future->id = getUuid('PF');
                $future->card_id = $futureHisFlow->card_id;
                $future->card_no = $futureHisFlow->card_no;
                $future->fees_type = '1002';  //续费
                $future->order_id = $futureHisFlow->order_id;
                $future->package_id = $futureHisFlow->package_id;
                $future->package_type = $futureHisFlow->package_type;
                $future->price = $futureHisFlow->price;
                $future->compensate = $futureHisFlow->compensate;
                $future->use_count = 0;
                $future->unuse_count = $unuse;
                $future->order_num = 1;
                $future->start_time = $startDate;
                $future->end_time = $this->card->valid_date;
                $future->created_time = date('Y-m-d H:i:s');
                $future->next_date = $startDate;
                $future->save();
                $this->futures[] = $future;
            }
    }

    /**
     * 延长预生效生命周期
     * @param string $cardNo 卡号
     * @param string $packageId 套餐id
     * @param string $month 续费多少个月
     * @param datetime $startDate 开始时间
     * 
     * 
     */
    protected function activityPackageFuture($cardNo, $packageId, $month, $startDate) {
        $where['card_no'] = $cardNo;
        //$where['package_id'] = $packageId;
        $futures = TCCardPackageFutureModel::where($where)->get();
        $package = Package::where('id', $packageId)->first();
        $this->log(date('Y-m-d H:i:s').'【延长预生效套餐】开始，预生效：'.json_encode($futures, JSON_UNESCAPED_UNICODE));
        $unuse = $month / $package->time_length;
        $this->log(date('Y-m-d H:i:s').'【延长预生效套餐】开始，延长时间：'.$unuse);
        
        if ($futures->isEmpty()) {
            //查询预最后一个流量的生效套餐历史，续费这个预生效套餐
            $this->activityPackageFutureHis($where, 'FLOW', $unuse, $startDate);
            //查询预最后一个短信的生效套餐历史，续费这个预生效套餐
            $this->activityPackageFutureHis($where, 'SMS', $unuse, $startDate);
        } else {
            foreach($futures as $future) {
                //修改预生效
                $future->unuse_count += $unuse;
                $future->end_time = $this->card->valid_date;
                $res = $future->save();
            }
            $this->futures = $futures;
        }
        $this->log(date('Y-m-d H:i:s').'【延长预生效套餐】更新后的：'.json_encode($this->futures, JSON_UNESCAPED_UNICODE));
        $this->package = $package;
        
    }

    /**
     * 卡片停复机操作
     * @param string $cardNo 卡号
     */
    protected function cardStart($cardNo) {
        //判断卡是否停机保号
        if ($this->card->status == CardModel::STATUS_STOP_PERTECTED_CARD) {
            //卡状态变成停机好调开机
            $this->card->status = CardModel::STATUS_STOP;
            $this->card->save();
        }

        //如果卡状态是停机需要调开机
        if ($this->card->status == CardModel::STATUS_STOP) {
            $restartModel = new TCCardRestartModel;
            $data['cardNo'] = $cardNo;
            $data['operateType'] = 2; //开机
            $data['customerId'] = $this->card->customer_id;
            $data['stationId'] = $this->card->station_id;
            $data['applyReason'] = '续费时候卡片在服务器内并且流量有剩余被停机了，续费成功需要给客户开机';
            $user['id'] = 0;
            $user['real_name'] = '系统';
            $restartModel->addRestartCard($data, $user);
        }
    }

    /**
     * 生效卡套餐
     * @param string $cardNo 卡号
     */
    protected function activityPackage(string $cardNo) {
        
        $cardPackages = CardPackageModel::where('card_no', $cardNo)->where('fees_type', '1001')->get();
        if ($cardPackages->isEmpty()) {
            foreach($this->futures as $future) {
                $package = Package::where('id',$future->package_id)->first();
                //计算下次生效时间
                $nextDate = date('Y-m-d', strtotime($future->start_time . "+" . $package->time_length . " " .$package->time_unit));
                    
                //有补偿值的才加补偿
                $price = $future->price;
                $cardPackageFutureUpdate = [
                    'next_date' => $nextDate,
                    'use_count' => ++$future->use_count,
                    'unuse_count' => --$future->unuse_count,
                ];
                if (bccomp(strval($future->compensate), '0', 2) == 1) {
                    $price = bcadd($future->price, $future->compensate, 2);
                    //补偿只生效一次，生效后就将补偿值置0
                    $cardPackageFutureUpdate['compensate'] = 0;
                }
                //更新预生效套餐
                $this->log(date('Y-m-d H:i:s').'续费套餐更新预生效套餐,预生效套餐id【'.$future->id.'】卡号【'.$future->card_no.'】更新数据：'.json_encode($cardPackageFutureUpdate, JSON_UNESCAPED_UNICODE));
                TCCardPackageFutureModel::where('id', $future->id)->update($cardPackageFutureUpdate);

                //开始时间是每月一号
                $startDate = $future->start_time;
                //结束时间是开始时间加上套餐的时间再减去一天，因为要取月末那天
                $endDate = date('Y-m-d', strtotime($startDate . "+" . $package->time_length . " " . $package->time_unit .  " -1 day"));
                $date = date('Y-m-d H:i:s');
            
                //计算总量
                $total = $package->consumption;
                if ($future->package_type == 'FLOW') {
                    //更新卡表
                    //修改激活时间和卡状态
                    $updateCard['active_date'] = date('Y-m-d');
                    //更新有效期止
                    $updateCard['valid_date'] = $future->end_time;
                    // $updateCard['status'] = 2; //正常
                    $this->log(date('Y-m-d H:i:s').'续费套餐激活更新卡片，卡号：【'.$future->card_no.'】更新数据：'.json_encode($updateCard, JSON_UNESCAPED_UNICODE));
                    CardModel::where('card_no', $future->card_no)->update($updateCard);
                    //流量变成KB
                    $total = $total * 1024;
                }

                //插入卡片当前生效套餐数据
                $cardPackage = new CardPackageModel;
                $cardPackage->id = getUuid();
                $cardPackage->card_id = $future->card_id;
                $cardPackage->card_no = $future->card_no;
                $cardPackage->renew_id = $future->order_id;
                $cardPackage->package_id = $future->package_id;
                $cardPackage->price = $price;
                $cardPackage->total = $total;
                $cardPackage->allowance = $total;
                $cardPackage->enable_date = $startDate;
                $cardPackage->failure_date = $endDate;
                $cardPackage->created_at = $date;
                $cardPackage->updated_at = $date;
                $cardPackage->package_type = $future->package_type;
                $cardPackage->fees_type = '1001'; //主套餐
                $cardPackage->used = 0;
                $cardPackage->save();
                $cardPackages[] = $cardPackage;

                $this->cardPackageToConsumption($cardNo, $future->package_type);
            }
            $this->cardPackages = $cardPackages;
        }
    }

    /**
     * 插入扣费记录
     * 
     */
    protected function cardPackageToConsumption(string $cardNo, $type) {
        // DB::connection()->enableQueryLog();
        $date = date('Y-m-d');

        $sql = "INSERT INTO t_c_consumption_detail (id,card_id,card_no,package_type,consumption,consumption_time,card_package_id,cost_type, end_date) 
        SELECT wl_uuid(),cp.card_id,cp.card_no,cp.package_type,0,'$date',cp.id,null, cp.failure_date
        FROM t_c_card_package cp 
        WHERE cp.card_no = '$cardNo' and cp.package_type = '$type'";
        
        $res = DB::select($sql);
    }
}







