<?php

namespace App\Http\Controllers\WeChat;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\WeChat\WeChatSmsModel;
use Illuminate\Support\Facades\Validator;

class WeChatSmsController extends Controller
{
    protected $rules = [
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    /**
     * 获取短信详情
     * 
     */
    public function mobileSmsList(Request $request)
    {
        try{
            $this->rules['cardNo'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new WeChatSmsModel)->mobileSmsList($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('106011');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = Validator::make($input,$this->rules,$this->messages,[
            'cardNo'=>'卡号',
        ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }

    


}
