<?php

namespace App\Http\Controllers\ReportForm;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Sms\SmsReceiveLogModel;
use App\Http\Models\Card\TCCardDateUsedModel;
use App\Exports\MonthUsedExport;
use Maatwebsite\Excel\Facades\Excel;

class MonthUsedReportController extends Controller
{
    /*
     * 
     * 月用量列表
     * get.api/ReportForm/monthUsedReport
     */
    public function getMonthUsed(Request $request)
    {
        try{
            $result = (new TCCardDateUsedModel)->getMonthUsed($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 月用量列表导出
     * get.api/ReportForm/monthUsedExcel
     */
    public function monthUsedExcel(Request $request){
        try{
            $export = new MonthUsedExport($request->all(), $this->user);
            return Excel::download($export, 'monthUsed.xls');
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }
   
    

}



