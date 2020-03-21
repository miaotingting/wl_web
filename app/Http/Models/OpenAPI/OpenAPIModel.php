<?php

namespace App\Http\Models\OpenAPI;

use App\Http\Models\BaseModel;

use App\Http\Models\Customer\Customer;
use App\Http\Models\Operation\Package;
use App\Http\Models\Card\CardPackageModel;
use App\Exceptions\CommonException;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Card\TCCardPackageFutureModel;
use App\Http\Models\Card\TCCardRestartModel;
use App\Http\Models\Common\TCConsumptionDetailModel;
use App\Http\Models\Customer\TSysCustomerAccountModel;
use App\Http\Models\Customer\TSysCustomerAccountRecordModel;
use App\Http\Models\Impl\TImplCustomerRenewModel;
use App\Http\Models\Impl\TImplRenewSuccessModel;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Order\TCRenewOrderDetailModel;
use App\Http\Models\Order\TCRenewOrderModel;
use Illuminate\Support\Facades\DB;

class OpenAPIModel extends BaseModel
{
    /**
     *获取签名
     * @param Array $input
     * @param String $code
     * @return void
     */
    public function getSign(Array $input, String $code)
    {
        $signStr = '';
        foreach($input as $key=>$val){
            if(is_array($val)) return 'Wrong parameter value type';
            $signStr .= $key . '=' . $val;
        }
        return md5($signStr . $code);
    }

    /**
     * 行业卡单卡续费
     * @param [type] $input
     * @return void
     */
    public function packageRenew($input){
        try{
            $orderNo = getComOrderNo('JKXF');
            // 保存续费接口调用记录
            $implRenewRecordId = getUuid('impl_customer_renew');
            $TImplCustomerRenewEntity = new TImplCustomerRenewModel();
            $TImplCustomerRenewEntity->id = $implRenewRecordId;
            $TImplCustomerRenewEntity->order_no = $orderNo;
            $TImplCustomerRenewEntity->customer_id = $input['clientId'];
            $TImplCustomerRenewEntity->message = json_encode($input);
            $TImplCustomerRenewEntity->create_time = date("Y-m-d H:i:s",time());
            $TImplCustomerRenewEntity->type = 1; // 1-续费
            $TImplCustomerRenewEntity->save();
            // 先查询有没有生效记录(如果没有直接返回续费异常)
            $sql = "SELECT o.flow_package_id,o.flow_card_price,o.flow_expiry_date,o.sms_package_id,
                        o.sms_card_price,o.sms_expiry_date,o.voice_package_id,o.voice_card_price,o.voice_expiry_date,o.customer_id,o.customer_name,o.id  
                    FROM (
                        SELECT bb.order_id,bb.end_time FROM t_c_card_package_future bb WHERE bb.card_no = ".$input['cardNo']." AND bb.package_type = 'FLOW' AND fees_type != '1003' 
                        UNION
                        SELECT bb.order_id,bb.end_time FROM t_c_card_package_future_his bb WHERE bb.card_no = ".$input['cardNo']." AND bb.package_type = 'FLOW' AND fees_type != '1003' 
                        ) tt
                    LEFT JOIN t_c_sale_order o ON o.id = tt.order_id and o.flow_card_price > 0
                    where 1=1 ORDER BY tt.end_time DESC LIMIT 1";
            $packageFutureEntity = DB::select($sql);
            $packageFutureEntity = $packageFutureEntity[0];
            if(empty($packageFutureEntity)){
                return ['status'=>FALSE,'code'=>600009,'msg'=>'续费异常！'];
            }
            // 根据订单卡片单价和周期计算续费套餐价格（根据续费年数计算）
            $flowPackageEntity = null;//订单中的流量套餐
            $smsPackageEntity = null;//订单中的短信套餐
            $voicePackageEntity = null;//订单中的语音套餐
            $flowTotalPrice = 0;
            $flowMonthPrice = 0;
            $flowCompensate = 0;
            $smsTotalPrice = 0;
            $smsMonthPrice = 0;
            $smsCompensate = 0;
            $voiceTotalPrice = 0;
            $voiceMonthPrice = 0;
            $voiceCompensate = 0;
            if(!empty($packageFutureEntity->flow_package_id)){
                $flowPackageEntity = Package::where('id',$packageFutureEntity->flow_package_id)->first();
            }else{
                return ['status'=>FALSE,'code'=>600009,'msg'=>'续费异常！'];
            }
            if(!empty($packageFutureEntity->sms_package_id)){
                $smsPackageEntity = Package::where('id',$packageFutureEntity->sms_package_id)->first();
            }
            if(!empty($packageFutureEntity->voice_package_id)){
                $voicePackageEntity = Package::where('id',$packageFutureEntity->voice_package_id)->first();
            }
            // 获取卡片原始服务期止
            $TCCardEntity = CardModel::where('card_no',$input['cardNo'])->first(['id','card_no','iccid','card_type','station_id','status','valid_date']);
            if($TCCardEntity->status == 1){
                return ['status'=>FALSE,'code'=>600010,'msg'=>'卡片未激活无法续费！'];
            }
            // 计算继续生效开始日期
            $startTime = date('Y-m-d',strtotime("{$TCCardEntity->valid_date} + 1 day"));
            $timeUnit =  $flowPackageEntity->time_unit;// 套餐表时间单位
            $timeLength = $flowPackageEntity->time_length;// 套餐表时长
            // 计算续费后的服务期止
            $allMonth = $input['renewLength']*12;
            $endTime = date('Y-m-d',strtotime("{$startTime} +{$allMonth} month")-3600*24);
            // 计算续费套餐价格：流量
            $flowPriceMap = $this->getPrice(
                    $packageFutureEntity->flow_card_price,
                    $packageFutureEntity->flow_expiry_date,
                    $input['renewLength'],
                    $flowPackageEntity
                );
            $flowTotalPrice = $flowPriceMap['totalPrice'];
            $flowMonthPrice = $flowPriceMap['monthPrice'];
            $flowCompensate = $flowPriceMap['compensate'];
            // 计算续费套餐价格：短信
            if($smsPackageEntity != null){
                $smsPriceMap = $this->getPrice(
                    $packageFutureEntity->sms_card_price,
                    $packageFutureEntity->sms_expiry_date,
                    $input['renewLength'],
                    $smsPackageEntity
                );
                $smsTotalPrice = $smsPriceMap['totalPrice'];
                $smsMonthPrice = $smsPriceMap['monthPrice'];
                $smsCompensate = $smsPriceMap['compensate'];
            }
            // 计算续费套餐价格：语音
            if($voicePackageEntity != null){
                $voicePriceMap = $this->getPrice(
                    $packageFutureEntity->voice_card_price,
                    $packageFutureEntity->voice_expiry_date,
                    $input['renewLength'],
                    $voicePackageEntity
                );
                $voiceTotalPrice = $voicePriceMap['totalPrice'];
                $voiceMonthPrice = $voicePriceMap['monthPrice'];
                $voiceCompensate = $voicePriceMap['compensate'];
            }
            // 判断账户余额是否充足
            $TSysCustomerAccountEntity = TSysCustomerAccountModel::find($input['clientId']);
            $balance = $TSysCustomerAccountEntity->balance_amount;
            if($balance < 1 || $balance - ($flowTotalPrice+$smsTotalPrice+$voiceTotalPrice) < 1){
                return ['status'=>FALSE,'code'=>600011,'msg'=>'账户余额不足'];
            }
            // 扣除账户余额
            TSysCustomerAccountModel::where('id',$input['clientId'])->update([
                'balance_amount'=>$TSysCustomerAccountEntity->balance_amount-($flowTotalPrice+$smsTotalPrice+$voiceTotalPrice)]);
            // *********************************开始进行续费逻辑 Start  *****************//
            // 判断卡状态，根据卡状态进行续费;停机保号的卡需要生成预生效
            if($TCCardEntity->status == 5){
                // 停机保号卡进行复机
                $restartModel = new TCCardRestartModel;
                $restartMap['customerId'] = $packageFutureEntity->customer_id;
                $restartMap['customerName'] = $packageFutureEntity->customer_name;
                $restartMap['stationId'] = $TCCardEntity->station_id;
                $restartMap['stationName'] = '0';
                $restartMap['cardNo'] = $TCCardEntity->card_no;
                $restartMap['iccid'] = $TCCardEntity->iccid;
                $restartMap['cardType'] = $TCCardEntity->card_type;
                $restartModel->createImplRestart($restartMap);
                // 修改卡状态为停机
                CardModel::where('card_no',$input['cardNo'])->update(['status'=>3]);
            }
            
            DB::beginTransaction();
            // 创建续费订单和续费卡片详情
            $TCSaleOrderModelEntity = SaleOrderModel::find($packageFutureEntity->id);
            $TCRenewOrderEntity = new TCRenewOrderModel();
            $renewId = getUuid('renew_id');//续费订单ID
            $TCRenewOrderEntity->id = $renewId;
            $TCRenewOrderEntity->trade_no =$orderNo;
            $TCRenewOrderEntity->order_num = 1;
            $TCRenewOrderEntity->status = 2; //接口订单直接状态：订单完成
            $TCRenewOrderEntity->customer_id = $TCSaleOrderModelEntity->customer_id;
            $TCRenewOrderEntity->customer_name = $TCSaleOrderModelEntity->customer_name;
            $TCRenewOrderEntity->contacts_name = $TCSaleOrderModelEntity->contacts_name;
            $TCRenewOrderEntity->contacts_mobile = $TCSaleOrderModelEntity->contacts_mobile;
            $TCRenewOrderEntity->operator_type = $TCSaleOrderModelEntity->operator_type;
            $TCRenewOrderEntity->industry_type = $TCSaleOrderModelEntity->industry_type;
            $TCRenewOrderEntity->card_type = $TCSaleOrderModelEntity->card_type;
            $TCRenewOrderEntity->standard_type = $TCSaleOrderModelEntity->standard_type;
            $TCRenewOrderEntity->model_type = $TCSaleOrderModelEntity->model_type;
            $TCRenewOrderEntity->is_flow = $TCSaleOrderModelEntity->is_flow;
            $TCRenewOrderEntity->is_sms = $TCSaleOrderModelEntity->is_sms;
            $TCRenewOrderEntity->is_voice = $TCSaleOrderModelEntity->is_voice;
            $TCRenewOrderEntity->flow_package_id = $TCSaleOrderModelEntity->flow_package_id;
            $TCRenewOrderEntity->sms_package_id = $TCSaleOrderModelEntity->sms_package_id;
            $TCRenewOrderEntity->voice_package_id = $TCSaleOrderModelEntity->voice_package_id;
            $TCRenewOrderEntity->flow_card_price = $TCSaleOrderModelEntity->flow_card_price;
            $TCRenewOrderEntity->sms_card_price = $TCSaleOrderModelEntity->sms_card_price;
            $TCRenewOrderEntity->voice_card_price = $TCSaleOrderModelEntity->voice_card_price;
            $TCRenewOrderEntity->flow_expiry_date = $TCSaleOrderModelEntity->flow_expiry_date;
            $TCRenewOrderEntity->sms_expiry_date = $TCSaleOrderModelEntity->sms_expiry_date;
            $TCRenewOrderEntity->voice_expiry_date = $TCSaleOrderModelEntity->voice_expiry_date;
            $TCRenewOrderEntity->real_name_type = $TCSaleOrderModelEntity->real_name_type;
            $TCRenewOrderEntity->pay_type = $TCSaleOrderModelEntity->pay_type;
            $TCRenewOrderEntity->amount = $TCSaleOrderModelEntity->amount;
            $TCRenewOrderEntity->create_time = date('Y-m-d H:i:s',time());
            $TCRenewOrderEntity->update_time = date('Y-m-d H:i:s',time());
            $TCRenewOrderEntity->describe = "接口自助续费";
            $TCRenewOrderEntity->fees_type = '1002'; //计费类型  1001：开卡   1002：续费  1003:升级套餐
            $TCRenewOrderEntity->is_pool = 0;
            $TCRenewOrderEntity->is_overflow_stop = $TCSaleOrderModelEntity->is_overflow_stop;
            $TCRenewOrderEntity->payment_method = 0;//付款方式 0：账户余额抵扣    1：微信支付
            $TCRenewOrderEntity->effect_type = 1;//续费套餐生效类型   0：次月生效   1：服务期止后生效
            $TCRenewOrderEntity->resubmit = 0;
            $TCRenewOrderEntity->create_user_id = $TCSaleOrderModelEntity->customer_id;
            $TCRenewOrderEntity->package_type = $TCSaleOrderModelEntity->package_type;
            $TCRenewOrderEntity->card_style = $TCSaleOrderModelEntity->card_style;
            $TCRenewOrderEntity->save();
            // 创建续费订单卡片详情
            $TCRenewOrderDetail = new TCRenewOrderDetailModel();
            $TCRenewOrderDetail->id = getUuid('renew_detail_id');
            $TCRenewOrderDetail->renew_id = $renewId;
            $TCRenewOrderDetail->card_no = $input['cardNo'];
            $TCRenewOrderDetail->save();

            // 续费修改开卡订单fees_type,升级新增一条，以上均保存原始备注信息并拼接备注信息
            SaleOrderModel::where('id',$packageFutureEntity->id)->update([
                'fees_type'=>'1002',
                'describe'=>$TCSaleOrderModelEntity->describe . '/ 接口续费于:'. date('Y-m-d',time()) . '续费起始日期为：' . $startTime
                ]);
            
            // 便于计算月收入 每次续费都新增预生效信息表
            // 计算生效次数
            $renewMonth = $input['renewLength']*12;
            $timeLength = $flowPackageEntity->time_length;
            $unuseCount = $renewMonth/$timeLength;
            $TCCardPackageFutureEntity = new TCCardPackageFutureModel();
            $flowPackageFutureId = getUuid('package_future');
            $TCCardPackageFutureEntity->id = $flowPackageFutureId;
            $TCCardPackageFutureEntity->card_id = $TCCardEntity->id;
            $TCCardPackageFutureEntity->card_no = $TCCardEntity->card_no;
            $TCCardPackageFutureEntity->fees_type = '1002';
            $TCCardPackageFutureEntity->order_id = $packageFutureEntity->id;
            $TCCardPackageFutureEntity->package_id = $packageFutureEntity->flow_package_id;
            $TCCardPackageFutureEntity->package_type ='FLOW';
            $TCCardPackageFutureEntity->price = $flowPriceMap['monthPrice'];
            $TCCardPackageFutureEntity->compensate = $flowPriceMap['compensate'];
            $TCCardPackageFutureEntity->use_count = 0;
            $TCCardPackageFutureEntity->unuse_count = $unuseCount;
            $TCCardPackageFutureEntity->order_num = 1;
            $TCCardPackageFutureEntity->start_time = $startTime;
            $TCCardPackageFutureEntity->end_time = $endTime;
            $TCCardPackageFutureEntity->created_time = date("Y-m-d H:i:s",time());
            $TCCardPackageFutureEntity->next_date = $startTime;
            $TCCardPackageFutureEntity->save();

            $packageFutureMap = TCCardPackageFutureModel::where('card_no',$input['cardNo'])
                                    ->where('package_type','FLOW')
                                    ->orderBy('start_time','DESC')
                                    ->first();
            // 如果没有预生效要新增当前生效和消费记录(有预生效的直接新增就完事了后面的系统进行自动生效)
            $flowTotal = $flowPackageEntity->consumption*1024; //流量准换成KB
            $startDate = date('Y-m-01');
            $failureDate = date('Y-m-d', strtotime("$startDate + $flowPackageEntity->time_length $flowPackageEntity->time_unit - 1 day"));
            if(empty($packageFutureMap)){
                // 没有预生效的添加当前生效套餐
                $TCCardPackage = new CardPackageModel();
                $cardPackageId = getUuid('card_package_id');
                $TCCardPackage->id = $cardPackageId;
                $TCCardPackage->card_id = $TCCardEntity->id;
                $TCCardPackage->card_no = $TCCardEntity->card_no;
                $TCCardPackage->renew_id = $packageFutureEntity->id;
                $TCCardPackage->package_id = $packageFutureEntity->flow_package_id;
                $TCCardPackage->total = $flowTotal;
                $TCCardPackage->allowance = $flowTotal;
                $TCCardPackage->used = 0;
                $TCCardPackage->price = $flowMonthPrice;
                $TCCardPackage->enable_date = $startDate;
                $TCCardPackage->failure_date = $failureDate;
                $TCCardPackage->created_at = date("Y-m-d H:i:s",time());
                $TCCardPackage->updated_at = date("Y-m-d H:i:s",time());
                $TCCardPackage->package_type = $flowPackageEntity->package_type;
                $TCCardPackage->fees_type = $flowPackageEntity->fees_type;
                $TCCardPackage->save();
                // 没有预生效的添加生效扣费记录表
                $TCConsumptionDetailEntity = new TCConsumptionDetailModel();
                $TCConsumptionDetailEntity->id = getUuid();
                $TCConsumptionDetailEntity->card_id = $TCCardEntity->id;
                $TCConsumptionDetailEntity->card_no = $TCCardEntity->card_no;
                $TCConsumptionDetailEntity->package_type = $flowPackageEntity->package_type;
                $TCConsumptionDetailEntity->consumption = 0;
                $TCConsumptionDetailEntity->consumption_time = date('Y-m-01');
                $TCConsumptionDetailEntity->card_package_id =$cardPackageId;
                $TCConsumptionDetailEntity->cost_type = 1;
                $TCConsumptionDetailEntity->end_date =$failureDate;
                $TCConsumptionDetailEntity->save();
                // 修改预生效生效次数、未生效次数、生效时间、下次生效时间
                $nextDate = date('Y-m-01', strtotime("$startDate + $flowPackageEntity->time_length $flowPackageEntity->time_unit"));
                TCCardPackageFutureModel::where('id',$flowPackageFutureId)->update([
                    'use_count'=>1,
                    'unuse_count'=>$unuseCount-1,
                    'start_time'=>date('Y-m-01'),
                    'next_date'=>$nextDate
                ]);
            }

            // 短信（有短信套餐的情况下）
            if(!empty($smsPackageEntity)){
                // 短信预生效
                $renewMonth = $input['renewLength']*12;
                $timeLength = $flowPackageEntity->time_length;
                $unuseCount = $renewMonth/$timeLength;
                $TCCardPackageFutureEntity = new TCCardPackageFutureModel();
                $smsPackageFutureId = getUuid('package_future');
                $TCCardPackageFutureEntity->id = $smsPackageFutureId;
                $TCCardPackageFutureEntity->card_id = $TCCardEntity->id;
                $TCCardPackageFutureEntity->card_no = $TCCardEntity->card_no;
                $TCCardPackageFutureEntity->fees_type = '1002';
                $TCCardPackageFutureEntity->order_id = $packageFutureEntity->id;
                $TCCardPackageFutureEntity->package_id = $packageFutureEntity->sms_package_id;
                $TCCardPackageFutureEntity->package_type = 'SMS';
                $TCCardPackageFutureEntity->price = $smsPriceMap['monthPrice'];
                $TCCardPackageFutureEntity->compensate = $smsPriceMap['compensate'];
                $TCCardPackageFutureEntity->use_count = 0;
                $TCCardPackageFutureEntity->unuse_count = $unuseCount;
                $TCCardPackageFutureEntity->order_num = 1;
                $TCCardPackageFutureEntity->start_time = $startTime;
                $TCCardPackageFutureEntity->end_time = $endTime;
                $TCCardPackageFutureEntity->created_time = date("Y-m-d H:i:s",time());
                $TCCardPackageFutureEntity->next_date = $startTime;
                $TCCardPackageFutureEntity->save();

                $smsPackageFutureMap = TCCardPackageFutureModel::where('card_no',$input['cardNo'])
                                        ->where('package_type','SMS')
                                        ->orderBy('start_time','DESC')
                                        ->first();
                // 如果没有预生效要新增当前生效和消费记录(有预生效的直接新增就完事了后面的系统进行自动生效)
                $smsTotal = $smsPackageEntity->consumption; //短信条数
                $startDate = date('Y-m-01');
                $failureDate = date('Y-m-d', strtotime("$startDate + $smsPackageEntity->time_length $smsPackageEntity->time_unit - 1 day"));
                if(empty($smsPackageFutureMap)){
                    // 没有预生效的添加当前生效套餐
                    $TCCardPackageSms = new CardPackageModel();
                    $cardPackageSmsId = getUuid('card_package_sms_id');
                    $TCCardPackageSms->id = $cardPackageSmsId;
                    $TCCardPackageSms->card_id = $TCCardEntity->id;
                    $TCCardPackageSms->card_no = $TCCardEntity->card_no;
                    $TCCardPackageSms->renew_id = $packageFutureEntity->id;
                    $TCCardPackageSms->package_id = $packageFutureEntity->sms_package_id;
                    $TCCardPackageSms->total = $smsTotal;
                    $TCCardPackageSms->allowance = $smsTotal;
                    $TCCardPackageSms->used = 0;
                    $TCCardPackageSms->price = $smsMonthPrice;
                    $TCCardPackageSms->enable_date = $startDate;
                    $TCCardPackageSms->failure_date = $failureDate;
                    $TCCardPackageSms->created_at = date("Y-m-d H:i:s",time());
                    $TCCardPackageSms->updated_at = date("Y-m-d H:i:s",time());
                    $TCCardPackageSms->package_type = $smsPackageEntity->package_type;
                    $TCCardPackageSms->fees_type = $smsPackageEntity->fees_type;
                    $TCCardPackageSms->save();
                    // 没有预生效的添加生效扣费记录表
                    $TCConsumptionDetailEntity = new TCConsumptionDetailModel();
                    $TCConsumptionDetailEntity->id = getUuid();
                    $TCConsumptionDetailEntity->card_id = $TCCardEntity->id;
                    $TCConsumptionDetailEntity->card_no = $TCCardEntity->card_no;
                    $TCConsumptionDetailEntity->package_type = $smsPackageEntity->package_type;
                    $TCConsumptionDetailEntity->consumption = 0;
                    $TCConsumptionDetailEntity->consumption_time = date('Y-m-01');
                    $TCConsumptionDetailEntity->card_package_id =$cardPackageSmsId;
                    $TCConsumptionDetailEntity->cost_type = 1;
                    $TCConsumptionDetailEntity->end_date =$failureDate;
                    $TCConsumptionDetailEntity->save();
                    // 修改预生效生效次数、未生效次数、生效时间、下次生效时间
                    $nextDate = date('Y-m-01', strtotime("$startDate + $smsPackageEntity->time_length $smsPackageEntity->time_unit"));
                    TCCardPackageFutureModel::where('id',$smsPackageFutureId)->update([
                        'use_count'=>1,
                        'unuse_count'=>$unuseCount-1,
                        'start_time'=>date('Y-m-01'),
                        'next_date'=>$nextDate
                    ]);
                }
            }

            // 语音（有语音套餐的情况下）
            if(!empty($voicePackageEntity)){
                // 语音预生效
                $renewMonth = $input['renewLength']*12;
                $timeLength = $flowPackageEntity->time_length;
                $unuseCount = $renewMonth/$timeLength;
                $TCCardPackageFutureEntity = new TCCardPackageFutureModel();
                $voicePackageFutureId = getUuid('package_future_voice');
                $TCCardPackageFutureEntity->id = $voicePackageFutureId;
                $TCCardPackageFutureEntity->card_id = $TCCardEntity->id;
                $TCCardPackageFutureEntity->card_no = $TCCardEntity->card_no;
                $TCCardPackageFutureEntity->fees_type = '1002';
                $TCCardPackageFutureEntity->order_id = $packageFutureEntity->id;
                $TCCardPackageFutureEntity->package_id = $packageFutureEntity->voice_package_id;
                $TCCardPackageFutureEntity->package_type = 'VOICE';
                $TCCardPackageFutureEntity->price = $voicePriceMap['monthPrice'];
                $TCCardPackageFutureEntity->compensate = $voicePriceMap['compensate'];
                $TCCardPackageFutureEntity->use_count = 0;
                $TCCardPackageFutureEntity->unuse_count = $unuseCount;
                $TCCardPackageFutureEntity->order_num = 1;
                $TCCardPackageFutureEntity->start_time = $startTime;
                $TCCardPackageFutureEntity->end_time = $endTime;
                $TCCardPackageFutureEntity->created_time = date("Y-m-d H:i:s",time());
                $TCCardPackageFutureEntity->next_date = $startTime;
                $TCCardPackageFutureEntity->save();

                $voicePackageFutureMap = TCCardPackageFutureModel::where('card_no',$input['cardNo'])
                                        ->where('package_type','VOICE')
                                        ->orderBy('start_time','DESC')
                                        ->first();
                // 如果没有预生效要新增当前生效和消费记录(有预生效的直接新增就完事了后面的系统进行自动生效)
                $voiceTotal = $smsPackageEntity->consumption; //语音（分钟）
                $startDate = date('Y-m-01');
                $failureDate = date('Y-m-d', strtotime("$startDate + $voicePackageEntity->time_length $voicePackageEntity->time_unit - 1 day"));
                if(empty($voicePackageFutureMap)){
                    // 没有预生效的添加当前生效套餐
                    $TCCardPackageVoice = new CardPackageModel();
                    $cardPackageVoiceId = getUuid('card_package_voice_id');
                    $TCCardPackageVoice->id = $cardPackageVoiceId;
                    $TCCardPackageVoice->card_id = $TCCardEntity->id;
                    $TCCardPackageVoice->card_no = $TCCardEntity->card_no;
                    $TCCardPackageVoice->renew_id = $packageFutureEntity->id;
                    $TCCardPackageVoice->package_id = $packageFutureEntity->voice_package_id;
                    $TCCardPackageVoice->total = $voiceTotal;
                    $TCCardPackageVoice->allowance = $voiceTotal;
                    $TCCardPackageVoice->used = 0;
                    $TCCardPackageVoice->price = $voiceMonthPrice;
                    $TCCardPackageVoice->enable_date = $startDate;
                    $TCCardPackageVoice->failure_date = $failureDate;
                    $TCCardPackageVoice->created_at = date("Y-m-d H:i:s",time());
                    $TCCardPackageVoice->updated_at = date("Y-m-d H:i:s",time());
                    $TCCardPackageVoice->package_type = $voicePackageEntity->package_type;
                    $TCCardPackageVoice->fees_type = $voicePackageEntity->fees_type;
                    $TCCardPackageVoice->save();
                    // 没有预生效的添加生效扣费记录表
                    $TCConsumptionDetailEntity = new TCConsumptionDetailModel();
                    $TCConsumptionDetailEntity->id = getUuid();
                    $TCConsumptionDetailEntity->card_id = $TCCardEntity->id;
                    $TCConsumptionDetailEntity->card_no = $TCCardEntity->card_no;
                    $TCConsumptionDetailEntity->package_type = $voicePackageEntity->package_type;
                    $TCConsumptionDetailEntity->consumption = 0;
                    $TCConsumptionDetailEntity->consumption_time = date('Y-m-01');
                    $TCConsumptionDetailEntity->card_package_id =$cardPackageVoiceId;
                    $TCConsumptionDetailEntity->cost_type = 1;
                    $TCConsumptionDetailEntity->end_date =$failureDate;
                    $TCConsumptionDetailEntity->save();
                    // 修改预生效生效次数、未生效次数、生效时间、下次生效时间
                    $nextDate = date('Y-m-01', strtotime("$startDate + $voicePackageEntity->time_length $voicePackageEntity->time_unit"));
                    TCCardPackageFutureModel::where('id',$voicePackageFutureId)->update([
                        'use_count'=>1,
                        'unuse_count'=>$unuseCount-1,
                        'start_time'=>date('Y-m-01'),
                        'next_date'=>$nextDate
                    ]);
                }
            }

            //客户进出账明细记录
            $TSysCustomerAccountRecord = new TSysCustomerAccountRecordModel();
            $TSysCustomerAccountRecord->id = getUuid('account_record');
            $TSysCustomerAccountRecord->customer_id = $input['clientId'];
            $TSysCustomerAccountRecord->amount = $flowTotalPrice+$smsTotalPrice+$voiceTotalPrice;
            $TSysCustomerAccountRecord->type = 1; // 0进账   1出账
            $TSysCustomerAccountRecord->apply_code = $orderNo;;
            $TSysCustomerAccountRecord->apply_explain = '客户接口自助续费';
            $TSysCustomerAccountRecord->create_time = date("Y-m-d H:i:s",time());
            $TSysCustomerAccountRecord->create_user_id = $input['clientId'];
            $TSysCustomerAccountRecord->apply_type = 1;//1-续费消费 ，2-分润，3-客户提现
            $TSysCustomerAccountRecord->save();

            // *********************************续费逻辑 End  *****************//
            //*****************************行业卡接口续费成功记录Start*********************************//
            $TImplRenewSuccessEntity = new TImplRenewSuccessModel();
            $TImplRenewSuccessEntity->id = getUuid('impl_renew_success');
            $TImplRenewSuccessEntity->card_no = $input['cardNo'];
            $TImplRenewSuccessEntity->client_id = $input['clientId'];
            $TImplRenewSuccessEntity->balance = $balance;
            $TImplRenewSuccessEntity->cost = $flowTotalPrice+$smsTotalPrice+$voiceTotalPrice;
            $TImplRenewSuccessEntity->operate_type = 1;
            $TImplRenewSuccessEntity->create_time = date("Y-m-d H:i:s",time());
            $TImplRenewSuccessEntity->length = $input['renewLength'];
            $TImplRenewSuccessEntity->renew_id = $implRenewRecordId;
            $TImplRenewSuccessEntity->save();
            //*****************************行业卡接口续费成功记录End*********************************//
            // 返回续费成功信息
            $resultMap = [];
            $resultMap['tradeNo'] = $orderNo;
            $resultMap['msg'] = '接口自助续费成功！';
            $resultMap['balance'] = $flowTotalPrice+$smsTotalPrice+$voiceTotalPrice;
            $resultMap['renewTime'] = date("Y-m-d H:i:s",time());
            DB::commit();
            return ['status'=>TRUE,'data'=>$resultMap];
        }catch(Exception $e){
            DB::rollback();
            return ['status'=>FALSE,'code'=>600000,'msg'=>'操作异常'];
        }

    }
    
    /**
     * 根据订单卡片单价和周期计算续费总价、单价、补偿价
     * @param [type] $price
     * @param [type] $expiryDate
     * @param [type] $length
     * @param Package $Package
     * @return void
     */
    public function getPrice($price,$expiryDate,$length,Package $packageEntity)
    {
        $totalPrice = round($price*12*$length/($expiryDate*$packageEntity->time_length),2);
        $perPrice = $totalPrice*$packageEntity->time_length/($length*12);
        $monthPrice = floor($perPrice*100)/100; //取两位小数但不四舍五入
        $backPrice = $monthPrice*$length*12;
        $compensate = round(($totalPrice-$backPrice),2);//补偿价
        $priceMap = []; 
        $priceMap['totalPrice'] = $totalPrice;
        $priceMap['monthPrice'] = $monthPrice;
        $priceMap['compensate'] = $compensate;
		return $priceMap;
    }
    
    
    

}







