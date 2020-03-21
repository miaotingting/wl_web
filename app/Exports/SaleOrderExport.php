<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeModel;
use App\Http\Models\Operation\Package;

class SaleOrderExport implements FromCollection,WithHeadings,WithMapping
{

    function __construct($search,$loginUser)
    {
        $this->search = $search;
        $this->loginUser = $loginUser;
    }

    public function headings(): array
    {
        $heads=['订单编号','客户编号','客户名称','运营商','卡类型','订单状态','卡型号','通讯制式',
            '行业用途','订单金额','采购数量','沉默期（月）','下单时间','订单结束时间','短信套餐','流量套餐',
            '语音套餐','联系人名称','联系人手机号'];
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
        
        $rows=[$row->order_no,$row->customer_code,$row->customer_name,$row->operator_type,$row->card_type,
            $row->status,$row->model_type,$row->standard_type,$row->industry_type,$row->amount,
            $row->order_num,$row->silent_date,$row->create_time,$row->end_time,$row->sms_package_name,
            $row->flow_package_name,$row->voice_package_name,$row->contacts_name,$row->contacts_mobile];
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
        
        $where = (new SaleOrderModel)->getWhere($this->search);
        //判断查询卡信息和落地信息
        if ((array_has($this->search, 'iccid') && !empty(array_get($this->search, 'iccid'))) 
            || (array_has($this->search, 'cardNo') && !empty(array_get($this->search, 'cardNo'))) 
            || (array_has($this->search, 'station') && !empty(array_get($this->search, 'station')))) {
                //先查询卡表和落地，如果查到了查询相应订单，如果没查到，返回空
                $cardModel = new CardModel;
                $cardWhere =[];
                if (array_has($this->search, 'iccid')) {
                    $cardWhere[] = ['c_card.iccid', 'like', '%'.array_get($this->search, 'iccid').'%'];
                }
                if (array_has($this->search, 'cardNo')) {
                    $cardWhere[] = ['c_card.card_no', 'like', '%'.array_get($this->search, 'cardNo').'%'];
                }
                $orWhere = [];
                
                $cardSql = $cardModel->leftjoin('sys_station_config', 'c_card.station_id', '=', 'sys_station_config.id')->where($cardWhere);
                if (array_has($this->search, 'station')) {
                    $cardSql = $cardSql->orWhere('sys_station_config.station_name', 'like', '%'.array_get($this->search, 'station').'%')
                                ->orWhere('sys_station_config.station_code', 'like', '%'.array_get($this->search, 'station').'%');
                   
                }
                $card = $cardSql->first();
                if (empty($card)) {
                    $result = [];
                    return new Collection($result);
                }
                //查询到了，增加订单id等于这个卡的订单id的条件
                $where[] = ['c_sale_order.id', $card->order_id];
        }
        $sql = SaleOrderModel::leftjoin('sys_customer', 'c_sale_order.customer_id', '=', 'sys_customer.id')
            ->leftjoin('wf_process', 'c_sale_order.order_no', '=', 'wf_process.business_order')
            ->where($where)
            ->orderBy('c_sale_order.create_time', 'desc');
        //如果是导卡的时候不要结束的订单
        if (array_has($this->search, 'isSpecial') && $this->search['isSpecial'] == 1) {
            $sql = $sql->whereNotIn('c_sale_order.status', [SaleOrderModel::STATUS_END, SaleOrderModel::STATUS_DELETE]);
        }
        // 如果是销售人员自己看自己的订单
        if($this->loginUser['isSeller'] === true){
            $sql = $sql->where('c_sale_order.create_user_id',$this->loginUser['id']);
        }
        $count = $sql->count('c_sale_order.id');
        if($count > 50000){
            throw new CommonException('106025');
        }
        $orders = $sql->select([
            'c_sale_order.is_overflow_stop','c_sale_order.id', 'c_sale_order.order_no', 'c_sale_order.order_num', 'c_sale_order.amount', 'c_sale_order.customer_id', 'c_sale_order.customer_name', 'c_sale_order.contacts_name', 'c_sale_order.contacts_mobile', 
            'c_sale_order.operator_type', 'c_sale_order.industry_type', 'c_sale_order.card_type', 'c_sale_order.standard_type', 'c_sale_order.model_type','c_sale_order.is_flow',
            'c_sale_order.is_sms','c_sale_order.is_voice','c_sale_order.flow_package_id','c_sale_order.sms_package_id','c_sale_order.voice_package_id','c_sale_order.silent_date','c_sale_order.create_time','c_sale_order.status', 
            'sys_customer.id as customerId', 'sys_customer.customer_code',
            'wf_process.business_order', 'wf_process.end_time',
            ])->get();
        //获取字典
        $operatorTypes = TypeDetailModel::getDetailsByCode(TypeModel::OPERATOR_TYPE);
        $industryTypes = TypeDetailModel::getDetailsByCode(TypeModel::INDUSTRY_TYPE);
        $cardTypes = TypeDetailModel::getDetailsByCode(TypeModel::CARD_TYPE);
        $standardTypes = TypeDetailModel::getDetailsByCode(TypeModel::STANDARD_TYPE);
        $modelTypes = TypeDetailModel::getDetailsByCode(TypeModel::MODEL_TYPE);
        $status = TypeDetailModel::getDetailsByCode(TypeModel::SALE_ORDER_STATUS);
        foreach ($orders as $order) {
            //查询套餐
            $packageModel = new Package;
            $smsPackage = null;
            if (intval($order->is_sms) === 1) {
                $smsPackage = $packageModel->where('id', $order->sms_package_id)->first(['id', 'package_name']);
            }
            $order->sms_package_name = empty($smsPackage) ? '' : $smsPackage->package_name;

            $flowPackage = null;
            if (intval($order->is_flow) === 1) {
                $flowPackage = $packageModel->where('id', $order->flow_package_id)->first(['id', 'package_name']);
            }
            $order->flow_package_name = empty($flowPackage) ? '' : $flowPackage->package_name;

            $voicePackage = null;
            if (intval($order->is_voice) === 1) {
                $voicePackage = $packageModel->where('id', $order->voice_package_id)->first(['id', 'package_name']);
            }
            $order->voice_package_name = empty($voicePackage) ? '' : $voicePackage->package_name;
            $order->operator_type = $operatorTypes[$order->operator_type]['name'];
            $order->industry_type = $industryTypes[$order->industry_type]['name'];
            $order->card_type = $cardTypes[$order->card_type]['name'];
            $order->standard_type = $standardTypes[$order->standard_type]['name'];
            $order->model_type = $modelTypes[$order->model_type]['name'];
            $order->status = $status[$order->status]['name'];
        }
        return new Collection($orders);
    }
}
