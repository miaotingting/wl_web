<?php

namespace App\Http\Models\Matter\Handle;

use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Card\CardModel;
use App\Exceptions\CommonException;
use App\Http\Models\Card\CardPackageModel;
use App\Http\Models\Card\TCCardDateUsedModel;
use App\Http\Models\Card\TCCardPackageFutureHisModel;
use App\Http\Models\Card\TCCardPackageFutureModel;
use App\Http\Models\Card\TCCardPackageHisModel;
use App\Http\Models\Common\TCConsumptionDetailHisModel;
use App\Http\Models\Common\TCConsumptionDetailModel;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Finance\CustomerAccountModel;
use App\Http\Models\Operation\StoreOutDetailModel;
use App\Http\Models\Operation\TCWarehouseModel;
use App\Http\Models\Operation\TCWarehouseOrderDetailOutModel;
use App\Http\Models\Order\TCCardRefundOrderDetailModel;
use App\Http\Models\Order\TCCardRefundOrderModel;

class TkspHandle extends BaseHandle {

    protected $agreeHandle = [
        'shqr' => 'shqrHandle',
        'shzjqr' => 'shzjqrHandle',
        'zjlqr' => 'zjlqrHandle',
        'cwqr' => 'cwqrHandle',
        'kgqr' => 'kgqrHandle',
    ];

    protected $rejectHandle = [
        'shqr' => 'statusRejectHandle',
        'shzjqr' => 'statusRejectHandle',
        'zjlqr' => 'statusRejectHandle',
        'cwqr' => 'statusRejectHandle',
        'kgqr' => 'kgqrRejectHandle',
    ];

    protected $deleteHandle = [
        'shqr' => 'shqrDeleteHandle',
        'shzjqr' => 'shqrDeleteHandle',
        'zjlqr' => 'shqrDeleteHandle',
        'cwqr' => 'shqrDeleteHandle',
        'kgqr' => 'shqrDeleteHandle',
    ];

    protected $MAX_NUM = 5;

    /**
     * 售后确认的作废
     */
    protected function shqrDeleteHandle() {
        //修改订单状态
        $model = new TCCardRefundOrderModel;
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_DELETE);
        //删除退卡的卡片
        TCCardRefundOrderDetailModel::where('no', $this->orderNo)->delete();
    }

    /**
     * 不让作废
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
        $model = new TCCardRefundOrderModel;
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_REJECT);
    }

    /**
     * 库管确认的驳回操作
     */
    protected function kgqrRejectHandle() {
        //因为财务已经把钱给客户了，所以这步驳回需要把余额减少
        //查询余额
        $model = new TCCardRefundOrderModel;
        $refund = $model->where('no', $this->orderNo)->first(['order_no', 'amount', 'status']);
        $order = SaleOrderModel::where('order_no', $refund->order_no)->first(['customer_id']);
        $customerAccountModel = new CustomerAccountModel;
        $account = $customerAccountModel->where('id', $order->customer_id)->first();
        //扣除余额
        $account->balance_amount -= $refund->amount;
        $account->save();
        //修改订单状态
        $model = new TCCardRefundOrderModel;
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_REJECT);
    }

    /**
     * 售后确认的处理
     */
    protected function shqrHandle() {
        //修改订单状态
        $model = new TCCardRefundOrderModel;
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_CHECKING);
    }

    /**
     * 售后总监审批的处理
     */
    protected function shzjqrHandle() {
        //修改订单状态
        $model = new TCCardRefundOrderModel;
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_CHECKING);
    }

    /**
     * 总经理确认的处理
     */
    protected function zjlqrHandle() {
        $model = new TCCardRefundOrderModel;
        //修改订单状态
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_WAIT_REFUND);
    }

    /**
     * 财务确认的处理
     */
    protected function cwqrHandle() {
        //查询余额
        $model = new TCCardRefundOrderModel;
        $refund = $model->where('no', $this->orderNo)->first(['order_no', 'amount', 'status']);
        //判断状态
        if ($refund->status != TCCardRefundOrderModel::STATUS_WAIT_REFUND) {
            DB::rollback();
            throw new CommonException(Errors::REFUND_STATUS_ERROR);
        }
        $order = SaleOrderModel::where('order_no', $refund->order_no)->first(['customer_id']);
        $customerAccountModel = new CustomerAccountModel;
        $account = $customerAccountModel->where('id', $order->customer_id)->first();
        //如果没有就创建并扣除余额
        if (empty($account)) {
            $customerAccountModel->balance_amount = $refund->amount;
            $customerAccountModel->id = $order->customer_id;
            $customerAccountModel->save();
        } else {
            //扣除余额
            $account->balance_amount += $refund->amount;
            $account->save();
        }

        //修改订单状态
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_WAIT_IN_STORE);
    }

    /**
     * 库管审核的处理
     */
    protected function kgqrHandle() {
        //行政发卡的时候先确定是否审批
        //查询订单id
        $model = new TCCardRefundOrderModel;
        $refund = $model->where('no', $this->orderNo)->first(['status', 'warehouse_id', 'order_no']);
        //判断状态
        if ($refund->status != TCCardRefundOrderModel::STATUS_WAIT_IN_STORE) {
            DB::rollback();
            throw new CommonException(Errors::REFUND_STATUS_ERROR);
        }

        //查询所有退货卡片
        $cards = TCCardRefundOrderDetailModel::where('no', $this->orderNo)->get();
        $cardNos = [];
        //卡片数量
        $num = 0;
        foreach ($cards as $card) {
            $cardNos[] = $card->card_no;
            $num++;
        }
        
        //插入待出库卡片
        $cardStr = implode("','",$cardNos);
        $sql = "INSERT INTO t_c_warehouse_order_detail (id,order_id,card_no,iccid,imsi,card_maker,slice_type,physical_type, board_color, network_standard, is_print_card_no, create_time) 
        SELECT cp.id,cp.order_id,cp.card_no,cp.iccid,cp.imsi,cp.card_maker,cp.slice_type,cp.physical_type, cp.board_color, cp.network_standard, cp.is_print_card_no, cp.create_time
        FROM
            t_c_warehouse_order_detail_out cp
        WHERE
            cp.card_no in ('$cardStr')";
        DB::insert($sql);
        //增加仓库内卡片数量
        TCWarehouseModel::where('id', $refund->warehouse_id)->increment('card_stock_num', $num);

        //把套餐数据保留起来
        $packageSql = "INSERT INTO t_c_refund_consumption (id,card_no,package_type,consumption,consumption_time,total,used,allowance) 
        SELECT cp.id,cp.card_no,cp.package_type,cp.consumption,cp.consumption_time,ccp.total,ccp.used,ccp.allowance
        FROM
            t_c_consumption_detail cp
        LEFT JOIN
            t_c_card_package ccp on cp.card_package_id = ccp.id
        WHERE
            cp.card_no in ('$cardStr')";
        DB::insert($packageSql);

        //把套餐历史的数据保留起来
        $packageHisSql = "INSERT INTO t_c_refund_consumption (id,card_no,package_type,consumption,consumption_time,total,used,allowance) 
        SELECT cp.id,cp.card_no,cp.package_type,cp.consumption,cp.consumption_time,ccp.total,ccp.used,ccp.allowance
        FROM
            t_c_consumption_detail_his cp
        LEFT JOIN
            t_c_card_package_his ccp on cp.card_package_id = ccp.id
        WHERE
            cp.card_no in ('$cardStr')";
        DB::insert($packageHisSql);

        //删除预生效
        TCCardPackageFutureModel::whereIn('card_no', $cardNos)->delete();
        //删除预生效历史
        TCCardPackageFutureHisModel::whereIn('card_no', $cardNos)->delete();
        //删除生效套餐
        CardPackageModel::whereIn('card_no', $cardNos)->delete();
        //删除生效套餐历史
        TCCardPackageHisModel::whereIn('card_no', $cardNos)->delete();
        //删除日用量
        TCCardDateUsedModel::whereIn('card_no', $cardNos)->delete();
        //删除账单
        TCConsumptionDetailModel::whereIn('card_no', $cardNos)->delete();
        //删除账单历史
        TCConsumptionDetailHisModel::whereIn('card_no', $cardNos)->delete();
        //删除已出库卡片
        TCWarehouseOrderDetailOutModel::whereIn('card_no', $cardNos)->delete();
        //删除store out detail
        StoreOutDetailModel::whereIn('card_no', $cardNos)->delete();
        //删除卡片
        CardModel::whereIn('card_no', $cardNos)->delete();

        //修改开卡订单状态
        $saleOrder = SaleOrderModel::where('order_no', $refund->order_no)->first(['id','order_num', 'status']);
        if ($saleOrder->order_num == $num) {
            //全部退货
            $saleOrder->status = SaleOrderModel::STATUS_PART_RETURN;
        } else {
            //部分退货
            $saleOrder->status = SaleOrderModel::STATUS_RETURN;
        }
        $saleOrder->save();

        //修改订单状态
        $model->saveStatus($this->orderNo, TCCardRefundOrderModel::STATUS_END);

        //修改入库时间
        $model->saveStoreTime($this->orderNo);
    }
}