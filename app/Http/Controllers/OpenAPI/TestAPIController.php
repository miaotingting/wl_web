<?php

namespace App\Http\Controllers\OpenAPI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Models\OpenAPI\TestAPIModel;

class TestAPIController extends Controller
{
    protected $rules = [
            'clientId'=>'required',
        ];
    protected $messages = [
            'required'=>'缺少必填参数:attribute',
        ];
   
    /*
     * 获取签名
     * get.api/TestAPI/getSignAPI
     * 参数：clientId必填
     * 其他参数：根据接口需要填入不同参数
     * 按填入顺序进行加密
     */
    public function getSign(Request $request){
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TestAPIModel)->getSign($request->all());
            if($result['status']){
                return setTResult($result['data'],'查询成功');
            }else{
                return setFResult($result['code'],$result['msg']);
            }
        } catch (Exception $ex) {
            return setFResult('999999','查询失败');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
                'clientId'=>'客户ID',
            ]);
        if($validator->fails()){
            return setFResult('600001', $validator->errors()->first());
        }
        return 1;
    }
    
    
    

}



