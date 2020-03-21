<?php

namespace App\Http\Models\Matter\Handle;

use App\Exceptions\CommonException;
use App\Http\Models\Finance\CustomerAccountModel;
use App\Http\Models\Finance\TSysCustomerWithdrawModel;
use App\Http\Models\Profit\TCProfitModel;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;

/**
 * 分润
 */
class FrspHandle extends BaseHandle {

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
        $model = new TCProfitModel();
        $model->saveStatus($this->orderNo, TCProfitModel::DELETE_STATUS);
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
        $model = new TCProfitModel;
        $model->saveStatus($this->orderNo, TCProfitModel::REJECT_STATUS);
    }

    /**
     * 销售确认的处理
     */
    protected function xsqrHandle() {
        //查询订单id
        $model = new TCProfitModel();
        $temp = $model->where('profit_code', $this->orderNo)->first(['id', 'profit_code','status']);
        //判断状态
        if ($temp->status != TCProfitModel::BEGIN_STATUS) {
            DB::rollback();
            throw new CommonException(Errors::TEMP_STATUS_ERROR);
        }
        
        $temp->status = TCProfitModel::CHECKING_STATUS;
        $temp->save();
    }

    /**
     * 销售总监审批的处理
     */
    protected function xszjqrHandle() {
    }

    /**
     * 财务确认的处理
     */
    protected function cwqrHandle() {
        
    }

    /**
     * 总裁审核的处理
     */
    protected function zcqrHandle() {
        //查询订单id
        $model = new TCProfitModel();
        $temp = $model->where('profit_code', $this->orderNo)->first(['id', 'profit_code','status']);
        //判断状态
        if ($temp->status != TCProfitModel::CHECKING_STATUS) {
            DB::rollback();
            throw new CommonException(Errors::TEMP_STATUS_ERROR);
        }
        
        $temp->status = TCProfitModel::CHECKED_STATUS;
        $temp->save();
    }
}