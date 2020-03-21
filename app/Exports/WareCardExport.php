<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Http\Models\Operation\TCWarehouseOrderDetailModel;

class WareCardExport implements FromCollection,WithHeadings,WithMapping
{

    function __construct($input,$loginUser)
    {
        $this->input = $input;
        $this->loginUser = $loginUser;
    }

    public function headings(): array
    {
        $heads=['卡号','ICCID','IMSI','制卡商','切片类型','物理类型','卡板颜色','网络制式',
            '是否印制卡号','创建时间'];
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
        
        $rows=[$row->card_no,$row->iccid,$row->imsi,$row->card_maker,$row->slice_type,
               $row->physical_type,$row->board_color,$row->network_standard,$row->is_print_card_no,
               $row->create_time];
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
        
        $where = array();
        if(isset($this->input['search']) && !empty($this->input['search'])){
            $search = json_decode($this->input['search'],TRUE);
            $where = (new TCWarehouseOrderDetailModel)->getOrderSearchWhere($search);
        }
        $where[] = ['od.order_id','=',$this->input['orderId']];
        $sqlObject = DB::table('c_warehouse_order_detail as od');
        $count = $sqlObject->count('od.id');//总条数
        
        if($count > 50000){
            throw new CommonException('106025');
        }
        $data = $sqlObject->orderBy('od.create_time','DESC')->where($where)
                ->get(['od.id','od.card_no','od.iccid','od.imsi','od.card_maker','od.slice_type',
                    'od.physical_type','od.board_color','od.network_standard','od.is_print_card_no',
                    'od.create_time']);
        if(!$data->isEmpty()){
            foreach($data as $value){
                $value->is_print_card_no = $value->is_print_card_no==1?'是':'否';
            }
        }
        return new Collection($data);
    }
}
