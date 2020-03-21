<?php

namespace App\Exports;

use App\Exceptions\CommonException;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Operation\StoreOutDetailModel;
use App\Http\Models\Operation\StoreOutModel;

class OrderCardsExport implements FromCollection,WithHeadings,WithMapping
{
    /**
     * 构造函数
     * @param [type] $input 初始化入参
     * @param [type] $loginUser 登录用户
     */
    function __construct($input,$loginUser)
    {
        $this->input = $input;
        $this->loginUser = $loginUser;
    }

    public function headings(): array
    {
        $heads=['卡号','iccid','imsi','运营商批次号','仓库名称','制卡商','切片类型','物理类型',
            '卡板颜色','网络制式','是否印制卡号','流量类型','卡片类型','APN','是否开通短信',
            '流量池类型','是否超流量停机'];
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
        $rows=[$row->card_no,$row->iccid,$row->imis,$row->operator_batch_no,$row->ware_name,
               $row->card_maker,$row->slice_type,$row->physical_type,$row->board_color,$row->network_standard,
               $row->is_print_card_no,$row->flow_type,$row->card_type,$row->APN,
               $row->is_open_sms,$row->pool_type,$row->is_overflow_stop];
        return $rows;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        if(empty($this->loginUser)){
            throw new CommonException('300001');
        }
        $StoreOutEntity = StoreOutModel::where('order_id',$this->input)->where('status','<>',2)->first();
        if($StoreOutEntity->status == 3){
            // 如果状态为已出库去已出库库存查看片详情
            $sql = StoreOutDetailModel::FROM('c_store_out_detail as outDetail')->where('store_out_id',$StoreOutEntity->id)
                ->leftJoin('c_warehouse_order_detail_out as warehouseDetail','outDetail.iccid','=', 'warehouseDetail.iccid')
                ->leftJoin('c_warehouse_order as warehouseOrder','warehouseDetail.order_id','=','warehouseOrder.id'); 
        }else{
            $sql = StoreOutDetailModel::FROM('c_store_out_detail as outDetail')->where('store_out_id',$StoreOutEntity->id)
                ->leftJoin('c_warehouse_order_detail as warehouseDetail','outDetail.iccid','=', 'warehouseDetail.iccid')
                ->leftJoin('c_warehouse_order as warehouseOrder','warehouseDetail.order_id','=','warehouseOrder.id'); 
        }
        //总条数
        $count = $sql->count('outDetail.id');
        if($count > 50000){
            throw new CommonException('106025');
        }
        $list = $sql->get(['outDetail.card_no','outDetail.iccid','outDetail.imsi',
                        'warehouseOrder.operator_batch_no','warehouseOrder.ware_name',
                        'warehouseDetail.card_maker','warehouseDetail.slice_type',
                        'warehouseDetail.physical_type','warehouseDetail.board_color',
                        'warehouseDetail.network_standard','warehouseDetail.is_print_card_no',
                        'warehouseDetail.flow_type','warehouseDetail.card_type',
                        'warehouseDetail.APN','warehouseDetail.is_open_sms',
                        'warehouseDetail.pool_type','warehouseDetail.is_overflow_stop']);
        $operateMaintainFlowType = TypeDetailModel::getDetailsByCode('operate_maintain_flowtype');
        $operateMaintainPoolType = TypeDetailModel::getDetailsByCode('operate_maintain_pooltype');
        $cardType = TypeDetailModel::getDetailsByCode('card_type');
        if(!$list->isEmpty()){
            // 处理int状态
            foreach($list as &$val){
                $val->flow_type = empty($val->flow_type)?null:$operateMaintainFlowType[$val->flow_type]['name'];
                if(empty($val->pool_type)){
                    $val->pool_type = $operateMaintainPoolType[0]['name'];
                }else{
                    $val->pool_type = $operateMaintainPoolType[$val->pool_type]['name'];
                }
                $val->card_type = empty($val->card_type)?null:$cardType[$val->card_type]['name'];
                $val->is_open_sms = $val->is_open_sms == 1?'是':'否';
                $val->is_overflow_stop = $val->is_overflow_stop == 1?'是':'否';
                $val->is_print_card_no = $val->is_print_card_no == 1?'是':'否';
            }
        }
        return new Collection($list);
    }
}
