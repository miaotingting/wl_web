<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\NoticeUserModel;
use App\Http\Models\Admin\NoticeRoleModel;
use App\Http\Models\Admin\Role;
use App\Http\Models\Admin\User;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Admin\TypeDetailModel;

class NoticeModel extends BaseModel
{
    protected $table = 'sys_notice';
    protected $page = 1;
    protected $pageSize = 10;
    /*
     * 获取消息列表
     */
    public function getNotice($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = $this->page;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = $this->pageSize;
        }
        
        $where[] = ['is_delete','=',0];
        //print_r($where);exit;
        $data = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $data;
    }
    
    /*
     * 获取所有分页数据
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $count = $this->where($where)->count('id');//总条数
        $data = $this->where($where)->offset($offset)
                ->orderBy('created_at','DESC')->limit($pageSize)
                ->get(['id','title','type','level','notice_term','created_at']);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
            return $result;
        }
        
        foreach($data as $value){
            $noticeTypeGroup = TypeDetailModel::getDetailsByCode('notice_type');
            $value->type = $noticeTypeGroup[$value->type];
            $noticeLevelGroup = TypeDetailModel::getDetailsByCode('notice_level');
            $value->level = $noticeLevelGroup[$value->level];
        }
        $result['data'] = $data;
        return $result;
    }
    /*
     * 获取where条件
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['title']) && !empty($input['title'])){
            $where[] = ['title', 'like', "%".$input['title'].'%'];
        }
        return $where;
    }
    /*
     * 新增
     */
    public function addNotice($input,$loginUser,$uuid=null){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $noticeModel = new NoticeModel();
        if(empty($uuid)){
            $noticeModel->id = getUuid();
        }else{
            $noticeModel->id = $uuid;
        }
        $noticeModel->title = $input['title'];
        $noticeModel->content = $input['content'];
        $noticeModel->type = $input['type'];
        $noticeModel->level = $input['level'];
        //$noticeModel->notice_term = date('Y-m-d H:i:s',strtotime($input['noticeTerm']));
        if(isset($input['noticeTerm']) && !empty($input['noticeTerm'])){
            $noticeTerm = substr($input['noticeTerm'],0,10);
            $noticeModel->notice_term = $noticeTerm.' 23:59:59';
        }
        $noticeModel->create_user_id = $loginUser['id'];
        $noticeModel->create_user_name = $loginUser['real_name'];
        $res = $noticeModel->save();
        return $res;
    }
    /*
     * 编辑
     */
    public function updateNotice($input,$id){
        $noticeData = $this->getNoticeById($id);
        $updateData['title'] = $input['title'];
        $updateData['content'] = $input['content'];
        $updateData['type'] = (int)$input['type'];
        $updateData['level'] = (int)$input['level'];
        $updateData['notice_term'] = $input['noticeTerm'];
        DB::beginTransaction();
        $resNotice = $this->where('id',$id)->update($updateData);
        if($updateData['level'] != $noticeData->level){
            $resRoleOrUser = $this->updateNoticeRoleOrUser($id,$noticeData->level);
        }else{
            $resRoleOrUser = TRUE;
        }
        if($resNotice > 0 && $resRoleOrUser == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
        
    }
    /*
     * 编辑公告时，如果改变了公告级别
     * 则把原来角色与公告的关联与用户与公告关联及已读表中与公告的关联全部删除
     * 
     */
    public function updateNoticeRoleOrUser($noticeId,$level){
        if($level == 1){
            $noticeRoleCount = NoticeRoleModel::where('notice_id',$noticeId)->count('id');
            if($noticeRoleCount == 0){
                return TRUE;
            }
            $resNoticeRole = NoticeRoleModel::where('notice_id',$noticeId)->delete();
            if($resNoticeRole != $noticeRoleCount){
                return FALSE;
            }
        }elseif($level == 2){
            $noticeUserCount = NoticeUserModel::where('notice_id',$noticeId)->count('id');
            if($noticeUserCount == 0){
                return TRUE;
            }
            $resNoticeUser = NoticeUserModel::where('notice_id',$noticeId)->delete();
            if($resNoticeUser != $noticeUserCount){
                return FALSE;
            }
        }
        $readUserCount = NoticeReadUserModel::where('notice_id',$noticeId)->count('id');
        if($readUserCount == 0){
            return TRUE;
        }
        $resReadUser = NoticeReadUserModel::where('notice_id',$noticeId)->delete();
        if($resReadUser != $readUserCount){
            return FALSE;
        }
        return TRUE;
    }
    /*
     * 删除 
     */
    public function destroyNotice($id){
        $noticeData = $this->getNoticeById($id);
        $resNotice = $this->where('id',$id)->update(['is_delete'=>1]);
        return $resNotice;
    }
    /*
     * 授权
     */
    public function impowerNotice($input){
        $noticeData = $this->getNoticeById($input['noticeId']);
        if($noticeData->level == 1){//角色授权
            $res = $this->impowerRole($input['noticeId'],$input['paramId']);
        }elseif($noticeData->level == 2){//用户授权
            $res = $this->impowerUser($input['noticeId'],$input['paramId']);
        }else{ 
            throw new CommonException('101405');//全员级别没有授权权限
        }
        return $res;
    }
    /*
     * 角色授权
     */
    public function impowerRole($noticeId,$paramId){
        
        $idArr = explode(',',trim($paramId,','));
        $noticeRoleData = array();
        foreach($idArr as $value){
            $roleData = Role::where('id',$value)->first(['id']);
            if(empty($roleData)){
                throw new CommonException('101407');//此角色不存在
            }
            $temp['id'] = getUuid();
            $temp['role_id'] = $value;
            $temp['notice_id'] = $noticeId;
            $noticeRoleData[] = $temp;
        }
        $res = NoticeRoleModel::insert($noticeRoleData);
        return $res;
    }
    /*
     * 用户授权
     */
    public function impowerUser($noticeId,$paramId){
        $userData = User::where(['id'=>$paramId,'is_delete'=>0])->first(['id']);
        if(empty($userData)){
            throw new CommonException('101408');//此用户不存在
        }
        $idArr = explode(',',trim($paramId,','));
        $noticeUserData = array();
        foreach($idArr as $value){
            $temp['id'] = getUuid();
            $temp['user_id'] = $value;
            $temp['notice_id'] = $noticeId;
            $noticeUserData[] = $temp;
        }
        $res = NoticeUserModel::insert($noticeUserData);
        return $res;
    }
    /*
     * 删除权限
     */
    public function deleteImpower($input){
        $noticeData = $this->getNoticeById($input['noticeId']);
        if($noticeData->level == 1){//角色授权
            $res = $this->deleteNoticeRole($input['paramId'],$input['noticeId']);
        }elseif($noticeData->level == 2){//用户授权
            $res = $this->deleteNoticeUser($input['paramId'],$input['noticeId']);
        }else{ 
            throw new CommonException('101410');//全员级别没有删除授权权限
        }
        return $res;
    }
    /*
     * 用户授权类型删除授权
     * 删除此条授权信息并查找已读公告表信息删除
     */
    public function deleteNoticeUser($noticeUserId,$noticeId){
        $noticeUserData = NoticeUserModel::where('id',$noticeUserId)->first(['user_id']);
        if(empty($noticeUserData)){
            throw new CommonException('101411');
        }
        DB::beginTransaction();
        $resNoticeUser = NoticeUserModel::where('id',$noticeUserId)->delete();
        $resReadUser = $this->deleteUserRead($noticeId,$noticeUserData->user_id);
        if($resNoticeUser >0 && $resReadUser>0){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
    }
    public function deleteUserRead($noticeId,$userId){
        $sqlObject = NoticeReadUserModel::where(['notice_id'=>$noticeId
                ,'user_id'=>$userId]);
        $noticeReadCount = $sqlObject->count('id');
        $resReadUser = 1;
        if($noticeReadCount >0){
            $resReadUser = $sqlObject->delete();
        }
        return $resReadUser;
    }
    /*
     * 角色授权类型删除授权
     * 删除此条授权信息并查找已读公告表中属于此角色的用户信息删除
     */
    public function deleteNoticeRole($noticeRoleId,$noticeId){
        $noticeRoleData = NoticeRoleModel::where('id',$noticeRoleId)->first(['role_id']);
        if(empty($noticeRoleData)){
            throw new CommonException('101411');
        }
        
        DB::beginTransaction();
        $resNoticeRole = NoticeRoleModel::where('id',$noticeRoleId)->delete();
        $resReadUser = $this->deleteRoleRead($noticeId, $noticeRoleData->role_id);
        if($resNoticeRole > 0 && $resReadUser == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
        
    }
    public function deleteRoleRead($noticeId,$roleId){
        $res = TRUE;
        $sqlObjectJoin = DB::table('sys_notice_read_user as r')
                ->leftJoin('sys_role_user as ru','ru.user_id','=','r.user_id')
                ->where(['r.notice_id'=>$noticeId,'ru.role_id'=>$roleId]);
        $count = $sqlObjectJoin ->count('r.id');
        if($count > 0){
            $resReadUserCount = $sqlObjectJoin->delete();
            if($resReadUserCount != $count){
                $res = FALSE;
            }
        }
        return $res;
    }
    /*
     * 获取指定ID公告信息
     */
    public function getNoticeById($id){
        $data = $this->where('id',$id)->first(['id','title','content','type','level','notice_term','created_at']);
        if(empty($data)){
            throw new CommonException('101404');//此条记录不存在
        }
        return $data;
    }
    /*
     * 获取指定ID详细信息
     */
    public function getNoticeInfo($id){
        $data = $this->getNoticeById($id);
        $noticeTypeGroup = TypeDetailModel::getDetailsByCode('notice_type');
        $data->type = $noticeTypeGroup[$data->type];
        $noticeLevelGroup = TypeDetailModel::getDetailsByCode('notice_level');
        $data->level = $noticeLevelGroup[$data->level];
        return $data;
    }
    /*
     * 授权列表
     */
    public function getImpowerList($input,$id){
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = $this->page;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = $this->pageSize;
        }
       
        $noticeData = $this->getNoticeById($id);
        $offset = ($input['page']-1) * $input['pageSize'];
        if($noticeData->level == 1){
            $str = ['nr.id','r.role_name'];
            $pKey = 'nr.id';
            $whereKey = 'nr.notice_id';
            $sqlObjectJoin = DB::table('sys_notice_role as nr')
                ->leftJoin('sys_role as r','nr.role_id','=','r.id');
        }elseif($noticeData->level == 2){
            $str = ['nu.id','u.user_name'];
            $pKey = 'nu.id';
            $whereKey = 'nu.notice_id';
            $sqlObjectJoin = DB::table('sys_notice_user as nu')
                ->leftJoin('sys_user as u','nu.user_id','=','u.id');
        }else{
            throw new CommonException('101405');//全员级别无法查看授权列表
        }
        $count = $sqlObjectJoin->where([$whereKey=>$id])->count($pKey);
        
        $data = $sqlObjectJoin->where([$whereKey=>$id])
                ->offset($offset)->limit($input['pageSize'])
                ->get($str);
        $result = array();
        $pageCount = ceil($count/$input['pageSize']); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$input['page'];
        $result['pageSize'] = $input['pageSize'];
        $result['pageCount'] = $pageCount;
        $result['data'] = $data;
        return $result;
    }
    /*
     * 登录用户获取系统公告列表
     */
    public function getMessage($input,$loginUser,$type){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = $this->page;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = $this->pageSize;
        }
        $userId = $loginUser['id'];
        $time = date("Y-m-d H:i:s",time());
        $roleArr = (new RoleUser)->getRoleIdByUser($userId);
        
        $sqlObject = DB::table('sys_notice as n')
                ->leftJoin('sys_notice_role as rn','rn.notice_id','=','n.id')
                ->leftJoin('sys_notice_user as un','un.notice_id','=','n.id')
                //->leftJoin('sys_notice_read_user as urn','urn.notice_id','=','n.id')
                ->where(['n.is_delete'=>0,'n.type'=>$type])
                ->where(function ($query) use ($time) {
                    $query->where('n.notice_term',null)
                        ->orWhere('n.notice_term','>=',$time);
                    })
                ->Where(function ($query) use ($userId,$roleArr) {
                    $query->whereIn('rn.role_id',$roleArr)
                        ->orWhere('un.user_id','=',$userId)
                        ->orWhere('n.level','=',0);
                    });
        $count = $sqlObject ->count('n.id');
        $offset = ($input['page']-1) * $input['pageSize'];
        $data = $sqlObject->offset($offset)
                ->orderBy('n.created_at','DESC')
                ->limit($input['pageSize'])
                ->get(['n.id','n.title','n.created_at']);
        $result = array();
        $pageCount = ceil($count/$input['pageSize']); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$input['page'];
        $result['pageSize'] = $input['pageSize'];
        $result['pageCount'] = $pageCount;
        if(empty($data)){
            $result['data'] = [];
            return $result;
        }
        foreach($data as $value){
            $isNoRead = NoticeReadUserModel::where(['notice_id'=>$value->id,'user_id'=>$userId])
                    ->first(['id']);
            $value->status = "已读";
            if(empty($isNoRead)){
                $value->status = "未读";
            }
        }
        $result['data'] = $data;
        //print_r($result);exit;
        return $result;
    }
    /*
     * 阅读
     */
    public function readNotice($id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $noticeData = $this->getNoticeById($id);
        $readInfo = NoticeReadUserModel::where(['notice_id'=>$id,'user_id'=>$loginUser['id']])->first(['id']);
        if(empty($readInfo)){
            $data['id'] = getUuid();
            $data['user_id'] = $loginUser['id'];
            $data['notice_id'] = $id;
            $data['created_time'] = date('Y-m-d H:i:s',time());
            $res = NoticeReadUserModel::insert($data);
            if($res <= 0){
                throw new CommonException('101413');
            }
        }
        $result = array(); 
        $result['id'] = $noticeData->id;
        $result['title'] = $noticeData->title;
        $result['content'] = $noticeData->content;
        $result['created_at'] = $noticeData->created_at->format('Y-m-d H:i:s');
        return $result;
    }
    /*
     * 查询当前登录用户有几个未读通知和几个未读公告
     */
    public function getUnread($loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $userId = $loginUser['id'];
        $roleArr = (new RoleUser)->getRoleIdByUser($userId);
        $afficheCount = $this->getUnreadNum(0, $userId, $roleArr);//未读通知个数
        $messageCount = $this->getUnreadNum(1, $userId, $roleArr);//未读公告个数
        $result = array();
        $result['afficheCount'] = $afficheCount;
        $result['messageCount'] = $messageCount;
        return $result;
    }
    public function getUnreadNum($type,$userId,$roleArr){
        $time = date("Y-m-d H:i:s",time());
        $sqlObject = DB::table('sys_notice as n')
                ->leftJoin('sys_notice_role as rn','rn.notice_id','=','n.id')
                ->leftJoin('sys_notice_user as un','un.notice_id','=','n.id');
        $sqlObjectTotal = $sqlObject->where(['n.is_delete'=>0,'n.type'=>$type])
                ->where(function ($query) use ($time) {
                    $query->where('n.notice_term',null)
                        ->orWhere('n.notice_term','>=',$time);
                    })
                ->Where(function ($query) use ($userId,$roleArr) {
                    $query->whereIn('rn.role_id',$roleArr)
                        ->orWhere('un.user_id','=',$userId)
                        ->orWhere('n.level','=',0);
                    });
        $total = $sqlObjectTotal->count('n.id');
        $sqlObjectRead = $sqlObject->leftJoin('sys_notice_read_user as urn','urn.notice_id','=','n.id')
                ->where(['n.is_delete'=>0,'n.type'=>$type,'urn.user_id'=>$userId])
                ->Where(function ($query) use ($userId,$roleArr) {
                    $query->whereIn('rn.role_id',$roleArr)
                        ->orWhere('un.user_id','=',$userId)
                        ->orWhere('n.level','=',0);
                    });
        $readCount = $sqlObjectRead->count('n.id');
        return $total-$readCount;
    }
    /*
     * 其他操作添加公告通知
     * 公告标题(title)，公告内容(content)，公告阅读期限(noticeTerm)，
     * 公告类型（通知/公告）(type)(0：通知，1：公告)，
     * 公告级别(全员/角色/用户)(level)(0：全员，1：角色授权，2：用户授权)，
     * 用户/角色ID(impowerIds)(字符串用逗号隔开)
     */
    public function addNoticeCaution($input,$loginUser){
        $noticeId = getUuid();
        DB::beginTransaction();
        $addRes = $this->addNotice($input,$loginUser,$noticeId);
        if($input['level'] == 1){//角色授权
            $impowerRes = $this->impowerRole($noticeId, $input['impowerIds']);
        }elseif($input['level'] == 2){//用户授权
            $impowerRes = $this->impowerUser($noticeId, $input['impowerIds']);
        }elseif($input['level'] == 0){//全员
            $impowerRes = TRUE;
        }else{
            return FALSE;
        }
        
        if($addRes == TRUE && $impowerRes == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
        
    }
    
    
    
    
    
    
}
