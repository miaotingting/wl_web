<?php

namespace App\Http\Controllers\Operation;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\CommonException;
use App\Http\Models\Operation\TCWarehouseOrderModel;
use App\Http\Models\Operation\TCWarehouseOrderDetailModel;
use App\Exports\WareCardExport;
use Maatwebsite\Excel\Facades\Excel;

class TCWarehouseOrderDetailController extends Controller
{
    protected $rules = [
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    
    /**
     * 入库订单卡片 模板下载
     * get.api/Opertain/warehouseTemplate
     */
    public function exportTemplate()
    {
        $path = 'template/operation/import_warehouse_card.xls';
        return response()->download(public_path($path));
    }
    /*
     * 导入入库订单卡片
     */
    public function importWarehouseCards(Request $request){
        // 验证用户是否登录
        if(empty($user = $this->user)){
            throw new CommonException('300001');
        }
        if(!$request->has('orderId') || empty($request->post('orderId'))){
            throw new CommonException('103154');//入库订单ID不能为空
        }
        if(!$request->has('cardFile')){
            throw new CommonException('103155');//请传入卡片excel文件
        }
        $orderId = $request->post("orderId");// 订单ID
        (new TCWarehouseOrderDetailModel)->getOrderStatus($orderId);//判断是否需要导入
        $cardFile = $request->file('cardFile');//Excel文件
        $ext = $cardFile->getClientOriginalExtension();  
        $fileSize = ($cardFile->getClientSize()) / 1048576;//MB
        if(!$cardFile->isValid()){
            throw new CommonException('106001');
        }
        if($ext != 'xls' && $ext != 'xlsx'){
            throw new CommonException('106002');
        }
        if((int)$fileSize > 15){
            throw new CommonException('103167');
        }
        $random_str = random_str();
        // upload/card/importCards.xlsx
        $path = $request->file('cardFile')->storeAs("upload", "importWarehouseCards{$random_str}.{$ext}");
        $filePath = storage_path("app/upload/importWarehouseCards{$random_str}.{$ext}");
        if(is_file($filePath)){
            // 从第二条读到最后，地1,2列不允许为空,中间有断点就返回数据
            $header = ['0'=>'cardNo','1'=>'iccid','2'=>'imsi','3'=>'cardMaker','4'=>'sliceType',
                '5'=>'physicalType','6'=>'boardColor','7'=>'networkStandard','8'=>'isPrintCardNo'];
            $cards = importExcel($filePath,$header,$ext,2,null,[1,2]);
        }else{
            throw new CommonException('106004');
        }
        // 当前支持导入卡片数量10万张
        if(count($cards) > 100000){
            throw new CommonException('103156');
        }
        //判断如果导入量大于这订单需要的导入量才提示错误
        (new TCWarehouseOrderModel)->estimateNum($orderId,count($cards));
        // 验证通过处理卡片
        $msg = (new TCWarehouseOrderDetailModel())->saveEntity($cards,$orderId,$user,$filePath);
        return   setFResult('0',$msg);
    }
    /*
     * 入库订单->数据初始化
     * 删除入库订单详情表中此订单的卡片
     */
    public function  dataInit(Request $request){
        try{
            $this->rules['orderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseOrderDetailModel)->dataInit($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('103157');
            } 
        } catch (Exception $ex) {
            throw new CommonException('103157');
        }
    }
    /*
     * 入库订单:卡片详情
     */
    public function getWareOrderCards(Request $request){
        try{
            $this->rules['orderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseOrderDetailModel)->getWareOrderCards($request->all(),$this->user,'order');
            if($result){
                return $this->success($result);
            }else{
                throw new CommonException('101010');
            } 
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 库存卡片
     */
    public function getWarehouseCards(Request $request){
        try{
            $result = (new TCWarehouseOrderDetailModel)->getWarehouseCards($request->all(),$this->user);
            if($result){
                return $this->success($result);
            }else{
                throw new CommonException('101010');
            } 
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 入库订单->卡片详情导出
     */
    public function wareCardExport(Request $request){
        try{
            $this->rules['orderId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $export = new WareCardExport($request->all(), $this->user);
            return Excel::download($export, 'restartCard.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            'orderId'=>'入库订单ID',
        ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }
    

}
