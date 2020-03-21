<?php

namespace App\Exports;

use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeDetailModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Exceptions\CommonException;

class ExpireCardExport implements FromCollection,WithHeadings,WithMapping
{

    function __construct($input,$loginUser,$crData)
    {
        $this->input = $input;
        $this->loginUser = $loginUser;
        $this->type = 'expireCard';
        $this->crData = $crData;
    }

    public function headings(): array
    {
        $heads =  ['卡号','iccid','落地名称','所属公司','客户经理','运营商','卡状态','活动状态',
                    '发卡日期','激活时间','服务期止','流量套餐','流量套餐总量(MB)','流量使用量(MB)',
                    '流量套餐剩余量(MB)','短信套餐','短信套餐总量(条)','短信发送量(条)','短信套餐剩余量(条)',
                    '语音套餐','语音套餐总量(分钟)','语音使用量(分钟)','语音套餐剩余量(分钟)'];
        if($this->loginUser['is_owner'] == 0){ //客户:不显示落地名称
            if(!empty($this->crData['customerData'])){
            //客户级别是二级或三级不显示发卡日期
                if($this->crData['customerData']->level > 1){ 
                    unset($heads[8]);
                }
            }
            unset($heads[2]);
        }else{//网来员工：销售不显示落地
            if($this->crData['sellRole'] == 1){
                unset($heads[2]);
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
        $rows =  [$row->card_no,$row->iccid,$row->station_name,$row->customer,$row->account_manager_name,$row->operator_type,
            $row->status,$row->machine_status,$row->sale_date,$row->active_date,$row->valid_date,$row->flow_package_name,
            $row->flow_total,$row->flow_used,$row->flow_residue,$row->sms_package_name,$row->sms_total,$row->sms_used,
            $row->sms_residue,$row->voice_package_name,$row->voice_total,$row->voice_used,$row->voice_residue];
        if($this->loginUser['is_owner'] == 0){ //客户:不显示落地名称
            if(!empty($this->crData['customerData'])){
            //客户级别是二级或三级不显示发卡日期
                if($this->crData['customerData']->level > 1){ 
                    unset($rows[8]);
                }
            }
            unset($rows[2]);
        }else{//网来员工：销售不显示落地
            if($this->crData['sellRole'] == 1){
                unset($rows[2]);
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
            $search = json_decode($this->input['search'],TRUE);
            $where = $where.$cardModel->getWhere($search);
        }
        $countSql = $cardModel->getSql('count', $where, $loginUserWhereData['isOwnerJoin']);
        $countCard = DB::select($countSql);
        if($countCard[0]->count > 50000){
            throw new CommonException('106025');
        }
        $sql = $cardModel->getSql('excel', $where, $loginUserWhereData['isOwnerJoin']);
        $cardData = DB::select($sql);
        if(!empty($cardData)){
            $operatorTypeGroup = TypeDetailModel::getDetailsByCode('operator_type');
            $statusGroup = TypeDetailModel::getDetailsByCode('card_status');
            $machineStatusGroup = TypeDetailModel::getDetailsByCode('machine_status');
            foreach($cardData as $value){
                $value->operator_type = $operatorTypeGroup[$value->operator_type]['name'];
                $value->status = $statusGroup[$value->status]['name'];
                $value->machine_status = $machineStatusGroup[$value->machine_status]['name'];
            }
        }
        
        return new Collection($cardData);
    }
}
