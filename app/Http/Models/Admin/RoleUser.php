<?php

namespace App\Http\Models\Admin;
use Illuminate\Support\Facades\DB;
use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;

class RoleUser extends BaseModel
{
    protected $table = 'sys_role_user';
    //与角色表关联（关联模型，子表主键ID,主表外键ID）
    public function getRoles(){
        return $this->hasOne('App\Http\Models\Admin\Role','id','role_id');
    }
    //与用户表关联
    public function getUsers(){
        return $this->hasOne('App\Http\Models\Admin\User','id','user_id');
    }
    /*
     * 从指定角色中删除用户
     */
    public function delUser($id){
        $res = $this->where('id','=',$id)->delete();
        return $res;
    }
    /*
     * 从角色中添加用户
     */
    public function addUser($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('101001');
        }
        DB::beginTransaction();
            $res = TRUE;
            $roleData = array();
            $temp = array();
            foreach($input['userId'] as $key=>$value){
                $userRoleData = $this->where([['role_id','=',$input['roleId']],['user_id','=',$value]])->first(['id']);
                if(!empty($userRoleData)){
                    continue;
                }
                $id = getUuid();
                $temp['id'] = $id;
                $temp['role_id'] = $input['roleId'];
                $temp['user_id'] = $value;
                $temp['create_user_id'] = $loginUser['id'];
                $temp['create_user_name'] = $loginUser['user_name'];
                $temp['created_at'] = date("Y-m-d H:i:s");
                $roleData[] = $temp;
            }
            $res = RoleUser::insert($roleData);
            if($res){
                DB::commit();
            }else{
                DB::rollBack();
            }
            return $res;
    }
    
    
    /**
     * 通过用户id查询角色id
     */
    function getRoleIdByUser(string $userId) {
        $roles = $this->where('user_id', $userId)->pluck('role_id');
        if ($roles->isEmpty()) {
            throw new CommonException(Errors::USER_ROLE_NOTFOUND);
        }
        
        return $roles->toArray();
    }
    /*
     * 通过用户与角色查找信息
     */
    public static function getUserRoleData($userId,$roleId){
        $result = TRUE;
        $data = RoleUser::where(['user_id'=>$userId,'role_id'=>$roleId])->first(['id']);
        if(empty($data)){
            $result = FALSE;
        }
        return $result;
    }
    
}
