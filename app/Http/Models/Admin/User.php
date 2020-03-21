<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Admin\Depart;
use App\Http\Models\Customer\Customer;
use App\Exceptions\CommonException;
use App\Http\Models\Finance\CustomerAccountModel;


class User extends BaseModel
{
    protected $table = 'sys_user';

    protected $isSeller;
    /*
     * 获取所有用户列表
     */
    public function getUsers($input){
        $whereIn = array();
        $where = array();
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        if(isset($input['search']) && !empty($input['search'])){
            $searchWhere = $this->searchWhere($input['search'], $where, $whereIn);
            $where = $searchWhere['where'];
            $whereIn = $searchWhere['whereIn'];
        }
        if(isset($input['isOwner']) && !empty($input['isOwner'])){//只查询网来员工用户
            $where[] = ['is_owner','=',1];
            $type = 1;
        }elseif(isset($input['customerId']) && !empty($input['customerId'])){//只查询某客户下的用户
            $where = $this->customerIdWhere($where, $input['customerId']);
            $type = 2;
        }elseif(isset($input['roleId']) && !empty($input['roleId'])){//添加用户到角色时用户列表
            $where = $this->roleIdWhere($where, $input['roleId']);
            $type = 3;
        }else{//所有用户
            $type = 4;
        }
        $where[] = ['is_delete','=',0];
        $data = $this->getAllUserInfo($where,$input['page'],$input['pageSize'],$type,$whereIn);
        return $data;
    }
    /*
     * 获取所有用户详细信息
     */
    public function getAllUserInfo($where,$page,$pageSize,$type,$whereIn){
        $offset = ($page-1) * $pageSize;
        $sqlObject = $this->where($where);
        if(!empty($whereIn)){
            $sqlObject = $sqlObject->whereIn('depart_id',$whereIn);
        }
        $count = $sqlObject->count('id');//总条数
        $userData = $sqlObject->offset($offset)->orderBy('created_at', 'DESC')->limit($pageSize)
                ->get(['id','user_name','real_name','depart_id','mobile','is_lock']);
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($userData->isEmpty()){
            $result['data']=[];
            return $result;
        }
        foreach ($userData as $key=>$value){
            $roles = $this->getRoleNames($value->id);
            if($type == 1){//用户列表
                $depart_name = $this->getDepartName($value->depart_id);
                $result['data'][$key]['departId'] = $value->depart_id;
                $result['data'][$key]['departName'] = $depart_name;
                $result['data'][$key]['mobile'] = $value->mobile;
            }else{//某个客户下的用户列表
                if($value->is_lock == 0){
                    $result['data'][$key]['isLock'] = "激活";
                }else{
                    $result['data'][$key]['isLock'] = "锁定";
                }
            }
            
            $result['data'][$key]['userId'] = $value->id;
            $result['data'][$key]['userName'] = $value->user_name;
            $result['data'][$key]['realName'] = $value->real_name;
            $result['data'][$key]['roles'] = $roles;
        }
        return $result; 
    }
    public function searchWhere($input,$where,$whereIn){
        $search = json_decode($input,TRUE);
        $where = self::getWhere($search);
        if(isset($search['departId']) && !empty($search['departId'])){
            $childDepart = Depart::where('parent_id',$search['departId'])->pluck('id');
            if($childDepart->isEmpty()){
                $where[] = ['depart_id','=',$search['departId']];
            }else{
                $childDepart['id'] = $search['departId'];
                $whereIn = $childDepart;
            }
        }
        $result = array();
        $result['where'] = $where;
        $result['whereIn'] = $whereIn;
        return $result;
    }
    public function customerIdWhere($where,$cutomerId){
        $customerData = Customer::where('id',$cutomerId)->first(['id']);
        if(empty($customerData)){
            throw new CommonException('102003');//客户不存在
        }
        $where[] = ['customer_id','=',$cutomerId];
        $where[] = ['is_owner','=',0];
        return $where;
    }
    /* 添加用户到角色-》用户列表
    *  网来的角色显示网来的用户
    *  客户的角色显示客户用户
    */
    public function roleIdWhere($where,$roleId){
        $roleData = Role::where('id',$roleId)->first(['role_type']);
        if(empty($roleData)){
            throw new CommonException('101102');//此角色不存在
        }
        if($roleData->role_type == 0){ //网来的角色
            $where[] = ['is_owner','=',1];
        }else{//客户角色
            $where[] = ['is_owner','=',0];
        }
        return $where;
    }
    /*
     * 添加用户
     */
    public function add($data,$loginUser,$type=1){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        
        $customerCode = "";
        if($type == 2){ //添加客户下用户
            if(!isset($data['customerId']) || empty($data['customerId'])){
                throw new CommonException('101002');
            }
            $customerCode = Customer::where('id',$data['customerId'])->first(['customer_code','level']);
            $userName = $data['userName']."@".$customerCode->customer_code;
            $userNameData = $this->getUserName($userName);
            if(!empty($userNameData)){
                throw new CommonException('101003');
            }
        }
        if(!isset($data['email']) && empty($data['email'])){
            $data['email']= null;
        }
        DB::beginTransaction();
        $res = $this->addUser($data, $loginUser, $type,$customerCode);
        if($res){
            DB::commit();
        }else{
            DB::rollBack();
        }
        return $res;
    }
    public function addUser($data,$loginUser,$type,$customerCode){
        $userModel = new User();
        $userId = getUuid();
        $userModel->id = $userId;
        $userModel->real_name = $data['realName'];
        $userModel->user_pwd = md5($data['userPwd'].config("info.SALT"));
        $userModel->mobile = $data['mobile'];
        $userModel->email = $data['email'];
        $userModel->create_user = $loginUser['id'];
        if($type == 1){//添加普通用户
            $userModel->user_name =$data['userName'];
            $userModel->depart_id = $data['departId'];
            $userModel->is_owner = 1;
            $resUser = $userModel->save();
            $roleData = array();
            $temp = array();
            foreach($data['roleIds'] as $v){
                $temp['id'] = getUuid();
                $temp['role_id'] = $v;
                $temp['user_id'] = $userId;
                $temp['created_at'] = date("Y-m-d H:i:s");
                $temp['create_user_id'] = $loginUser['id'];
                $temp['create_user_name'] = $loginUser['user_name'];
                $roleData[] = $temp;
            }
            $resRole = RoleUser::insert($roleData);
            if($resUser == TRUE && $resRole == TRUE){
                return TRUE;
            }
            return FALSE;
        }else{//添加客户下用户
            $customerRole = $this->getRoleByCustomer($data['customerId']);
            $userModel->user_name = $data['userName']."@".$customerCode->customer_code;
            $userModel->customer_id = $data['customerId'];
            $userModel->is_owner = 0;
            $resUser = $userModel->save();
            if(empty($customerRole)){
                return $resUser;
            }else{
                $roleUserModel = new RoleUser();
                $roleUserModel->id = getUuid();
                //$roleLevel = config("info.role_level");
                //$level = $customerCode->level;
                $roleUserModel->role_id = $customerRole;
                $roleUserModel->user_id = $userId;
                $roleUserModel->create_user_id = $loginUser['id'];
                $roleUserModel->create_user_name = $loginUser['user_name'];
                $resRole = $roleUserModel->save();
                if($resUser == TRUE  && $resRole == TRUE){
                    return TRUE;
                }
            }
            return FALSE;
        }
    }
    /*
     * 根据客户ID获取客户用户的角色
     */
    public function getRoleByCustomer($customerId){
        $userData = $this->where(['customer_id'=>$customerId,'is_delete'=>0])->first(['id']);
        if(empty($userData)){
            return "";
        }
        $roleData = $this->getRoleNames($userData->id);
        if(empty($roleData)){
            return "";
        }
        return $roleData[0]['id'];
    }
    /*
     * 编辑用户
     */
    public function updates($input,$loginUser,$userId,$type=1){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $data = array();
        $roleIds = [];
        if($type == 1){
            $data['depart_id']= $input['departId'];
            $roleIds = $input['roleIds'];
        }
        $data['real_name']= $input['realName'];
        $data['mobile']= $input['mobile'];
        if(isset($input['email']) && !empty($input['email'])){
            $data['email']= $input['email'];
        }
        //查询之前的角色信息
        DB::beginTransaction();
        $res = $this->updateUser($data,$loginUser,$userId,$type,$roleIds);
        if($res){
            DB::commit();
        }else{
            DB::rollBack();
        } 
        return $res;
    }
    public function updateUser($data,$loginUser,$userId,$type,$roleIds){
        if($type == 1){//网来员工
            
            $userRes = $this->where('id','=',$userId)->update($data);
            $roleRes = 1;
            $userRoles = RoleUser::where('user_id',$userId)->first(['id']);
            if(!empty($userRoles)){
                //删除之前的角色信息
                $roleRes = RoleUser::where('user_id',$userId)->delete();
            }
            $roleData = array();
            $temp = array();
            foreach($roleIds as $v){
                $temp['id'] = getUuid();
                $temp['role_id'] = $v;
                $temp['user_id'] = $userId;
                $temp['created_at'] = date("Y-m-d H:i:s");
                $temp['create_user_id'] = $loginUser['id'];
                $temp['create_user_name'] = $loginUser['user_name'];
                $roleData[]= $temp;
            }
            $resRole = RoleUser::insert($roleData);
            if($userRes >0  && $roleRes > 0 && $resRole == TRUE){
                return TRUE;
            }
            return FALSE;
        }else{//客户用户
            $userRes = $this->where('id','=',$userId)->update($data);
            if($userRes > 0 ){
                return TRUE;
            }
            return FALSE;
        }
    }
    /*
     * 密码重置
     */
    public function rePwd($input){
        $userData = $this->getAdminInfo($input['userId']);
        $userPwd = random_str();
        $data['user_pwd'] = md5($userPwd.config("info.SALT"));
        $res = $this->where('id','=',$input['userId'])->update($data);
        $result = array();
        $result['pwd'] = $userPwd;
        if($res>0){
            return $result;
        }else{
            throw new CommonException('101008');
        }
        
    }
    /*
     * 删除用户
     */
    public function destroyUser($userId){
        //查看是否是网来的员工
        $userData = $this->where(['id'=>$userId,'is_delete'=>0])->first(['is_owner','user_name']);
        if(empty($userData)){
            throw new CommonException('101007');//此用户不存在！
        }
        if (preg_match('/^admin@\w+$/', $userData->user_name)) {
            throw new CommonException('101013');//此用户你无权删除！
        }
        DB::beginTransaction();
        if($userData->is_owner == 1){//是网来的员工
            $res = $this->where('id',$userId)->delete();
        }else{//客户的用户
            $res = $this->where('id',$userId)->update(['is_delete'=>1,'deleted_at'=>date('Y-m-d H:i:s',time())]);
        }
        $resRoleUserBool = TRUE;
        $roleUserCount = RoleUser::where('user_id',$userId)->count('id');
        if($roleUserCount > 0){
            $resRoleUser = RoleUser::where('user_id',$userId)->delete();
            if($roleUserCount != $resRoleUser){
                return FALSE;
            }
        }
        if($res == 1 && $resRoleUserBool == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
        
    }
    /*
     * 激活锁定用户
     */
    public function lockUser($input){
        $userData = $this->getAdminInfo($input['userId']);
        $res = $this->where('id','=',$input['userId'])->update(['is_lock'=>(int)$input['status']]);
        return $res;
    }
    
    /*
     * 获取某个用户详细信息
     */
    public function getUserInfo($userId){
        $data = array();
        $userData = $this->where('id','=',$userId)
                ->first(['id','user_name','real_name','depart_id','mobile','email','is_owner']);
        if(empty($userData)){
            throw new CommonException('101007');
        }
        $customerBalance = '0';
        if($userData->is_owner == 0){//客户
            
            $customerData = User::where(['id'=>$userId])->first(['customer_id']);
            $customerBalanceData = CustomerAccountModel::where('id',$customerData->customer_id)->first(['balance_amount']);
            if(!empty($customerBalanceData)){
                $customerBalance = $customerBalanceData->balance_amount;
            }
        }
        $roleName = $this->getRoleNames($userId);
        $departName = $this->getDepartName($userData->depart_id);
        $data['id'] = $userId;
        $data['user_name'] = $userData->user_name;
        $data['real_name'] = $userData->real_name;
        $data['depart_name'] = $departName;
        $data['role_name'] = $roleName;
        $data['mobile'] = $userData->mobile;
        $data['email'] = $userData->email;
        $data['balance_amount'] = $customerBalance;
        return $data;
    }
    /*
     * 根据用户ID获取用户信息
     */
    public function getAdminInfo($id){
        $data = $this->where(['id'=>$id,'is_delete'=>0])
                ->first(['id','user_name','user_pwd','real_name','depart_id','mobile','email','is_lock']);
        if(empty($data)){
            throw new CommonException('101007');
        }
        return $data;
    }
    /*
     * 用户名查重
     */
    public function getUserName($name){
        $data = $this->where('is_delete',0)->where('user_name',$name)
                ->first(['id']);
        return $data;
    }
    /*
    * 根据用户ID获取多角色名称
    */
    public function getRoleNames($userId){
        $roles = array();
        $roleUser = RoleUser::where('user_id','=',$userId)->with([
                    'getRoles' => function ($query) {
                        $query->select(['id','role_name']);
                        },
                ])
                ->get(['id','role_id','user_id'])->toArray();
        
        foreach($roleUser as $key=>$value){
            $roles[$key]['id'] = $value['get_roles']['id'];
            $roles[$key]['roleName'] = $value['get_roles']['role_name'];
        }
        return $roles;
    }
    /*
     * 根据部门ID获取部门名称
     */
    public function getDepartName($id){
        $departUser = Depart::where('id','=',$id)->first(['depart_name']);
        if($departUser){
            return $departUser->depart_name;
        }else{
            return null;
        }
        
    }
    /*
     * 修改用户密码
     */
    public function updateUserPwd($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $userData = $this->getAdminInfo($loginUser['id']);
        $oldPwd = md5($input['oldPwd'].config("info.SALT"));
        if($userData->user_pwd != $oldPwd){
            throw new CommonException('101012');//原密码输入错误
        }
        $data['user_pwd'] = md5($input['newPwd'].config("info.SALT"));
        $res = $this->where('id','=',$loginUser['id'])->update($data);
        return $res;
    }
    /*
     * 获取where条件
     */
    public static function getWhere($input){
        $where = array();
            if(isset($input['userName']) && !empty($input['userName'])){
                $userNameWhere = ['user_name', 'like', "%".$input['userName'].'%'];
                $where[]=$userNameWhere;
            }
            if(isset($input['realName']) && !empty($input['realName'])){
                $realNameWhere = ['real_name', 'like', "%".$input['realName'].'%'];
                $where[]=$realNameWhere;
            }
            if(isset($input['mobile']) && !empty($input['mobile'])){
                $mobileWhere = ['mobile', 'like', "%".$input['mobile'].'%'];
                $where[]=$mobileWhere;
            }
            /*if(isset($input['departId']) && !empty($input['departId'])){
                //$departIdWhere = ['depart_id', '=', $input['departId']];
                //$where[]=$departIdWhere;
                $childDepart = Depart::where('parent_id',$input['departId'])->get(['id'])->toArray();
                print_r($childDepart);exit;
                if(empty($childDepart)){
                    $where[] = ['depart_id', '=', $input['departId']];
                }else{
                    
                }
                $data = $this->getChildDepartId($input['departId']);
            }*/
            if(isset($input['gatewayName']) && !empty($input['gatewayName'])){
                $gatewayNameWhere = ['gateway_name', 'like', '%'.$input['gatewayName'].'%'];
                $where[]=$gatewayNameWhere;
            }
            if(isset($input['isUse'])){
                if(empty($input['isUse'])){
                    if($input['isUse'] == "0"){
                        $isUseWhere = ['is_use', '=', $input['isUse']];
                        
                        $where[]=$isUseWhere;
                    }
                }else{
                    $isUseWhere = ['is_use', '=', $input['isUse']];
                    $where[]=$isUseWhere;
                }
            }
            if(isset($input['stationName']) && !empty($input['stationName'])){
                $stationNameWhere = ['station_name', 'like',"%".$input['stationName'].'%'];
                $where[]=$stationNameWhere;
            }
            if(isset($input['stationCode']) && !empty($input['stationCode'])){
                $stationCodeWhere = ['station_code', 'like', "%".$input['stationCode']."%"];
                $where[]=$stationCodeWhere;
            }
            if(isset($input['operatorType']) && !empty($input['operatorType'])){
                $operatorTypeWhere = ['operator_type', 'like', '%'.$input['operatorType'].'%'];
                $where[]=$operatorTypeWhere;
            }
            
        return $where;
    }
    
    /**
     * 获取用户权限
     * @param [type] $user
     * @return void
     */
    public function getUserAuth($user)
    {   
        if($user->id === '110administrators110'){
            //超级管理员
            $menuAuth = Menu::orderBy('sort')->get(['id','menu_name','menu_url','parent_id','menu_type','remark','sort','front_url','menu_icon'])->toArray();
            $auth = getTree($menuAuth);
            return backTree($auth);
        }else{
            // 角色用户
            $roles = RoleUser::where('user_id',$user->id)->get(['role_id']);
            $roleAuth = [];
            $menuAuth = [];
            if(!$roles->isEmpty()){
                foreach($roles as $val){
                    $roleAuth[] = $val['role_id'];
                }
            }
            if(!empty($roleAuth)){
                $menus = RoleMenu::whereIn('role_id',$roleAuth)->get(['menu_id']);
                if(!empty($menus)){
                    foreach($menus as $va){
                        $menuAuth[] = $va['menu_id'];
                    }
                    $auths = Menu::whereIn('id',$menuAuth)->orderBy('sort')->get(['id','menu_name','menu_url','parent_id','menu_type','remark','sort','front_url','menu_icon'])->toArray();
                    $auth = getTree($auths);
                    return backTree($auth);
                }else{
                    return '请联系管理员授权！';
                }
            }else{
                return '请联系管理员授权！';
            }
        }
    }
    
    
    
    
}
