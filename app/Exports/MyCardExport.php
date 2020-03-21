<?php

namespace App\Exports;

use App\Http\Models\Card\CardModel;
use App\Http\Models\Operation\Package;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Admin\TypeDetailModel;

class MyCardExport implements FromCollection,WithHeadings,WithMapping
{

    function __construct($input,$loginUser,$crData)
    {
        $this->input = $input;
        $this->loginUser = $loginUser;
        $this->crData = $crData;
        $this->type = 'myCard';
    }

    public function headings(): array
    {
        $heads =['卡号','iccid','imsi','订单编号','客户名称','运营商类型','落地名称','卡状态',
                '运营商到停状态','活动状态','发卡日期','激活日期','服务期止','卡余额(元)',
                '订单时效(月)','流量套餐','采购流量单价(元)','流量套餐总量(MB)',
                '流量使用量(MB)','流量套餐剩余量(MB)','短信套餐','短信套餐总量(条)','短信发送量(条)',
                '短信套餐剩余量(条)','语音套餐','语音套餐总量(分钟)','语音使用量(分钟)','语音套餐剩余量(分钟)'
                ];
        if($this->loginUser['is_owner'] == 0){ //客户:不显示落地名称
            if(!empty($this->crData['customerData'])){
            //客户级别是二级或三级不显示发卡时间，采购单价
                if($this->crData['customerData']->level > 1){ 
                    unset($heads[16]);
                    unset($heads[10]);
                    
                }
            }
            unset($heads[6]);
            
        }else{//网来员工：销售不显示落地
            if($this->crData['sellRole'] == 1){
                unset($heads[6]);
            }
        }
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
        if(empty($row->sms_total) ){
            $row->sms_residue = "";
        }else{
            $row->sms_residue = $row->sms_used>$row->sms_total ? 0 : bcsub($row->sms_total,$row->sms_used,2);
        }
        if(empty($row->flow_total) ){
            $row->flow_residue = "";
        }else{
            $row->flow_residue = $row->flow_used>$row->flow_total ? 0 : bcsub($row->flow_total,$row->flow_used,2);
        }
        if(empty($row->voice_total) ){
            $row->voice_residue = "";
        }else{
            $row->voice_residue = $row->voice_used>$row->voice_total ? 0 : bcsub($row->voice_total,$row->voice_used,2);
        }
        //开通时效
        $row->flow_expiry_date = (string)($row->flow_expiry_date * $row->flow_time_length);
        $rows =[$row->card_no,$row->iccid,$row->imsi,$row->order_no,$row->customer,$row->operator_type,
                $row->station_name,$row->status,$row->is_overflow_stop,$row->machine_status,$row->sale_date,
                $row->active_date,$row->valid_date,$row->card_account,$row->flow_expiry_date,
                $row->flow_package_name,$row->flow_card_price,$row->flow_total,$row->flow_used,$row->flow_residue,
                $row->sms_package_name,$row->sms_total,$row->sms_used,$row->sms_residue,
                $row->voice_package_name,$row->voice_total,$row->voice_used,$row->voice_residue
               ];
        if($this->loginUser['is_owner'] == 0){ //客户:不显示落地名称
            if(!empty($this->crData['customerData'])){
            //客户级别是二级或三级不显示发卡时间，采购单价
                if($this->crData['customerData']->level > 1){ 
                    unset($rows[16]);
                    unset($rows[10]);
                }
            }
            unset($rows[6]);
        }else{//网来员工：销售不显示落地
            if($this->crData['sellRole'] == 1){
                unset($rows[6]);
            }
        }
        return $rows;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $cardModel = new CardModel;
        if(empty($this->loginUser)){
            throw new CommonException('300001');
        }

        $loginUserWhereData = $cardModel->getLoginUserWhere($this->loginUser, $this->type);
        $where = $loginUserWhereData['where'];
        if(isset($this->input['search']) && !empty($this->input['search'])){
            //$search = json_decode($this->input['search'],TRUE);
            $where = $where.$cardModel->getWhere($this->input['search']);
        }
        //print_r($where);exit;
        $countSql = $cardModel->getSql('count', $where, $loginUserWhereData['isOwnerJoin']);
        $countData = DB::select($countSql);
        if($countData[0]->count>50000){
            throw new CommonException('106025');
        }
        $sql = $cardModel->getSql('excel', $where, $loginUserWhereData['isOwnerJoin']);
        //echo $sql;exit;
        $cardData = DB::select($sql);
        //echo count($cardData);exit;
        if(!empty($cardData)){
            $operatorTypeGroup = TypeDetailModel::getDetailsByCode('operator_type');
            $statusGroup = TypeDetailModel::getDetailsByCode('card_status');
            $machineStatusGroup = TypeDetailModel::getDetailsByCode('machine_status');
            foreach($cardData as $value){
                $value->operator_type = $operatorTypeGroup[$value->operator_type]['name'];//运营商类型
                $value->status = $statusGroup[$value->status]['name'];//卡片状态
                $value->machine_status = $machineStatusGroup[$value->machine_status]['name'];//活动状态
                $value->is_overflow_stop = $value->is_overflow_stop == 1?'超流量停机':'超流量不停机';
            }
        }
        return new Collection($cardData);
    }
}
