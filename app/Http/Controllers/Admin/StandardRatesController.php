<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\StandardRates;
use App\Exceptions\CommonException;

class StandardRatesController extends Controller
{
    protected $rules = [
            'flowPrice'=>'required|numeric',
            'voicePrice'=>'required|numeric',
            'smsPrice'=>'required|numeric',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'numeric'=>':attribute 只能为数字',
        ];
    /*
     * get.api/Admin/rates
     * 资费列表
     */
    public function index(Request $request){
        try{
            $result = (new StandardRates)->getRates($request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /**
     * put.api/Admin/rates/3c6befa6-99ef-52cd-ad33-90dc12779ddb
     * 在存储器中更新指定资费信息
     */   
    public function update(Request $request,$id){
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new StandardRates)->updateRates($request->all(),$id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101251');
            }
        } catch (Exception $ex) {
            throw new CommonException('101251');
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
