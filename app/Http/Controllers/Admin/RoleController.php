<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Models\Admin\Role;
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\RoleUser;
use App\Exceptions\CommonException;

class RoleController extends Controller
{
    protected $rules = [
            'roleType'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'roleName.unique'=>'该角色名已经被注册',
            'min'=>':attribute 长度不符合要求',
            'max'=>':attribute 长度不符合要求',
        ];
    /*
     * 获得角色列表
     */
    public function getRoles(Request $request){
        try{
            $result = (new Role)->getRoles($request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /* 
     * 将新创建的角色存储到存储器
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $result = (new Role)->addRole($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101101');
            }     
        } catch (Exception $ex) {
            throw new CommonException('101101');
        }
        
    }
    /**
     * 显示指定角色
     */
    public function show($id){
        try{
            $result = (new Role)->getRoleName($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * 显示指定角色下的用户列表
     */
    public function showUser(Request $request, $id){
        try{
            if(empty($id)){
                throw new CommonException('101102');
            }
            $result = (new Role)->getRoleUser($request->all(),$id);
            return $this->success($result); 
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * 在存储器中更新指定角色
     */   
    public function update(Request $request, $id){
        try{
            if(empty($id)){
                throw new CommonException('101102');
            }
            $validate = $this->validateStr($request->all(),'edit',$id);
            if($validate != 1){
                return $validate;
            }
            $result = (new Role)->updateRole($request->all(),$id,$this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101103');
            }
        } catch (Exception $ex) {
            throw new CommonException('101103');
        }
        
    }
    /**
     * 从存储器中移除指定角色
     */
    public function destroy($id){
        try{
            $result = (new Role)->destroyRole($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101105');
            }
        } catch (Exception $ex) {
            throw new CommonException('101105');
        }
        
    }
    /**
     * 从指定角色中删除用户
     */
    public function delUser($id){
        try{
            if(empty($id)){
                throw new CommonException('101102');
            }
            $result = (new RoleUser)->delUser($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101006');
            }
        } catch (Exception $ex) {
            throw new CommonException('101006');
        }
        
    }
    /*
     * 验证器
     */
    public function validateStr($input,$name,$id=0){
        if($name == "add"){
            $this->rules['roleName'] = 'required|min:2|max:20|unique:sys_role,role_name,id';  
        }else{
            $this->rules['roleName'] = 'required|min:2|max:20|unique:sys_role,role_name,'.$id;
        }
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            'roleName'=>'角色名称',
            'roleType'=>'角色类型'
        ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }

    //get.api/Admin/getMenuAuth 权限查看
    public function getMenuAuth(Request $request)
    {  
        if(!$request->has('id')){
            throw new CommonException('300002');
        }
        if(empty($request->get('id'))){
            throw new CommonException('300003');
        }
        $role = new Role();
        $data = $role->getMenuAuth($request->get('id'));
        if($data[0]){
            return setTResult($data[1]);
        }else{
            throw new CommonException('101107');
        }
    }
    
    //post.api/Admin/getMenuAuth 菜单树勾选录入角色权限
    public function setMenuAuth(Request $request)
    {
        if(!$request->has('roleId') || !$request->has('menuAuth')){
            throw new CommonException('300002');
        }
        if(empty($request->post('roleId'))){
            throw new CommonException('101108');
        }
        $role = new Role();
        $bool = $role->setMenuAuth($request,$this->user);
        if($bool){
            return setFResult('0', '设置权限成功！');
        }else{
            throw new CommonException('101109');
        }
    } 
        
}
