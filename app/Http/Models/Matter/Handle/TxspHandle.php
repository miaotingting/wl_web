<?php

namespace App\Http\Models\Matter\Handle;

use App\Exceptions\CommonException;
use App\Http\Models\Finance\CustomerAccountModel;
use App\Http\Models\Finance\TSysCustomerWithdrawModel;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;

/**
 * 提现
 */
class TxspHandle extends BaseHandle {

    protected $agreeHandle = [
        'xsqr' => 'xsqrHandle',
        'xszjsp' => 'xszjqrHandle',
        'cwqr' => 'cwqrHandle',
        'zcqr' => 'zcqrHandle',
    ];

    protected $rejectHandle = [
        'xsqr' => 'statusRejectHandle',
        'xszjsp' => 'statusRejectHandle',
        'cwqr' => 'statusRejectHandle',
        'zcqr' => 'statusRejectHandle',
    ];

    protected $deleteHandle = [
        'xsqr' => 'shqrDeleteHandle',
        'xszjsp' => 'shqrDeleteHandle',
        'zcqr' => 'shqrDeleteHandle',
        'cwqr' => 'shqrDeleteHandle',
    ];

    protected $MAX_NUM = 4;

    /**
     * 售后确认的作废
     */
    protected function shqrDeleteHandle() {
        //修改订单状态
        $model = new TSysCustomerWithdrawModel();
        $model->saveStatus($this->orderNo, TSysCustomerWithdrawModel::STATUS_DELETE);
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
        $model = new TSysCustomerWithdrawModel;
        $model->saveStatus($this->orderNo, TSysCustomerWithdrawModel::STATUS_REJECT);
    }

    /**
     * 销售确认的处理
     */
    protected function xsqrHandle() {
        //修改订单状态
        $model = new TSysCustomerWithdrawModel();
        $model->saveStatus($this->orderNo, TSysCustomerWithdrawModel::STATUS_CHECKING);
    }

    /**
     * 销售总监审批的处理
     */
    protected function xszjqrHandle() {
        //修改订单状态
        $model = new TSysCustomerWithdrawModel();
        $model->saveStatus($this->orderNo, TSysCustomerWithdrawModel::STATUS_CHECKING);
    }

    /**
     * 财务确认的处理
     */
    protected function cwqrHandle() {
        //查询订单id
        $model = new TSysCustomerWithdrawModel;
        $temp = $model->where('withdraw_code', $this->orderNo)->first(['status','customer_id','withdraw_code', 'amount', 'pay_status']);
        //判断状态
        if ($temp->status != TSysCustomerWithdrawModel::STATUS_PAYMENTING) {
            DB::rollback();
            throw new CommonException(Errors::TEMP_STATUS_ERROR);
        }
        
        $temp->status = TSysCustomerWithdrawModel::STATUS_PAYMENTED;
        $temp->pay_status = TSysCustomerWithdrawModel::STATUS_PAYMENTED;

        //扣钱
        $customerAccountModel = new CustomerAccountModel;
        $account = $customerAccountModel->where('id', $temp->customer_id)->first();
        //如果没有就创建并减少余额
        if (empty($account)) {
            $customerAccountModel->balance_amount = -$temp->amount;
            $customerAccountModel->id = $temp->customer_id;
            $customerAccountModel->save();
        } else {
            //提现到一级客户余额 客户余额减少提现金额
            $account->balance_amount = bcsub(strval($account->balance_amount), strval($temp->amount), 2);
            $account->save();
        }

        $temp->save();
    }

    /**
     * 总裁审核的处理
     */
    protected function zcqrHandle() {
        //查询订单id
        $model = new TSysCustomerWithdrawModel;
        $temp = $model->where('withdraw_code', $this->orderNo)->first(['status']);
        //判断状态
        if ($temp->status != TSysCustomerWithdrawModel::STATUS_CHECKING) {
            DB::rollback();
            throw new CommonException(Errors::TEMP_STATUS_ERROR);
        }
        
        $temp->status = TSysCustomerWithdrawModel::STATUS_PAYMENTING;
        $temp->save();
    }
}