<?php

namespace App\Http\Controllers\Operation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Exports\MaintainOrderCardsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Models\Operation\TCOperateMaintainModel;

class TCOperateMaintainController extends Controller
{
    /**
     * 运营维护/运营维护列表
     * get.api/Operation/index
     * @return void
     * @author xyh
     */
    public function index(Request $request)
    {
        try{
            if($request->has('search') && !empty($request->get('search'))){
                $search = json_decode($request->get('search'),TRUE);
            }else{
                $search = [];
            }
            $TCOperateMaintainEntity = new TCOperateMaintainModel();
            $result = $TCOperateMaintainEntity->getMaintainList($request, $search);
            return setTResult($result);
        } catch (Exception $ex) {
            throw new CommonException('103251');
        }
    }

    /**
     * 运营维护/批量维护 模板下载
     * get.api/Operation/exportTemplate
     * @return void
     * @author xyh
     */
    public function exportTemplate()
    {
        $path = 'template/card/import_card_operate.xls';
        return response()->download(public_path($path));
    }

    /**
     * 运营导卡:关联订单批量导入卡号
     * post.api/Operation/importCards
     * @param Request $request
     * @return void
     */
    public function importCards(Request $request)
    {
        // 验证用户是否登录
        if(empty($user = $this->user)){
            throw new CommonException('300001');
        }
        //验证参数
        if(!$request->has('stationId') || empty($request->post('stationId'))){
            throw new CommonException('300003');
        }
        if(!$request->has('gatewayId') || empty($request->post('gatewayId'))){
            throw new CommonException('300003');
        }
        if(!$request->has('orderId') || empty($request->post('orderId'))){
            throw new CommonException('300003');
        }
        if(!$request->has('cardType') || empty($request->post('cardType'))){
            throw new CommonException('300003');
        }
        if(!$request->has('isWhiteCard')){
            throw new CommonException('300003');
        }
       
        if($request->post('isWhiteCard') == 0){
            if(!$request->has('operatorPackageId') || empty($request->post('operatorPackageId'))){
                throw new CommonException('300003');
            }
        }
        
        if(!$request->has('readyPackageId') || empty($request->post('readyPackageId'))){
            throw new CommonException('300003');
        }
        if(!$request->has('flowType') || empty($request->post('flowType'))){
            throw new CommonException('300003');
        }
        if(!$request->has('APN') || empty($request->post('APN'))){
            throw new CommonException('300003');
        }
        if(!$request->has('wareId') || empty($request->post('wareId'))){
            throw new CommonException('300003');
        }
        if(!$request->has('cardFile')){
            throw new CommonException('300003');
        }
        $stationId = $request->post("stationId");// 落地ID
        $gateWayId = $request->post("gatewayId");// 网关ID
        $orderId = $request->post("orderId");// 订单ID
        $cardType =$request->post("cardType");// 卡类型:1001_流量卡，1002_语音卡
        $cardFile = $request->file('cardFile');//Excel文件
        $ext = $cardFile->getClientOriginalExtension();  
        $fileSize = ($cardFile->getClientSize()) / 1048576;//MB
        if(!$cardFile->isValid()){
            throw new CommonException('106001');
        }
        if($ext != 'xls' && $ext != 'xlsx'){
            throw new CommonException('106002');
        }
        if((int)$fileSize > 6){
            throw new CommonException('106003');
        }
        $random_str = random_str();
        // upload/card/importCards.xlsx
        $path = $request->file('cardFile')->storeAs("upload", "importCards{$random_str}.{$ext}");
        $filePath = storage_path("app/upload/importCards{$random_str}.{$ext}");
        if(is_file($filePath)){
            // 从第二条读到最后，地1,2列不允许为空,中间有断点就返回数据
            $header = ['0'=>'cardNo',"1"=>'iccid',"2"=>"imsi"];
            $cards = importExcel($filePath,$header,$ext,2,null,[1,2]);
        }else{
            throw new CommonException('106004');
        }
        // 当前支持导入卡片数量6万张
        if(count($cards) > 60000){
            throw new CommonException('106010');
        }
        // 验证通过处理卡片
        $TCOperateMaintainEntity = new TCOperateMaintainModel();
        $msg = $TCOperateMaintainEntity->saveEntity($cards,$orderId,$stationId,$gateWayId,$cardType,$user,$filePath,$request);
        return setFResult('0',$msg);
    }

    /**
     * 运营管理/运营维护：操作数据初始化
     * post.api/Operation/resetMaintainData
     * @return void
     * @author xyh
     */
    public function resetMaintainData(Request $request)
    {
        try{
            if(!$request->has('maintainId') || empty($request->post('maintainId'))){
                throw new CommonException('300003');
            }
            $TCOperateMaintainEntity = new TCOperateMaintainModel();
            $msg = $TCOperateMaintainEntity->setResetMaintain($request->post('maintainId'));
            return setFResult('0',$msg);
        } catch (Exception $ex) {
            throw new CommonException('103252');
        }
    }

    /**
     * 运营管理/运营维护：查看维护单
     * get.api/Operation/maintainOrderShow
     * @return void
     * @author xyh
     */
    public function maintainOrderShow(Request $request)
    {
        try{
            if(!$request->has('maintainId') || empty($request->get('maintainId'))){
                throw new CommonException('300003');
            }
            $maintainId = $request->get('maintainId');// 维护订单ID
            $TCOperateMaintainEntity = new TCOperateMaintainModel();
            $result = $TCOperateMaintainEntity->getMaintainOrderShow($maintainId);
            return setTResult($result);
        } catch (Exception $ex) {
            throw new CommonException('103252');
        }
    }

    /**
     * 运营管理/运营维护：修改维护单
     * post.api/Operation/updateMaintainOrder
     * @return void
     * @author xyh
     */
    public function updateMaintainOrder(Request $request)
    {
        try{
            // 验证用户是否登录
            if(empty($user = $this->user)){
                throw new CommonException('300001');
            }
            if(!$request->has('maintainId') || empty($request->post('maintainId'))){
                throw new CommonException('300003');
            }
            if(!$request->has('isWhiteCard')){
                throw new CommonException('300003');
            }
           
            if($request->post('isWhiteCard') == 0){
                if(!$request->has('operatorPackageId') || empty($request->post('operatorPackageId'))){
                    throw new CommonException('300003');
                }
            }
            if(!$request->has('readyPackageId') || empty($request->post('readyPackageId'))){
                throw new CommonException('300003');
            }
            if(!$request->has('flowType') || empty($request->post('flowType'))){
                throw new CommonException('300003');
            }
            if(!$request->has('APN') || empty($request->post('APN'))){
                throw new CommonException('300003');
            }
            if(!$request->has('wareId') || empty($request->post('wareId'))){
                throw new CommonException('300003');
            }
            $maintainId = $request->post('maintainId');// 维护订单ID
            $TCOperateMaintainEntity = new TCOperateMaintainModel();
            $msg = $TCOperateMaintainEntity->updateMaintainOrder($request,$maintainId);
            return setFResult('0',$msg);
        } catch (Exception $ex) {
            throw new CommonException('103252');
        }
    }

    /**
     * 运营管理/运营维护：维护订单卡片详情
     * get.api/Operation/getMaintainCards
     * @return void
     * @author xyh
     */
    public function getMaintainCards(Request $request)
    {
        try{
            if(!$request->has('orderId') || empty($request->get('orderId'))){
                throw new CommonException('300003');
            }
            $TCOperateMaintainEntity = new TCOperateMaintainModel();
            $result = $TCOperateMaintainEntity->getMaintainCards($request, $request->post('orderId'));
            return setTResult($result);
        } catch (Exception $ex) {
            throw new CommonException('103255');
        }
    }

    /**
     * 维护订单卡片列表导出（订单卡片列表）
     * get.api/Operation/maintainOrderCardsExportExcel
     * @param Request $request
     * @return void
     * @author xyh
     */
    public function maintainOrderCardsExportExcel(Request $request){
        try{
            if(!$request->has('orderId') || empty($request->get('orderId'))){
                throw new CommonException('300003');
            }
            $export = new MaintainOrderCardsExport($request->get('orderId'), $this->user);
            return Excel::download($export, 'maintainOrderCardsExcel.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');//导出失败
        }
    }

}



