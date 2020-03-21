<?php

namespace App\Http\Models\Customer;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;


class CustomerContact extends BaseModel
{
    protected $table = 'sys_customer_contact';
    /*
     * 客户联系人列表
     */
    public function getContacts($input){
        if(!isset($input['customerId']) && empty($input['customerId'])){
            throw new CommonException('102012');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $where[] = ['customer_id','=',$input['customerId']];//只查询此用户下的联系人列表
        $where[] = ['status','=',0];//未删除的联系人
        $data = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 根据客户ID找到客户联系人表未删除的主要联系人的ID
     */
    public function getContactId($id){
        $id = $this->where([['customer_id','=',$id],['is_main','=',1],['status','=',0]])->first(['id']);
        if(empty($id)){
            return 0;
        }
        return $id->id;
    }
    /*
     * 删除客户联系人
     */
    public function destroyContact($id){
        $time = date('Y-m-d H:i:s',time());
        $res = $this->where('id',$id)->update(['status'=>1,'deleted_at'=>$time]);
        return $res;
    }
    /*
     * 获取所有联系人详细信息
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $count = $this->where($where)->get(['id'])->count();//总条数
        $customerData = $this->where($where)
                ->offset($offset)->limit($pageSize)->orderBy('is_main','desc')
                ->get(['id','contact_name','contact_sex','contact_moible','is_main'])
                ->toArray();
        
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($customerData)){
            $result['data'] = [];
            return $result;
        }
        foreach ($customerData as &$value){
            if($value['is_main'] == 1){
                $value['is_main'] = "是";
            }else{
                $value['is_main'] = "否";
            }
            if($value['contact_sex'] == 1){
                $value['contact_sex'] = "女";
            }else{
                $value['contact_sex'] = "男";
            }
        }
        $result['data'] = $customerData;
        return $result; 
    }
    /*
     * 设置成主要客户联系人
     */
    public function setMain($input){
        if(!isset($input['id']) && empty($input['id'])){
            throw new CommonException('102011');
        }
        if(!isset($input['customerId']) && empty($input['customerId'])){
            throw new CommonException('102012');
        }
        $count = $this->where('customer_id',$input['customerId'])->where('is_main',1)->count('id');
        DB::beginTransaction();
        $res = 1;
        if($count>0){
            $res = $this->where('customer_id',$input['customerId'])->where('is_main',1)->update(['is_main'=>0]);
        }
        $res1 = $this->where('id',$input['id'])->update(['is_main'=>1]);
        if($res>0 && $res1>0){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
        
        
    }
    
    
}
