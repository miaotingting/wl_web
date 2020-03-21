<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\CommonException;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrderCardsExport;
use App\Http\Models\Operation\StoreOutModel;
use App\Http\Models\Admin\TypeDetailModel;
use Illuminate\Support\Facades\DB;

class StoreOutController extends Controller
{
    //get.api/Operation/storeOutList 出库管理列表
    public function storeOutList(Request $request)
    {
        if($request->has('search') && !empty($request->get('search'))){
            $search = json_decode($request->get('search'),TRUE);
        }else{
            $search = [];
        }
        $storeOutList = (new StoreOutModel())->getStoreOutList($request, $search);
        return setTResult($storeOutList);
    }

    //get.api/Operation/storeOutShow/{storeId} 出库订单查看和审核页面公用
    public function storeOutShow(Request $request,$storeOutId)
    {
        $showObj = DB::table('c_store_out as out')->where('out.id',$storeOutId)
                ->leftJoin('c_operate_maintain as maintain', 'out.id', '=', 'maintain.out_order_id')
                ->leftJoin('c_sale_order as order', 'out.order_id', '=', 'order.id')
                ->leftJoin('c_package as package1', 'maintain.ready_package_id', '=', 'package1.id')
                ->leftJoin('c_package as package2', 'order.flow_package_id', '=', 'package2.id')
                ->first(['out.id','out.store_out_order','out.out_type','out.out_date',
                     'out.status','out.remark','maintain.ready_package_id','order.flow_package_id',
                     'package1.package_name as readyPackageName','package2.package_name as orderPackageName',
                     'order.order_num']);
        // 处理int返回值
        $outType = TypeDetailModel::getDetailsByCode('store_out_type');
        $outStatus = TypeDetailModel::getDetailsByCode('store_out_status');
        $showObj->out_type = $outType[$showObj->out_type];
        $showObj->status = $outStatus[$showObj->status];
        $returnData = json_encode($showObj);
        $returnData = json_decode($returnData,true);
        return setTResult($returnData);
    }

    //post.api/Operation/checkOutOrder/{storeOutId} 出库订单审核
    public function checkOutOrder(Request $request,$storeOutId)
    {
        if(empty($this->user)){
            throw new CommonException('300001');
        }
        if(!$request->has('toExamine') || empty($request->post('toExamine'))){
            throw new CommonException('300003');
        }
        $msg = (new StoreOutModel())->toExamineSet($request,$storeOutId,$this->user);
        return setFResult('0',$msg);
    }

    /**
     * 运营管理/出库管理：出库单-卡片列表
     * get.api/Operation/outCardsInfo
     * @return void
     * @author xyh
     */
    public function outCardsInfo(Request $request)
    {
        try{
            if(!$request->has('orderId') || empty($request->get('orderId'))){
                throw new CommonException('300003');
            }
            $StoreOutDetailEntity = new StoreOutModel();
            $result = $StoreOutDetailEntity->getOrderCards($request, $request->post('orderId'));
            return setTResult($result);
        } catch (Exception $ex) {
            throw new CommonException('103058');
        }
    }

    /**
     * 出库卡片详情导出（订单片详情）
     * get.api/Operation/orderCardExportExcel
     * @param Request $request
     * @return void
     * @author xyh
     */
    public function orderCardExportExcel(Request $request){
        try{
            if(!$request->has('orderId') || empty($request->get('orderId'))){
                throw new CommonException('300003');
            }
            $export = new OrderCardsExport($request->get('orderId'), $this->user);
            return Excel::download($export, 'orderCardsExcel.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');//导出失败
        }
    }

}
