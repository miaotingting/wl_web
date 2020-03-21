<?php

namespace App\Http\Controllers\Sms;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Sms\SmsSendingModel;

class SmsSendingController extends Controller
{
    protected $rules = [
            'mobiles'=>'required',
            'content'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    /*
     * get.api/Sms/smsSending
     * 短信发送列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new SmsSendingModel)->getSmsSending($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /* 
     * 发送短信
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $result = (new SmsSendingModel)->addSms($request->all(), $this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('108001');
            }           
        } catch (Exception $ex) {
            //var_dump($ex->getMessage());exit;//获取异常信息
            throw new CommonException('108001');
        }
        
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }
    
    
    

}



