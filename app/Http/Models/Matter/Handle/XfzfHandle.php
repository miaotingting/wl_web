<?php

namespace App\Http\Models\Matter\Handle;

use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Order\TCOrderTemplateModel;

/**
 * 续费资费计划
 */
class XfzfHandle extends BaseHandle {

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
        'cwqr' => 'shqrDeleteHandle',
        'zcqr' => 'shqrDeleteHandle',
    ];

    protected $MAX_NUM = 4;

    /**
     * 售后确认的作废
     */
    protected function shqrDeleteHandle() {
        //修改订单状态
        $model = new TCOrderTemplateModel;
        $model->saveStatus($this->orderNo, TCOrderTemplateModel::STATUS_DELETE);
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
        $model = new TCOrderTemplateModel;
        $model->saveStatus($this->orderNo, TCOrderTemplateModel::STATUS_REJECT);
    }

    /**
     * 销售确认的处理
     */
    protected function xsqrHandle() {
        //修改订单状态
        $model = new TCOrderTemplateModel();
        $model->saveStatus($this->orderNo, TCOrderTemplateModel::STATUS_CHECKING);
    }

    /**
     * 销售总监审批的处理
     */
    protected function xszjqrHandle() {
        //修改订单状态
        $model = new TCOrderTemplateModel();
        $model->saveStatus($this->orderNo, TCOrderTemplateModel::STATUS_CHECKING);
    }

    /**
     * 财务确认的处理
     */
    protected function cwqrHandle() {
        //修改订单状态
        $model = new TCOrderTemplateModel();
        $model->saveStatus($this->orderNo, TCOrderTemplateModel::STATUS_CHECKING);
    }

    /**
     * 总裁审核的处理
     */
    protected function zcqrHandle() {
        //行政发卡的时候先确定是否审批
        //查询订单id
        $model = new TCOrderTemplateModel;
        $temp = $model->where('template_code', $this->orderNo)->first(['status', 'customer_id']);
        //判断状态
        if ($temp->status != TCOrderTemplateModel::STATUS_CHECKING) {
            DB::rollback();
            throw new CommonException(Errors::TEMP_STATUS_ERROR);
        }

        $model->setCustomerRenewalWay($temp->customer_id);
        
        $temp->status = TCOrderTemplateModel::STATUS_CHECKEND;
        $temp->save();
    }
}