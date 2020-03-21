<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Models\Admin\User;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;

class UserController extends Controller
{
    protected $rules = [
            'realName'=>'required',
            'departId'=>'required',
            'roleIds'=>'required',
            'mobile'=>'required|mobile'
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'unique'=>'该:attribute已经被注册',
            'min'=>':attribute 长度不符合要求',
            'max'=>':attribute 长度不符合要求',
            'mobile'=>':attribute 格式错误',
            'email'=>':attribute 格式错误',
        ];
    
    /*
     * 用户列表
     */
    public function getUsers(Request $request)
    {
        try{
            $userData = (new User())->getUsers($request->all());
            return $this->success($userData);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /* 
     * 将新创建的用户存储到存储器
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $result = (new User)->add($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101004');
            }           
        } catch (Exception $ex) {
            //var_dump($ex->getMessage());exit;//获取异常信息
            throw new CommonException('101004');
        }
        
    }
    /**
     * 显示指定用户
     *
     * @param int $id
     * 
     */
    public function show($id){
        try{
            if(empty($id)){
                throw new CommonException('101007');
            }
            $result = (new User)->getUserInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * 在存储器中更新指定用户
     *
     * @param int $id
     * 
     */
    public function update(Request $request, $id){
        try{
            $validate = $this->validateStr($request->all(),'edit',$id);
            if($validate != 1){
                return $validate;
            }
            $result = (new User)->updates($request->all(), $this->user,$id);
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
     * 删除用户
     * delete.api/Admin/user/{userId}
     */
    public function destroy($id){
        try{
            $result = (new User)->destroyUser($id);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101006');
            }
        } catch (Exception $ex) {
            throw new CommonException('101006');
        }
        
    }
   /*
    * 密码重置
    */
    public function rePwd(Request $request){
        try{
            if(!$request->has('userId')){
                throw new CommonException('101007');
            }
            $result = (new User)->rePwd($request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101008');
        }
        
    }
    /*
     * 激活/锁定用户
     */
    public function lockUser(Request $request){
        try{
            if(!$request->has('userId')){
                throw new CommonException('101007');
            }
            $result = (new User)->lockUser($request->all());
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101009');
            }
        } catch (Exception $ex) {
            throw new CommonException('101009');
        }
        
    }
    /*
     * 修改密码
     */
    public function updateUserPwd(Request $request){
        try{
            $rules = [
                'oldPwd'=>'required|min:6|max:16',
                'newPwd' => 'required|min:6|max:16'
            ];
            $validator = \Validator::make($request->all(),$rules,$this->messages,[
                                            'oldPwd'=>'原密码',
                                            'newPwd'=>'新密码',
                        ]);
            if($validator->fails()){
                return setFResult('100000', $validator->errors()->first());
            }
            $result = (new User)->updateUserPwd($request->all(), $this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101011');
            }
        } catch (Exception $ex) {
            throw new CommonException('101011');
        }
    }

    /*
     * 验证器
     */
    public function validateStr($input,$name,$id=0){
        if($name == "add"){
            $this->rules['userName'] = 'required|min:2|max:20|unique:sys_user,user_name,id';
            $this->rules['userPwd'] = 'required|min:6|max:16';
        }
        if(!empty($input['email'])){
            $this->rules['email'] = 'email';
        }
        $validator = \Validator::make($input,$this->rules,$this->messages,[
                'userName'=>'用户名',
                'userPwd'=>'密码',
                'mobile'=>'手机号码',
                'email'=>'邮箱',
                'realName'=>'真实姓名',
                'departId'=>'部门',
                'roleIds'=>'角色',
            ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        
        return 1;
    }
    

}
