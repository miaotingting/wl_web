<?php

namespace App\Http\Controllers\OpenAPI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Models\OpenAPI\SmsAPIModel;
use App\Exceptions\CommonException;

class SmsAPIController extends Controller
{
    protected $rules = [
            'clientId'=>'required',
            'sign'=>'required',
        ];
    protected $messages = [
            'required'=>'缺少必填参数:attribute',
        ];
    /*
     * 发送短信
     */
    public function smsSend(Request $request){
        try{
            $validate = $this->validateStr('sendSms',$request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new SmsAPIModel)->smsSend($request->all());
            if($result['status']){
                return setTResult($result['data'],'提交成功');
            }else{
                return setFResult($result['code'],$result['msg']);
            }
        } catch (Exception $ex) {
            return setFResult(999999,'提交失败');
        }
    }
    /*
     * 短信发送状态查询
     */
    public function getSmsStatus(Request $request){
        try{
            $validate = $this->validateStr('sendSmsStatus',$request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new SmsAPIModel)->getSmsStatus($request->all());
            if($result['status']){
                return setTResult($result['data'],'查询成功');
            }else{
                return setFResult($result['code'],$result['msg']);
            }
        } catch (Exception $ex) {
            return setFResult(999999,'查询失败');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($type,$input){
        if($type == 'sendSms'){
            $this->rules['mobile'] = 'required';
            $this->rules['content'] = 'required';
        }elseif($type == 'sendSmsStatus'){
            $this->rules['msgId'] = 'required';
        }
        $validator = \Validator::make($input,$this->rules,$this->messages,[
                'clientId'=>'调用能力ID',
                'mobile'=>'发送号码',
                'content'=>'短信内容',
                'sign'=>'签名',
                'msgId'=>'短信ID',
            ]);
        if($validator->fails()){
            return setFResult(600001, $validator->errors()->first());
        }
        return 1;
    }
    
    
    

}



