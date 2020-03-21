<?php

namespace App\Http\Models\Sms;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use App\Http\Models\Customer\Customer;
use Illuminate\Support\Facades\DB;

class SmsCommandModel extends BaseModel
{
    protected $table = 'sms_command';

    /*
     * 新建指令模板内容
     */
    public function addSmsCommand($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $smsCommandModel = new SmsCommandModel();
        $smsCommandModel->id = getUuid();
        $smsCommandModel->command_name = $input['name'];
        $smsCommandModel->command_content = $input['content'];
        $smsCommandModel->customer_id = $loginUser['customer_id'];
        $smsCommandModel->create_user_id = $loginUser['id'];
        $res = $smsCommandModel->save();
        return $res;
    }
    /*
     * 编辑指令模板内容
     */
    public function updateSmsCommand($input,$id){
        $data['command_name'] = $input['name'];
        $data['command_content'] = $input['content'];
        $res = $this->where('id','=',$id)->update($data);
        return $res;
    }
    /*
     * 短信指定模板列表
     */
    public function getSmsCommand($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        if($loginUser['is_owner'] == 1){//网来员工
            $where[] = ['create_user_id','=',$loginUser['id']];
        }else{//客户
            $where[] = ['customer_id','=',$loginUser['customer_id']];
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 获取列表分类数据
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $count = $this->where($where)->count('id');//总条数
        $commandData =  $this->where($where)->orderBy('created_at','DESC')
                ->offset($offset)->limit($pageSize)
         ->get(['id','command_name','command_content','created_at'])->toArray();
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['data'] = $commandData; 
        return $result;
    }
    /*
     * 删除指令模板内容
     */
    public function destroySmsCommand($id){
        $res = $this->where('id',$id)->delete();
        return $res;
    }
    /*
     * 条件查询
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['name']) && !empty($input['name'])){
            $where[] = ['command_name', 'like', '%'.$input['name'].'%'];
        }
        if(isset($input['content']) && !empty($input['content'])){
            $where[] = ['command_content', 'like', '%'.$input['content'].'%'];
        }
        return $where;
    }
    /*
     * 获取某ID的信息
     */
    public function getSmsCommandInfo($id){
        $data = $this->where('id',$id)->first(['id','command_name','command_content','created_at']);
        if(empty($data)){
            throw new CommonException('108154');
        }
        return $data;
    }
    
    
    
    

}







