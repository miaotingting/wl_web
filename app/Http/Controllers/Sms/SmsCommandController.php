<?php

namespace App\Http\Controllers\Sms;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Sms\SmsCommandModel;

class SmsCommandController extends Controller
{
    protected $rules = [
            'name'=>'required',
            'content'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    
    /*
     * get.api/Sms/smsCommand
     * 短信指令模板列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new SmsCommandModel)->getSmsCommand($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    /* 
     * post.api/Sms/smsCommand
     * 新建短信指令模板
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new SmsCommandModel)->addSmsCommand($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('108151');
            } 
        } catch (Exception $ex) {
            throw new CommonException('108151');
        }
        
    }
    /**
     * get.api/Sms/smsCommand/{id}
     * 新建短信指令模板
     */
    public function show($id){
        try{
            $result = (new SmsCommandModel)->getSmsCommandInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /**
     * put.api/Sms/smsCommand/{id}
     * 编辑短信指令模板
     */
    public function update(Request $request, $id){
        
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new SmsCommandModel)->updateSmsCommand($request->all(),$id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('108152');
            }
        } catch (Exception $ex) {
            throw new CommonException('108152');
        }
        
    }
    /*
     * delete.api/Sms/smsCommand/{id}
     * 删除短信指令模板
     */
    public function destroy($id){
        try{
            $result = (new SmsCommandModel)->destroySmsCommand($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('108153');
            }
        } catch (Exception $ex) {
            throw new CommonException('108153');
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



