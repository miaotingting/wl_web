<?php

namespace App\Http\Models\Order;

use App\Events\MatterEvent;
use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Matter\ProcessModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Card\CardModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use App\Http\Models\Matter\DefineModel;
use App\Http\Models\Matter\ThreadModel;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Admin\TypeModel;
use App\Http\Models\Matter\NodeModel;
use App\Http\Models\Admin\NoticeModel;

class SaleOrderModel extends BaseModel
{
    //
    protected $table = 'c_sale_order';
    public $timestamps = false;

    public $dicArr = [
        "operator_type" => TypeModel::OPERATOR_TYPE,
        "industry_type" => TypeModel::INDUSTRY_TYPE,
        "card_type" => TypeModel::CARD_TYPE,
        "standard_type" => TypeModel::STANDARD_TYPE,
        "model_type" => TypeModel::MODEL_TYPE,
        "status" => TypeModel::SALE_ORDER_STATUS
    ];

    const PREFIX = 'CG';
    const TASK_CODE = 'kksp';

    const STATUS_CHECK = 1;
    const STATUS_END = 2;
    const STATUS_REJECT = 3;
    const STATUS_DELETE = 4;
    const STATUS_RECEIVABLES = 5;
    const STATUS_SHIPPED = 6;
    const STATUS_DATA = 7;
    const STATUS_PART_RETURN = 8; //部分退货
    const STATUS_RETURN = 9; //全部退货

    const CARD_TYPE_VOICE = "1002";  //语音卡

    function getWhere(array $search) {
        $where = [];
        if (count($search) > 0) {
            if (array_has($search, 'customerName') && !empty($search['customerName'])) {
                $where[] = ['c_sale_order.customer_name', 'like', '%'.array_get($search, "customerName").'%'];
            }
            if (array_has($search, 'status') && !empty($search['status'])) {
                $where[] = ['c_sale_order.status', array_get($search, 'status')];
            }
            if (array_has($search, 'orderNo') && !empty($search['orderNo'])) {
                $where[] = ['c_sale_order.order_no', 'like', '%'.array_get($search, 'orderNo').'%'];
            }
            if (array_has($search, 'operatorType') && !empty($search['operatorType'])) {
                $where[] = ['c_sale_order.operator_type', array_get($search, 'operatorType')];
            }
            if (array_has($search, 'customerCode') && !empty($search['customerCode'])) {
                $where[] = ['sys_customer.customer_code', 'like', '%'.array_get($search, 'customerCode').'%'];
            }
            if (array_has($search, 'createTime.start') || array_has($search, 'createTime.end')) {
                if (!empty(array_get($search, 'createTime.start'))) {
                    $where[] = ['c_sale_order.create_time', '>=', array_get($search, 'createTime.start')];
                }
                if (!empty(array_get($search, 'createTime.end'))) {
                    $where[] = ['c_sale_order.create_time', '<=', array_get($search, 'createTime.end')];
                }
            }
            if (array_has($search, 'endTime.start') || array_has($search, 'endTime.end')) {
                if (!empty(array_get($search, 'endTime.start'))) {
                    $where[] = ['wf_process.end_time', '>=', array_get($search, 'endTime.start')];
                }
                if (!empty(array_get($search, 'endTime.end'))) {
                    $where[] = ['wf_process.end_time', '<=', array_get($search, 'endTime.end')];
                }
            }
            if (array_has($search, 'standardType') && !empty($search['standardType'])) {
                $where[] = ['c_sale_order.standard_type', array_get($search, 'standardType')];
            }
        }
        return $where;
    }

    /**
     * 获取订单列表
     */
    function getOrders(int $pageIndex, int $pageSize, array $search, $user) {

        $where = $this->getWhere($search);
        //判断查询卡信息和落地信息
        if ((array_has($search, 'iccid') && !empty(array_get($search, 'iccid'))) 
            || (array_has($search, 'cardNo') && !empty(array_get($search, 'cardNo'))) 
            || (array_has($search, 'station') && !empty(array_get($search, 'station')))) {
                //先查询卡表和落地，如果查到了查询相应订单，如果没查到，返回空
                $cardModel = new CardModel;
                $cardWhere =[];
                if (array_has($search, 'iccid')) {
                    $cardWhere[] = ['c_card.iccid', 'like', '%'.array_get($search, 'iccid').'%'];
                }
                if (array_has($search, 'cardNo')) {
                    $cardWhere[] = ['c_card.card_no', 'like', '%'.array_get($search, 'cardNo').'%'];
                }
                $orWhere = [];
                
                $cardSql = $cardModel->leftjoin('sys_station_config', 'c_card.station_id', '=', 'sys_station_config.id')->where($cardWhere);
                if (array_has($search, 'station')) {
                    $cardSql = $cardSql->orWhere('sys_station_config.station_name', 'like', '%'.array_get($search, 'station').'%')
                                ->orWhere('sys_station_config.station_code', 'like', '%'.array_get($search, 'station').'%');
                    // $cardSql = ['sys_station_config.station_name', 'like', '%'.array_get($search, 'station').'%'];
                    // $orWhere[] = ['sys_station_config.station_code', 'like', '%'.array_get($search, 'station').'%'];
                }
                $card = $cardSql->first();
                if (empty($card)) {
                    $result = [];
                    $result['data'] = [];
                    $result['count'] = 0;
                    $result['page'] = intval($pageIndex);
                    $result['pageSize'] = intval($pageSize);
                    $result['pageCount'] = 0;
                    return $result;
                }
                //查询到了，增加订单id等于这个卡的订单id的条件
                $where[] = ['c_sale_order.id', $card->order_id];
        }

        $offset = ($pageIndex-1) * $pageSize;
        DB::connection()->enableQueryLog();
        // $sql = $this->where($where['where']);
        
        $sql = $this->leftjoin('sys_customer', 'c_sale_order.customer_id', '=', 'sys_customer.id')
            ->leftjoin('wf_process', 'c_sale_order.order_no', '=', 'wf_process.business_order')
            ->where($where)
            ->orderBy('c_sale_order.create_time', 'desc');
        
        //如果是导卡的时候不要结束的订单
        if (array_has($search, 'isSpecial') && $search['isSpecial'] == 1) {
            $sql = $sql->whereNotIn('c_sale_order.status', [self::STATUS_END, self::STATUS_DELETE]);
        }

        // 如果是销售人员自己看自己的订单
        if($user['isSeller'] === true){
            $sql = $sql->where('c_sale_order.create_user_id', $user['id']);
        }

        // $cardSql = $cardModel->leftjoin('sys_station_config,' 'c_card.station_id', '=', 'sys_station_config.id');
        $count = $sql->count('c_sale_order.id');
        $orders = $sql->offset($offset)->limit($pageSize)->select([
            'c_sale_order.is_overflow_stop','c_sale_order.id', 'c_sale_order.order_no', 'c_sale_order.order_num', 'c_sale_order.amount', 'c_sale_order.customer_id', 'c_sale_order.customer_name', 'c_sale_order.contacts_name', 'c_sale_order.contacts_mobile', 
            'c_sale_order.operator_type', 'c_sale_order.industry_type', 'c_sale_order.card_type', 'c_sale_order.standard_type', 'c_sale_order.model_type','c_sale_order.is_flow',
            'c_sale_order.is_sms','c_sale_order.is_voice','c_sale_order.flow_package_id','c_sale_order.sms_package_id','c_sale_order.voice_package_id','c_sale_order.silent_date','c_sale_order.create_time','c_sale_order.status',
            'c_sale_order.flow_card_price', 'c_sale_order.sms_card_price', 'c_sale_order.voice_card_price', 
            'sys_customer.id as customerId', 'sys_customer.customer_code','sys_customer.account_manager_name',
            'wf_process.business_order', 'wf_process.end_time',
            // 'c_card.order_id', 'c_card.iccid', 'c_card.card_no', 'c_card.station_id',
            // 'sys_station_config.id', 'sys_station_config.station_name', 'sys_station_config.station_code'
            ])->get();
            // dd(DB::getQueryLog());

        foreach ($orders as $order) {
            //查询套餐
            $packageModel = new Package;
            $smsPackage = null;
            if (intval($order->is_sms) === 1) {
                $smsPackage = $packageModel->where('id', $order->sms_package_id)->first(['id', 'package_name']);
            }
            $order->sms_package_name = empty($smsPackage) ? '' : $smsPackage->package_name;

            $flowPackage = null;
            if (intval($order->is_flow) === 1) {
                $flowPackage = $packageModel->where('id', $order->flow_package_id)->first(['id', 'package_name']);
            }
            $order->flow_package_name = empty($flowPackage) ? '' : $flowPackage->package_name;

            $voicePackage = null;
            if (intval($order->is_voice) === 1) {
                $voicePackage = $packageModel->where('id', $order->voice_package_id)->first(['id', 'package_name']);
            }
            $order->voice_package_name = empty($voicePackage) ? '' : $voicePackage->package_name;
        }
        
        $pageCount = ceil($count/$pageSize); #计算总页面数 
        $result = [];
        $result['data'] = $orders;
        $result['count'] = intval($count);
        $result['page'] = intval($pageIndex);
        $result['pageSize'] = intval($pageSize);
        $result['pageCount'] = intval($pageCount);
        return $result;
    }


    /**
     * 获取订单详情
     */
    function getOrder($no) {
        return $this->where('order_no', $no)->first();
    }

    /**
     * 修改订单状态
     */
    function saveStatus(string $no, $status) {
        $order = $this->getOrder($no);
        $order->status = $status;
        $order->save();
    }

    /**
     * 更新订单信息
     */
    function saveOrder($no, $reqs) {
        $order = $this->getOrder($no);
        $reqs = array_except($reqs, ['token', 'id', 'orderNo','q']);
        foreach ($reqs as $key => $value) {
            $field = snake_case($key);//把驼峰格式换成带下划线的格式
            $order->$field = $value;
        }
        $res = $order->save();
        if (!$res) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 创建
     */
    function add(array $request) {

        if (empty($request['user'])) {
            throw new CommonException(Errors::NOT_LOGIN);
        }

        //计算总金额
        DB::beginTransaction();
        $amount = ($request['flowCardPrice'] + $request['smsCardPrice'] + $request['voiceCardPrice']) * $request['orderNum'];
        if ($amount != $request['amount']) {
            throw new CommonException(Errors::ORDER_AMOUNT_ERROR);
        }

        $time = now();
        $this->id = getUuid($this->table);
        $this->order_no = $request['orderNo'];
        $this->customer_id = $request['customerId'];
        $this->customer_name = $request['customerName'];
        $this->contacts_name = $request['contactsName'];
        $this->contacts_mobile = $request['contactsMobile'];
        $this->operator_type = $request['operatorType'];
        $this->industry_type = $request['industryType'];
        $this->model_type = $request['modelType'];
        $this->standard_type = $request['standardType'];
        $this->describe = $request['describe'];
        $this->silent_date = $request['silentDate'];
        $this->real_name_type = $request['realNameType'];
        $this->flow_card_price = $request['flowCardPrice'];
        $this->sms_card_price = $request['smsCardPrice'];
        $this->voice_card_price = $request['voiceCardPrice'];
        $this->pay_type = $request['payType'];
        $this->order_num = $request['orderNum'];
        $this->amount = $amount;
        $this->address_name = $request['addressName'];
        $this->address_phone = $request['addressPhone'];
        $this->address = $request['address'];
        $this->express_arrive_day = $request['expressArriveDay'];
        $this->is_overflow_stop = $request['isOverflowStop'];
        if (array_has($request, 'express') && !empty($request['express'])) {
            $this->express = array_get($request, 'express', '');
        }
        if (array_has($request, 'expressNum') && !empty($request['expressNum'])) {
            $this->express_num = array_get($request, 'expressNum', '');
        }
        if (array_has($request, 'expressAmount') && !empty($request['expressAmount'])) {
            $this->express_amount = array_get($request, 'expressAmount', 0);
        }
        if (array_has($request, 'specialRequirements') && !empty($request['specialRequirements'])) {
            $this->special_requirements = array_get($request, 'specialRequirements', '');
        }
        $this->card_type = $request['cardType'];
        $this->card_style = 0;  //默认普通卡
        $this->create_time = $time;
        $this->update_time = $time;
        $this->payment_method = 0; //默认账户余额抵扣
        $this->create_user_id = $request['user']['id'];
        //套餐
        if (array_has($request, 'isSms') && !empty($request['isSms']) && intval($request['isSms']) === 1
            && array_has($request, 'smsPackageId') && !empty($request['smsPackageId'])
            && array_has($request, 'smsExpiryDate') && !empty($request['smsExpiryDate']) && $request['smsExpiryDate'] > 0) {
            $this->is_sms = $request['isSms'];
            $this->sms_package_id = $request['smsPackageId'];
            $this->sms_expiry_date = $request['smsExpiryDate'];
        }
        //流量必填
        $this->is_flow = $request['isFlow'];
        $this->flow_package_id = $request['flowPackageId'];
        $this->flow_expiry_date = $request['flowExpiryDate'];
        
        //如果语音卡，语音必填
        if ($request['cardType'] == self::CARD_TYPE_VOICE) {
            $this->is_voice = $request['isVoice'];
            $this->voice_package_id = $request['voicePackageId'];
            $this->voice_expiry_date = $request['voiceExpiryDate'];
        } else if (array_has($request, 'isVoice') && !empty($request['isVoice']) && intval($request['isVoice']) === 1
            && array_has($request, 'voicePackageId') && !empty($request['voicePackageId'])
            && array_has($request, 'voiceExpiryDate') && !empty($request['voiceExpiryDate']) && $request['voiceExpiryDate'] > 0) {
            $this->is_voice = $request['isVoice'];
            $this->voice_package_id = $request['voicePackageId'];
            $this->voice_expiry_date = $request['voiceExpiryDate'];
        }

        $res = $this->save();
        if (!$res) {
            DB::rollBack();
            throw new CommonException(Errors::DATABASE_ERROR);
        }
        //创建开卡订单给6个角色发消息提醒 
        event(new MatterEvent(self::TASK_CODE,'有新的开卡订单', '有新的开卡订单待处理', $request['user']));
        //开启流程
        $this->startProcess(self::TASK_CODE, $request['orderNo'], $request['user'], $request['customerName']);
        DB::commit();
    }

}
