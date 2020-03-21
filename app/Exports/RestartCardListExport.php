<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Http\Models\Card\TCCardRestartDetailModel;
use App\Http\Models\Admin\TypeDetailModel;

class RestartCardListExport implements FromCollection,WithHeadings,WithMapping
{

    function __construct($input,$loginUser)
    {
        $this->input = $input;
        $this->loginUser = $loginUser;
    }

    public function headings(): array
    {
        $heads=['卡号','iccid','客户名称','落地名称','开卡时间','激活时间','服务期止','操作类型',
            '创建时间','停复机时间','申请状态'];
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
        
        $rows=[$row->card_no,$row->iccid,$row->customer,$row->station_name,$row->sale_date,
               $row->active_date,$row->valid_date,$row->operate_type,$row->create_time,$row->operate_time,
               $row->status];
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
        $orWhere = '';
        if(isset($this->input['search']) && !empty($this->input['search'])){
            $search = json_decode($this->input['search'],TRUE);
            $where = (new TCCardRestartDetailModel)->getSearchWhere($search);
            if(isset($search['customer']) && !empty($search['customer'])){
                $orWhere = $search['customer'];
            }
        }
        $where[] = ['rd.restart_id','=',$this->input['restartId']];
        $sqlObject = DB::table('c_card_restart_detail as rd')
                ->leftJoin('c_card_restart as restart','restart.id','=','rd.restart_id')
                ->leftJoin('c_card as card','rd.card_no','=','card.card_no')
                ->leftJoin('sys_customer as customer','customer.id','=','card.customer_id')
                ->where($where);
        if(!empty($orWhere)){
            $sqlObject = $sqlObject->orWhere(function ($query) use ($orWhere) {
                            $query->where('customer.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('customer.customer_code','like','%'.$orWhere.'%');
                        });
        }
        
        $count = $sqlObject->count('rd.id');//总条数
        if($count > 50000){
            throw new CommonException('106025');
        }
        
        $data = $sqlObject->orderBy('rd.create_time','DESC')
            ->get(['rd.id','rd.card_no','rd.iccid','restart.operate_type','restart.station_name',
                'rd.operate_time','rd.create_time','rd.status','card.sale_date','card.active_date',
                'card.valid_date','customer.customer_code','customer.customer_name']);
        
        if(!$data->isEmpty()){
            $statusGroup = TypeDetailModel::getDetailsByCode('card_restart_detail_status');
            $operatorTypeGroup = TypeDetailModel::getDetailsByCode('card_restart_operate_type');
            foreach($data as $value){
                $value->customer = '('.$value->customer_code.')'.$value->customer_name;
                $value->status = $statusGroup[$value->status]['name'];
                $value->operate_type = $operatorTypeGroup[$value->operate_type]['name'];
                unset($value->customer_code);
                unset($value->customer_name);
            }
        }
        return new Collection($data);
    }
}
