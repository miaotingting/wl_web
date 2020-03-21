<?php

namespace App\Http\Controllers\Card;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Exports\ExpireCardExport;
use App\Http\Models\Card\CardModel;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Models\Card\TCCardRestartModel;
use App\Http\Models\Card\TCCardRestartDetailModel;
use App\Exports\RestartCardListExport;
use Illuminate\Support\Facades\Validator;
use App\Exports\MyCardExport;
use App\Exports\CustomerCardExport;
use App\Http\Models\Customer\Customer;

class CardController extends Controller
{
    protected $rules = [
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    /**
     * 我的卡片/批量导入 模板下载
     * get.api/Card/exportTemplate
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
     * post.api/Card/importCards
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
        if(!$request->has('gateWayId') || empty($request->post('gateWayId'))){
            throw new CommonException('300003');
        }
        if(!$request->has('orderId') || empty($request->post('orderId'))){
            throw new CommonException('300003');
        }
        if(!$request->has('cardType') || empty($request->post('cardType'))){
            throw new CommonException('300003');
        }
        if(!$request->has('cardFile')){
            throw new CommonException('300003');
        }
        $stationId = $request->post("stationId");// 落地ID
        $gateWayId = $request->post("gateWayId");// 网关ID
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
        $msg = (new CardModel())->saveEntity($cards,$orderId,$stationId,$gateWayId,$cardType,$user,$filePath);
        return   setFResult('0',$msg);
    }

    /*
     * 我的卡片
     * get.api/Card/myCard
     */
    public function myCard(Request $request){
        try{
            $result = (new CardModel)->myCard($request->all(), $this->user,"myCard");
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }
    /*
     * 客户卡片
     * get.api/Card/customerCard
     */
    public function customerCard(Request $request){
        try{
            $result = (new CardModel)->myCard($request->all(), $this->user,"customerCard");
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }
    /*
     * 批量开卡
     * post.api/Card/openCard
     */
    public function openCard(Request $request){
        try{
            $result = (new CardModel)->openCard($request->all(), $this->user);
            if($result > 0 ){
                return $this->success([]);
            }else{
                throw new CommonException('106013');
            }
        } catch (Exception $ex) {
            throw new CommonException('106013');
        }
    }
    /*
     * 批量回收
     * post.api/Card/recycleCard
     */
    public function recycleCard(Request $request){
        try{
            $result = (new CardModel)->recycleCard($request->all(), $this->user);
            if($result == FALSE){
                throw new CommonException('106014');
            }else{
                return setFResult('0', $result['message']);
            }
        } catch (Exception $ex) {
            throw new CommonException('106014');
        }
    }
    /*
     * 根据ID获取卡片详细信息
     * 
     */
    public function getCardInfo($id){
        try{
            $result = (new CardModel)->getCardInfo($id, $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }
    /*
     * 获取卡片的用量历史
     * get.api/Card/getUsedHistory/{$id}
     */
    public function getUsedHistory(Request $request,$id){
        try{
            $result = (new CardModel)->getUsedHistory($id,$request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 实时更新卡片的活动状态
     */
    public function updateMachineStatus($id){
        try{
            $result = (new CardModel)->updateMachineStatus($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106018');
        }
    }
    /*
     * 本月到期卡片
     */
    public function getExpireCard(Request $request){
        try{
            $result = (new CardModel)->myCard($request->all(), $this->user,"expireCard");
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }
    /*
     * 导出到期卡片
     */
    public function expireCardExcel(Request $request){
        try{
            $crData = (new Customer)->getCustomerOrRoleByUserId($this->user);
            $export = new ExpireCardExport($request->all(), $this->user,$crData);
            return Excel::download($export, 'expireCard.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');
        }
    }
    /*
     * 新建停复机申请
     * post.api/Card/addRestartCard
     */
    public function addRestartCard(Request $request){
        try{
            $this->rules['operateType'] = 'required';
            $this->rules['customerId'] = 'required';
            $this->rules['cardNo'] = 'required';
            $this->rules['applyReason'] = 'required';
            $this->rules['stationId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCCardRestartModel)->addRestartCard($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('106022');
            }
        } catch (Exception $ex) {
            throw new CommonException('106022');
        }
    }
    /*
     * 停复机管理列表
     * get.api/Card/restartList
     */
    public function restartList(Request $request){
        try{
            $result = (new TCCardRestartModel)->restartList($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 停复机卡片详情列表
     * 
     */
    public function restartCardList(Request $request){
        try{
            $this->rules['restartId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCCardRestartDetailModel)->restartCardList($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 导出停复机卡片详情
     */
    public function restartCardExcel(Request $request){
        try{
            $this->rules['restartId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            
            $export = new RestartCardListExport($request->all(), $this->user);
            return Excel::download($export, 'restartCard.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');
        }
    }
    /*
     * 导出我的卡片
     * get.api/Card/MyCardExport
     */
    public function MyCardExport(Request $request){
        try{
            $crData = (new Customer)->getCustomerOrRoleByUserId($this->user);
            $export = new MyCardExport($request->all(),$this->user,$crData);
            
            return Excel::download($export, 'MyCard.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');
        }
    }
    /*
     * 导出客户卡片
     * get.api/Card/customerCardExport
     */
    public function customerCardExport(Request $request){
        try{
            $crData = (new Customer)->getCustomerOrRoleByUserId($this->user);
            $export = new CustomerCardExport($request->all(),$this->user,$crData);
            //print_r($export);exit;
            return Excel::download($export, 'customerCard.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = Validator::make($input,$this->rules,$this->messages,[
            'operateType'=>'操作类型',
            'customerId'=>'客户名称',
            'cardNo'=>'停复机卡号',
            'applyReason'=>'申请原因',
            'restartId'=>'停复机申请单ID',
            'stationId'=>'落地',
        ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }
    
    
    

}



