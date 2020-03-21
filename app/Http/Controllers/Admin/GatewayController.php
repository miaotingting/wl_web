<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\Gateway;
use App\Exceptions\CommonException;

class GatewayController extends Controller
{
    protected $rules = [
        'gatewayName'=>'required',
        'gatewayType'=>'required',
        'gatewayIp'=>'required',
        'gatewayPort'=>'required',
        'spId'=>'required',
        'spCode'=>'required',
        'sharedSecret'=>'required',
        'connectCount'=>'required',
        'timeOut'=>'required',
        'serviceId'=>'required',
        'isUse'=>'required',
    ];
    protected $messages = [
        'required'=>':attribute 为必填项',
    ];
    /*
     * get.api/Admin/gateways
     * 网关信息列表
     */
    public function getGateways(Request $request){
        try{
            $result = (new Gateway())->getGateways($request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * post.api/Admin/gateway
     * 添加网关信息
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            //print_r($request->all());exit;
            $result = (new Gateway)->addGateway($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101251');
            }
        } catch (Exception $ex) {
            throw new CommonException('101251');
        }
        
    }
    /**
     * put.api/Admin/gateway/3c6befa6-99ef-52cd-ad33-90dc12779ddb
     * 在存储器中更新指定网关信息
     */   
    public function update(Request $request,$id){
        try{
            $validate = $this->validateStr($request->all(),'edit',$id);
            if($validate != 1){
                return $validate;
            }
            $result = (new Gateway)->updateGateway($request->all(),$id,$this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101252');
            }
        } catch (Exception $ex) {
            throw new CommonException('101252');
        }
        
    }
    /*
     * delete.api/Admin/gateway/3c6befa6-99ef-52cd-ad33-90dc1277
     * 删除网关信息
     */
    public function destroy($id){
        try{
            $result = (new Gateway)->destroyGateway($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101253');
            }
        } catch (Exception $ex) {
            throw new CommonException('101253');
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
