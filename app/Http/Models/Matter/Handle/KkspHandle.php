<?php

namespace App\Http\Models\Matter\Handle;

use App\Http\Models\Matter\ProcessModel;
use App\Http\Models\Matter\DefineModel;
use App\Http\Models\Matter\NodeModel;
use App\Http\Models\Matter\ThreadModel;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Operation\StoreOutModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Finance\CustomerAccountModel;
use App\Http\Models\Operation\TCOperateMaintainModel;

class KkspHandle extends BaseHandle {

    protected $agreeHandle = [
        'xsqr' => 'xsqrHandle',
        'xszjsp' => 'xszjspHandle',
        'zcqr' => 'zcqrHandle',
        'cwqr' => 'cwqrHandle',
        'xzfk' => 'xzfkHandle',
        'sjtb' => 'sjtbHandle',
    ];

    protected $rejectHandle = [
        'xsqr' => 'statusRejectHandle',
        'xszjsp' => 'statusRejectHandle',
        'zcqr' => 'statusRejectHandle',
        'cwqr' => 'statusRejectHandle',
        'xzfk' => 'xzfkRejectHandle',
        'sjtb' => 'sjtbRejectHandle',
    ];

    protected $deleteHandle = [
        'xsqr' => 'xsqrDeleteHandle',
        'xszjsp' => 'xsqrDeleteHandle',
        'zcqr' => 'xsqrDeleteHandle',
        'cwqr' => 'xsqrDeleteHandle',
        'xzfk' => 'xsqrDeleteHandle',
        'sjtb' => 'xsqrDeleteHandle',
    ];

    protected $MAX_NUM = 6;

    /**
     * 销售确认的作废操作
     */
    protected function xsqrDeleteHandle() {
        //查询订单id
        $saleOrderModel = new SaleOrderModel;
        $order = $saleOrderModel->where('order_no', $this->orderNo)->first(['id', 'order_no']);
        $storeOutModel = new StoreOutModel;
        $storeOut = $storeOutModel->where('order_id', $order->id)->first();

        //判断出库状态，如果存在并且已经审核，那么要初始化
        if (!empty($storeOut)) {
            $model = new TCOperateMaintainModel;
            //查询id
            $maintain = $model->where('order_id', $order->id)->first(['id']);
            //初始化
            $model->setResetMaintain($maintain->id);
        }

        //修改订单状态
        $saleOrderModel = new SaleOrderModel;
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_DELETE);
    }

    /**
     * 不让作废的操作
     */
    protected function errorDeleteHandle() {
        //不允许删除
        DB::rollback();
        throw new CommonException(Errors::MATTER_DELETE_ERROR);
    }

    /**
     * 只修改状态的驳回操作
     */
    protected function statusRejectHandle() {
        //修改订单状态
        $saleOrderModel = new SaleOrderModel;
        $order = $saleOrderModel->where('order_no', $this->orderNo)->first(['id']);
        $out = StoreOutModel::where('order_id', $order->id)->first();
        //状态已出库就不让驳回
        if (!empty($out) && $out->status == 3) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_STORE_OUT_STATUS_CHECK);
        }
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_REJECT);
    }

    /**
     * 行政发卡的驳回操作
     */
    protected function xzfkRejectHandle() {
        //因为财务确认已经减少余额，所以这步驳回需要加上相应的余额
        //查询余额
        $saleOrderModel = new SaleOrderModel;
        $order = $saleOrderModel->where('order_no', $this->orderNo)->first(['order_no', 'customer_id', 'amount', 'id']);
        $out = StoreOutModel::where('order_id', $order->id)->first();
        //状态已出库就不让驳回
        if ($out->status == 3) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_STORE_OUT_STATUS_CHECK);
        }
        $customerAccountModel = new CustomerAccountModel;
        $account = $customerAccountModel->where('id', $order->customer_id)->first();
        //增加余额
        $account->balance_amount += $order->amount;
        $account->save();

        //修改订单状态
        $saleOrderModel = new SaleOrderModel;
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_REJECT);
    }

    /**
     * 数据同步不让驳回
     */
    protected function sjtbRejectHandle() {
        //不允许驳回
        DB::rollback();
        throw new CommonException(Errors::MATTER_STORE_OUT_STATUS_CHECK);
    }

    /**
     * 销售确认的处理
     */
    protected function xsqrHandle() {
        //修改订单状态
        $saleOrderModel = new SaleOrderModel;
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_CHECK);
    }

    /**
     * 销售总监审批的处理
     */
    protected function xszjspHandle() {
        //修改订单状态
        $saleOrderModel = new SaleOrderModel;
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_CHECK);
    }

    /**
     * 支撑确认的处理
     */
    protected function zcqrHandle() {
        //先导卡
        //查询订单id
        $saleOrderModel = new SaleOrderModel;
        $order = $saleOrderModel->where('order_no', $this->orderNo)->first(['id', 'order_no']);
        $storeOutModel = new StoreOutModel;
        $storeOut = $storeOutModel->where('order_id', $order->id)->first();
        if (empty($storeOut)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_STORE_OUT_EMPTY);
        }

        //修改订单状态
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_RECEIVABLES);
    }

    /**
     * 财务确认的处理
     */
    protected function cwqrHandle() {
        //查询余额
        $saleOrderModel = new SaleOrderModel;
        $order = $saleOrderModel->where('order_no', $this->orderNo)->first(['order_no', 'customer_id', 'amount']);
        $customerAccountModel = new CustomerAccountModel;
        $account = $customerAccountModel->where('id', $order->customer_id)->first();
        //如果没有就创建并扣除余额
        if (empty($account)) {
            $customerAccountModel->balance_amount = -$order->amount;
            $customerAccountModel->id = $order->customer_id;
            $customerAccountModel->save();
        } else {
            //扣除余额
            $account->balance_amount = bcsub(strval($account->balance_amount),strval($order->amount), 2);
            $account->save();
        }

        //修改订单状态
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_SHIPPED);
    }

    /**
     * 行政发卡的处理
     */
    protected function xzfkHandle() {
        //行政发卡的时候先确定是否审批
        //查询订单id
        $saleOrderModel = new SaleOrderModel;
        $order = $saleOrderModel->where('order_no', $this->orderNo)->first(['id', 'order_no']);
        $storeOutModel = new StoreOutModel;
        $storeOut = $storeOutModel->where('order_id', $order->id)->where('status',3)->first();
        //判断是否有出库状态的出库单
        if (empty($storeOut)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_STORE_OUT_STATUS_ERROR);
        }

        //修改订单状态
        $saleOrderModel->saveStatus($this->orderNo, SaleOrderModel::STATUS_DATA);
    }

    /**
     * 数据同步的处理
     */
    protected function sjtbHandle() {
        //修改订单状态
        $orderNo = $this->orderNo;
        $saleOrderModel = new SaleOrderModel;
        $saleOrderModel->saveStatus($orderNo, SaleOrderModel::STATUS_END);

        $order = $saleOrderModel->getOrder($orderNo);

        //修改卡状态
        $cardModel = new CardModel;
        $cardModel->where('order_id', $order->id)->where('status', CardModel::STATUS_WAIT_SYNC)->update(['status' => CardModel::STATUS_WAIT_ACTICITY]);
    }
}