<?php

namespace App\Http\Models\Order;

use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeModel;
use App\Http\Models\BaseModel;
use App\Http\Models\Operation\StoreOutModel;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;
use App\Events\MatterEvent;

class TCCardRefundOrderModel extends BaseModel
{
    //
    protected $table = 'c_card_refund_order';

    const STATUS_START = '1';
    const STATUS_CHECKING = '2';
    const STATUS_WAIT_REFUND = '4';
    const STATUS_WAIT_IN_STORE = '3';
    const STATUS_END = '5';
    const STATUS_REJECT = '6';
    const STATUS_DELETE = '7';

    const TASK_CODE = 'tksp';

    public $dicArr = [
        "status" => TypeModel::REFUND_CARD_STATUS,
    ];

    private function getWhere(array $search) {
        $where = [];
        if (count($search) > 0) {
            //增加搜索条件
            if (array_has($search, 'refundNo')) {
                //如果有退货单号
                $where['c_card_refund_order.no'] = ['like', array_get($search, 'refundNo')];
            }
            if (array_has($search, 'orderNo')) {
                //如果有开卡单号
                $where['c_card_refund_order.order_no'] = ['like', array_get($search, 'orderNo')];
            }
            if (array_has($search, 'customerName')) {
                //如果有客户名
                $where['sys_customer.customer_name'] = ['like', array_get($search, "customerName")];
            }
            if (array_has($search, 'customerCode')) {
                //如果有客户代码
                $where['sys_customer.customer_code'] = ['like', array_get($search, 'customerCode')];
            }
            if (array_has($search, 'status')) {
                //如果有状态
                $where['c_card_refund_order.status'] = array_get($search, 'status');
            }
        }
        return $where;
    }

    /**
     * 获取退货列表
     * @param [int] $pageIndex 页码
     * @param [int] $pageSize 每页数量
     * @param [array] $search 搜索数组
     */
    function getOrders(int $pageIndex, int $pageSize, array $search) {
        $fileds = ['c_card_refund_order.id','c_card_refund_order.no','c_card_refund_order.order_no','c_card_refund_order.count','c_card_refund_order.amount','c_card_refund_order.desc','c_card_refund_order.create_time','c_card_refund_order.in_store_time','c_card_refund_order.status','c_card_refund_order.warehouse_id','c_card_refund_order.customer_id',
                    'sys_customer.customer_name', 'sys_customer.customer_code', 'sys_customer.customer_type',
                    'c_sale_order.order_num', 'c_sale_order.amount as order_amount',
                    DB::raw("CONCAT('(',t_sys_customer.customer_code,')',t_sys_customer.customer_name) AS customer_full_name")];
        $joins = [
            ['sys_customer', 'c_card_refund_order.customer_id', '=', 'sys_customer.id'],
            ['c_sale_order', 'c_sale_order.order_no', '=', 'c_card_refund_order.order_no'],
        ];
        $where = $this->getWhere($search);
        return $this->joinQueryPage($pageSize, $pageIndex, $fileds, $where, $joins);
    }

    /**
     * 获取退货详情
     * @param [string] $no 退货单号
     */
    function getOrder(string $no) {
        $fields = [
            'c_card_refund_order.no', 'c_card_refund_order.order_no','c_card_refund_order.count','c_card_refund_order.amount','c_card_refund_order.desc','c_card_refund_order.status',
            'c_sale_order.order_num','c_sale_order.amount as order_amount','c_sale_order.flow_card_price','c_sale_order.sms_card_price','c_sale_order.voice_card_price',
        ];
        return $this->leftjoin('c_sale_order', 'c_sale_order.order_no', '=', 'c_card_refund_order.order_no')->where('c_card_refund_order.no', $no)->first($fields);
    }

    /**
     * 创建退货订单
     * @param [string] $orderNo 开卡订单号
     * @param [string] $desc 退货原因
     * @param [int] $count 退货数量
     * @param [array] $cards 退货的卡片
     */
    function add(string $orderNo, string $desc, int $count,  array $cards, $user) {
        //查询开卡订单
        $order = SaleOrderModel::where('order_no', $orderNo)->first(['id','customer_id', 'customer_name', 'flow_card_price', 'sms_card_price', 'voice_card_price']);
        if (empty($order)) {
            throw new CommonException(Errors::ORDER_NOT_FOUND);
        }
        $price = ($order->flow_card_price + $order->sms_card_price + $order->voice_card_price);
        $amount = bcmul(strval($price), strval($count), 2);
        $storeOut = StoreOutModel::where('order_id', $order->id)->first(['out_warehouse_id']);
        if (empty($storeOut)) {
            throw new CommonException(Errors::STORE_OUT_NOT_FOUND);
        }
        DB::beginTransaction();
        $this->id = getUuid('CCRO');
        $this->no = getOrderNo('TK');
        $this->order_no = $orderNo;
        $this->count = $count;
        $this->amount = $amount;
        $this->desc = $desc; 
        $this->create_time = date('Y-m-d H:i:s');
        $this->status = self::STATUS_START;
        $this->warehouse_id = $storeOut->out_warehouse_id;
        $this->customer_id = $order->customer_id;
        $detailModel = new TCCardRefundOrderDetailModel;
        $detailModel->batchAdd($this->no, $cards);

        $this->save();

        //发送通知
        event(new MatterEvent(self::TASK_CODE,'有新的退卡订单', '有新的退卡订单待处理', $user));

        //开启流程
        $this->startProcess(self::TASK_CODE, $this->no, $user, $order->customer_name);
        DB::commit();
    }

    /**
     * 修改状态
     */
    function saveStatus(string $no, string $status) {
        $refundOrder = $this->where('no', $no)->first();
        $refundOrder->status = $status;
        $refundOrder->save();
    }

    /**
     * 修改入库时间
     */
    function saveStoreTime(string $no) {
        $refundOrder = $this->where('no', $no)->first();
        $refundOrder->in_store_time = date('Y-m-d H:i:s');
        $refundOrder->save();
    }

}
