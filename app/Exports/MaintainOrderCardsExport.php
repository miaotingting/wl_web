<?php

namespace App\Exports;

use App\Exceptions\CommonException;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Http\Models\Operation\StoreOutDetailModel;
use App\Http\Models\Operation\StoreOutModel;

class MaintainOrderCardsExport implements FromCollection,WithHeadings,WithMapping
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
            '卡板颜色','网络制式','是否印制卡号'];
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
        $rows=[$row->card_no,$row->iccid,$row->imis,$row->operator_batch_no,$row->ware_name,
               $row->card_maker,$row->slice_type,$row->physical_type,$row->board_color,$row->network_standard,
               $row->is_print_card_no];
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
                        'warehouseDetail.network_standard','warehouseDetail.is_print_card_no']);
        // 处理int状态
        foreach($list as &$val){
            $val->is_print_card_no = $val->is_print_card_no == 1?'是':'否';
        }
        return new Collection($list);
    }
}
