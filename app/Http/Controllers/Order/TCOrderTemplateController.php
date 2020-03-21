<?php

namespace App\Http\Controllers\Order;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\Validator;
use App\Http\Models\Order\TCOrderTemplateModel;


class TCOrderTemplateController extends Controller
{
    protected $rules = [
        'templateName'=>'required',
        'customerId' => 'required|exists:sys_customer,id',
        'contactsName'=>'required',
        'contactsMobile'=>'required',
        'operatorType'=>'required',
        'industryType'=>'required',
        'cardType'=>'required',
        'standardType'=>'required',
        'modelType'=>'required',
        'describe'=>'required',
        'flowCardPrice'=>'required',
        'smsCardPrice'=>'required_if:isSms,1',
        'voiceCardPrice' => 'required_if:cardType,1002',
        'isFlow' => 'required',
        'flowPackageId' => 'required',
        'flowExpiryDate' => 'required',
        'isSms' => 'required',
        'smsPackageId' => 'required_if:isSms,1',
        'smsExpiryDate' => 'required_if:isSms,1',
        'isVoice' => 'required_if:cardType,1002',
        'voicePackageId' => 'required_if:cardType,1002',
        'voiceExpiryDate' => 'required_if:cardType,1002',
    ];
    protected  $messages = [
        'templateName.required' => '计划名称为必填项',
        'customerId.required' => '客户id为必填项',
        'customerId.exists' => '客户id不存在',
        'contactsName.required' => '联系人名为必填项',
        'contactsMobile.required' => '联系人手机为必填项',
        'operatorType.required' => '运营商类型为必填项',
        'industryType.required' => '行业用途为必填项',
        'cardType.required' => '卡类型为必填项',
        'standardType.required' => '通讯制式为必填项',
        'modelType.required' => '卡型号为必填项',
        'describe.required' => '描述为必填项',
        'flowCardPrice.required' => '流量卡价格为必填项',
        'smsCardPrice.required_if' => '短信卡价格为必填项',
        'voiceCardPrice.required_if' => '语音卡价格为必填项',
        'isFlow.required' => '必须选择流量套餐',
        'flowPackageId.required' => '必须选择流量套餐',
        'flowExpiryDate.required' => '必须选择流量套餐',
        'isSms.required' => '是否选择短信套餐',
        'smsPackageId.required_if' => '必须选择短信套餐',
        'smsExpiryDate.required_if' => '短信套餐开通时效为必填项',
        'isVoice.required_if' => '必须选择语音套餐',
        'voicePackageId.required_if' => '必须选择语音套餐',
        'voiceExpiryDate.required_if' => '必须选择语音套餐',
        'templateCode.required' => '计划单号为必填项',
    ];
    /*
     * 计划列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new TCOrderTemplateModel)->getList($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /**
     * 创建计划
     */
    public function store(Request $request) {
        try {
            //参数验证
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            //参数处理
            $result = (new TCOrderTemplateModel)->add($request->all(),$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('107151');
            } 
        } catch(Exception $e) {
            throw new CommonException('107151');
        }
    }
    /*
     * 显示计划信息
     */
    public function show($id ){
        try{
            $result = (new TCOrderTemplateModel)->getOrderTemplate($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /**
     * 更新计划
     */
    public function update(Request $request, $id) {
        try {
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            //更新
            $result = (new TCOrderTemplateModel)->updateOrder($id, $request->all());
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('107152');
            } 
        } catch(Exception $e) {
            throw new CommonException('107152');
        }
    }
    /*
     * 删除资费计划
     */
    public function destroy($id){
        try{
            $result = (new TCOrderTemplateModel)->destroyOrder($id);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('110002');
            }
        } catch (Exception $ex) {
            throw new CommonException('110002');
        }
        
    }
    /**
     * 设置失效/生效
     */
    public function setStatus($id) {
        try {
            $result = (new TCOrderTemplateModel)->setStatus($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101009');
            } 
        } catch(Exception $e) {
            throw new CommonException('101009');
        }
    }
    /*
     * 更新计划名称
     */
    public function updateName(Request $request){
        try {
            $this->rules = [
                'templateCode'=>'required',
                'templateName'=>'required',
            ];
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCOrderTemplateModel)->updateName($request->all());
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('107156');
            } 
        } catch(Exception $e) {
            throw new CommonException('107156');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        
        $validator = Validator::make($input,$this->rules,$this->messages);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        
        return 1;
    }

}



