<?php

namespace App\Http\Controllers\Customer;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Admin\User;
use Illuminate\Support\Facades\Validator;

class CustomerUserController extends Controller
{
    protected $rules = [
            'realName'=>'required',
            'mobile'=>'required|mobile',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'min'=>':attribute 长度不符合要求',
            'max'=>':attribute 长度不符合要求',
            'mobile'=>':attribute 格式错误',
            'email'=>':attribute 格式错误',
        ];
    
    /* 
     * post.api/Customer/users
     * 新建客户的用户
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $result = (new User)->add($request->all(), $this->user,2);

            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101004');
            }    
        } catch (Exception $ex) {
            throw new CommonException('101004');
        }
        
    }
    /*
     * put.api/Customer/users
     * 编辑客户的用户
     */
    public function update(Request $request,$id){
        try{
            $validate = $this->validateStr($request->all(),'edit');
            if($validate != 1){
                return $validate;
            }
            $result = (new User)->updates($request->all(), $this->user,$id,2);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101005');
            }  
        } catch (Exception $ex) {
            throw new CommonException('101005');
        }
        
    }
    
    
    /*
     * 验证器
     */
    public function validateStr($input,$type){
        if($type == "add"){
            $this->rules['userName'] = 'required|min:2|max:20';
            $this->rules['userPwd'] = 'required|min:6|max:16';
        }
        if(!empty($input['email'])){
            $this->rules['email'] = 'email';
        }
        
        $validator = Validator::make($input,$this->rules,$this->messages,[
            ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }

}
