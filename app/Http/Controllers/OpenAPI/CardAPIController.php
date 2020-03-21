<?php

namespace App\Http\Controllers\OpenAPI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\CommonException;
use App\Http\Models\Card\CardModel;
use App\Http\Models\OpenAPI\CardAPIModel;

class CardAPIController extends Controller
{
    protected $rules = [
            'clientId'=>'required',
            'cardNo'=>'required',
            'sign'=>'required',
        ];
    protected $messages = [
            'required'=>'缺少必填参数:attribute',
        ];
    /*
     * 获取卡片详细信息
     */
    public function getCardInfo(Request $request){
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new CardAPIModel)->getCardInfo($request->all());
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
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
                'clientId'=>'调用能力ID',
                'cardNo'=>'卡号或ICCID',
                'sign'=>'签名',
            ]);
        if($validator->fails()){
            return setFResult(600001, $validator->errors()->first());
        }
        
        return 1;
    }
    
    
    

}



