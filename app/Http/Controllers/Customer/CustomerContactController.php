<?php

namespace App\Http\Controllers\Customer;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Customer\CustomerContact;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\CommonException;

class CustomerContactController extends Controller
{
    protected $rules = [
            'contactName'=>'required',
            'contactSex'=>'required',
            'contactMoible'=>'required|mobile',
            
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'min'=>':attribute 长度不符合要求',
            'max'=>':attribute 长度不符合要求',
            'mobile'=>':attribute 格式错误',
            'unique'=>'该:attribute已经被注册',
            'email'=>':attribute 格式错误',
        ];
    /*
     * get.api/Customer/contact
     * 客户联系人列表
     */
    public function index(Request $request){
        try{
            $result = (new CustomerContact)->getContacts($request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /* 
     * post.api/Customer/contact
     * 新建客户联系人
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $input = $request->all();
            $data = (new Customer)->addContact($input,$this->user,$input['customerId'],'contact');
            if($data){
                return $this->success([]);
            }else{
                throw new CommonException('102008');
            }
        } catch (Exception $ex) {
            throw new CommonException('102008');
        }
        
    }
    /*
     * put.api/Customer/contact
     * 编辑客户联系人
     */
    public function update(Request $request,$id){
        try{
            $validate = $this->validateStr($request->all(),'edit');
            if($validate != 1){
                return $validate;
            }
            $result = (new Customer)->updateContact($request->all(),$id,'contact');
            if($result > 0){
                return setTResult([]);
            }else{
                throw new CommonException('102009');
            }
        } catch (Exception $ex) {
            throw new CommonException('102009');
        }
        
    }
    /*
     * delete.api/Customer/contact
     * 删除客户联系人
     */
    public function destroy($id){
        try{
            $result = (new CustomerContact)->destroyContact($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('102010');
            }
        } catch (Exception $ex) {
            throw new CommonException('102010');
        }
        
    }
    /*
     * put.api/Customer/setMainContact
     * 设置客户联系人为主要联系人
     */
    public function setMain(Request $request){
        try{
            $result = (new CustomerContact)->setMain($request->all());
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('102013');
            }
        } catch (Exception $ex) {
            throw new CommonException('102013');
        }
        
    }
    
    /*
     * 验证器
     */
    public function validateStr($input,$type){
        if($type == "add"){
            $this->rules['customerId'] = 'required';
        }
        if(!empty($input['contactEmail'])){
            $this->rules['contactEmail'] = 'email';
        }
        $validator = Validator::make($input,$this->rules,$this->messages,[
            'contactName'=>'联系人姓名',
            'contactSex'=>'联系人性别',
            'customerId'=>'客户ID',
            'contactMoible'=>'联系人手机号',
            'contactEmail'=>'联系人邮箱',
            ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }

}
