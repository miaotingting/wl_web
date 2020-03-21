<?php

namespace App\Exports;

use App\Http\Models\Card\CardModel;
use App\Http\Models\Operation\Package;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Http\Models\Card\TCCardDateUsedModel;

class MonthUsedExport implements FromCollection,WithHeadings,WithMapping
{

    function __construct($input,$loginUser)
    {
        $this->input = $input;
        $this->loginUser = $loginUser;
    }

    public function headings(): array
    {
        $heads =  ['卡号'];
        if(isset($this->input['month']) && !empty($this->input['month'])){
            $thisMonth = $this->input['month'];
        }else{
            $thisMonth = date('Y-m',time());
        }
        $monthArr = [];//存放要显示的6个月的月份
        for($i = 5; $i >= 0; $i--){
            $heads[] = date('Y-m', strtotime($thisMonth . ' -'.$i.' month'));
            $monthArr[] = date('Y-m', strtotime($thisMonth . ' -'.$i.' month')); 
        }
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
//        dd($row);
        $rows =  [$row['cardNo'],$row['firstMonth'],$row['secondMonth'],$row['thirdMonth'],$row['fourthMonth'],$row['fifthMonth'],
            $row['sixthMonth']];
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
        if(isset($this->input['month']) && !empty($this->input['month'])){
            $thisMonth = $this->input['month'];
        }else{
            $thisMonth = date('Y-m',time());
        }
        $monthArr = [];//存放要显示的6个月的月份
        for($i = 5; $i >= 0; $i--){
            $monthArr[] = date('Y-m', strtotime($thisMonth . ' -'.$i.' month')); 
        }
        if($this->loginUser['is_owner'] == 1){//网来员工
            $where = " where 1=1";
        }else{//客户
            $where = " where sorder.customer_id ='".$this->loginUser['customer_id']."'";
        }
        $manyCardNoWhere = "";
        if(isset($this->input['search']) && !empty($this->input['search'])){
            $search = json_decode($this->input['search'],TRUE);
            $searchWhere = (new TCCardDateUsedModel)->getSearchWhere($search);
            $where = $where.$searchWhere['where'];
            $manyCardNoWhere = $searchWhere['manyCardNoWhere'];
        }
        $sqlCount = (new TCCardDateUsedModel)->getSql('count', $thisMonth, $where,$manyCardNoWhere);
        $monthUsedData = DB::select($sqlCount);
        $count = count($monthUsedData);//总条数
        if($count > 50000){
            throw new CommonException('106025');
        }
        $data = [];
        if(!empty($monthUsedData)){
            $data = (new TCCardDateUsedModel)->clearUpMonthUsedList($monthUsedData,$monthArr);
        }
//        dd($data);exit;
        return new Collection($data);
    }
}
