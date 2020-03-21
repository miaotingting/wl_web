<?php

namespace App\Http\Models\Admin;

use App\Http\Models\Admin\User;
use App\Http\Models\BaseModel;
use App\Http\Models\Admin\RoleUser;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Operation\Package;
use App\Http\Models\Matter\NodeModel;
use App\Http\Models\Admin\TypeDetailModel;


class Role extends BaseModel
{
    protected $table = 'sys_role';
    /*
     * 获取角色列表
     */
    public function getRoles($input){
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $where = array();
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            if(isset($search['roleName']) && !empty($search['roleName'])){
                $where[] = ['role_name', 'like', '%'.$search['roleName'].'%'];
            }
        }
        if(isset($input['roleType']) && !empty($input['roleType'])){
            $where[] = ['role_type','=',$input['roleType']];
        }
        $result = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $result;
    }
    /*
     * 添加角色
     */
    public function addRole($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('101001');
        }
        $roleId = getUuid();
        $roleModel = new Role();
        $roleModel->id = $roleId;
        $roleModel->role_name = $input['roleName'];
        $roleModel->create_user_id = $loginUser['id'];
        $roleModel->create_user_name = $loginUser['user_name'];
        $roleModel->role_type = $input['roleType'];//网来的角色,客户角色
        $resRole = $roleModel->save();
        return $resRole;
    }
    /*
     * 编辑角色
     */
    public function updateRole($input,$id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('101001');
        }
        $updateData['role_name'] = $input['roleName'];
        $updateData['role_type'] = $input['roleType'];
        $updateData['modify_user_id'] = $loginUser['id'];
        $updateData['modify_user_name'] = $loginUser['user_name'];
        $res = $this->where('id',$id)->update($updateData);
        return $res;
    }
    /*
     * 获取该角色下的用户列表
     */
    public function getRoleUser($input,$roleId){
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $where = ' ru.role_id = "'.$roleId.'"';
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            if(isset($search['userName']) && !empty($search['userName'])){
                $where = $where.' and u.user_name like "%'.$search['userName'].'%" ';
            }
            if(isset($search['realName']) && !empty($search['realName'])){
                $where = $where.' and u.real_name like "%'.$search['realName'].'%" ';
            }
        }
        
        $data = $this->getListByWhere($where, $input['page'], $input['pageSize']);
        return $data;
    }
    /*
     * 删除角色
     */
    public function destroyRole($roleId){
        //查询该角色下的用户数量
        $countRoleUsers = RoleUser::where('role_id','=',$roleId)->count();
        if($countRoleUsers > 0){
            throw new CommonException('101104');
        }
        $data = $this->find($roleId);
        if(empty($data)){
            throw new CommonException('101102');
        }
        $execRoleIdData = NodeModel::where('exec_role_id',$roleId)->first(['node_id']);
        if(!empty($execRoleIdData)){
            throw new CommonException('101110');//t_wf_node表有此角色，不能删除
        }
        $res = $this->where('id','=',$roleId)->delete();
        return $res;
    }
    
    /*
     * 通过条件查询角色列表
     */
    public function getListByWhere($where,$page,$pageSize){
        $start = ($page-1) * $pageSize;
        $countSql = "select count(ru.id) as num from t_sys_role_user as ru ".
        "LEFT JOIN t_sys_user as u on ru.user_id = u.id where ".$where." ";
        $sql = "select ru.id,ru.role_id,ru.user_id,u.user_name,u.real_name from t_sys_role_user as ru ".
        "LEFT JOIN t_sys_user as u on ru.user_id = u.id where ".$where." ".
        "order by ru.created_at desc limit ".$start.",".$pageSize;
        $countData = DB::select($countSql);
        /*if($countData <= 0){
            return "";
        }*/
        $roleUser = DB::select($sql);
        $count = $countData[0]->num;
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        
        $result = array();
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['data'] = $roleUser;
        return $result;
    }
    /*
     * 获取所有角色详细信息
     */
    public function getPageData($where,$page,$pageSize){
        $result = array();
        $offset = ($page-1)*$pageSize;
        $sqlObject = $this;
        //print_r($where);exit;
        if(!empty($where)){
            $sqlObject = $sqlObject->where($where);
        }
        
        
        $count = $sqlObject->count('id');//总条数
        $data = $sqlObject->orderBy('created_at', 'desc')
                    ->offset($offset)->limit($pageSize)
                    ->get(['id','role_name','role_type']);
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = (int)$pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
            return $result;
        }
        $roleTypeGroup = TypeDetailModel::getDetailsByCode('role_type');
        foreach($data as $value){
            $value->role_type = $roleTypeGroup[$value['role_type']];
        }
        $result['data'] = $data;
        return $result; 
    }
    /*
     * 获取某个角色名称 
     */
    public function getRoleName($id){
        $data = $this->where('id',$id)
                ->first(['id','role_name']);
        if(empty($data)){
            throw new CommonException('101102');
        }
        return $data;
    }
    /*
     * 角色名称的查重
     */
    public function getRoleId($name,$id){
        $data = $this->where([['id','<>',$id],['role_name','=',$name]])
                ->first(['id']);
        return $data;
    }

    /**
     * 设置权限
     * @param [type] $request
     * @return void
     */
    public function setMenuAuth($request, $user)
    {
        try{
            $menu = self::find($request->post('roleId'));
            if(!empty($menu)){
                DB::beginTransaction();
                $roleMenu = RoleMenu::where('role_id',$menu->id)->get(['role_menu_id'])->toArray();
                if(count($roleMenu) > 0){
                    RoleMenu::where('role_id',$menu->id)->delete(); 
                }
                $authArr = $request->post('menuAuth');
                $data = array();
                $temp = array();
                $roleMenu = new RoleMenu();
                foreach($authArr as $v){
                    $temp['role_menu_id'] = getUuid();
                    $temp['role_id'] = $menu->id;
                    $temp['menu_id'] = $v;
                    $temp['create_user_id'] = $user['id'];
                    $temp['create_user_name'] =$user['real_name'];
                    $temp['created_at'] = date('Y-m-d H:i:s');
                    $temp['updated_at'] = date('Y-m-d H:i:s');
                    $data[] = $temp;
                }
                RoleMenu::insert($data);
                DB::commit();
                return true;
            }else{
                return false;
            }
        }catch(Exception $e){
            DB::rollBack();
            return false;
        }
    }

    /**
     * 返回指定角色菜单权限
     * @param [type] $id
     * @return void
     */
    public function getMenuAuth($role_id)
    {
        $role = self::find($role_id);
        if(!empty($role)){
            $data = [];
            $roleMenu = RoleMenu::where('role_id', $role_id)->get(['menu_id'])->toArray();
            foreach($roleMenu as $val){
                $data[] = $val['menu_id'];
            }
            //处理掉parentId=0的菜单，不用返回
            //  $menuData = Menu::where('parent_id','<>','0')->whereIn('id',$data)->get(['id','parent_id'])->toArray();
            $menuData = Menu::whereIn('id',$data)->get(['id','parent_id'])->toArray();
            $menuData = getTree($menuData);
            $menuData = backTree($menuData);
            $newData = [];
            foreach($menuData as $children){
                if (array_has($children, 'children')) {
                    foreach($children['children'] as $child){
                        if (array_has($child, 'children')) {
                            foreach($child['children'] as $ch){
                                $newData[] = $ch['id'];
                            } 
                        }else{
                            $newData[] = $child['id'];
                        }
                    }
                }else{
                    $newData[] = $children['id'];
                }
            }
            return [true, $newData];
        }else{
            throw new CommonException('101106');
        }
    }


    
}
