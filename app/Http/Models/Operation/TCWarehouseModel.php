<?php

namespace App\Http\Models\Operation;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\User;

class TCWarehouseModel extends BaseModel
{
    protected $table = 'c_warehouse';
    /*
     * 仓库列表
     */
    public function getWarehouse($input){
        /*if(empty($loginUser)){
            throw new CommonException('300001');
        }*/
        $where = array();
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        
        
        $data = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 列表整理
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $sqlObject = $this;
        if(!empty($where)){
            $sqlObject =$sqlObject->where($where); 
        }
        $count = $sqlObject->count('id');//总条数
        $warehouseData = $sqlObject->orderBy('created_at','DESC')
                ->offset($offset)->limit($pageSize)
                ->get(['id','ware_name','admin_name','tel','card_total_num','card_stock_num',
                    'remark','created_at']);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($warehouseData)){
            $result['data'] = [];
            return $result;
        }
        foreach ($warehouseData as $value){
            $value->card_out_num = $value->card_total_num-$value->card_stock_num;//卡片剩余量
            //$value->status = $value->status == 0?'空仓':'使用中';
        }
        $result['data'] = $warehouseData;
        return $result; 
    }
    /*
     * 新建仓库
     */
    public function addWarehouse($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $data = array();
        $data['id'] = getUuid();
        $data['ware_name'] = $input['wareName'];
        $data['admin'] = $input['adminId'];
        $adminData = (new User)->getAdminInfo($input['adminId']);
        
        $data['admin_name'] = $adminData['real_name'];
        $data['tel'] = $input['tel'];
        if(isset($input['remark']) && !empty($input['remark'])){
            $data['remark'] = $input['remark'];
        }
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $res = $this->insert($data);
        return $res;
    }
    /*
     * 修改仓库信息
     */
    public function updateWarehouse($input,$id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        //$this->getWarehouseInfo($id);//判断此ID是否存在
        $data = array();
        $data['ware_name'] = $input['wareName'];
        $data['admin'] = $input['adminId'];
        $adminData = (new User)->getAdminInfo($input['adminId']);
        $data['admin_name'] = $adminData['real_name'];
        $data['tel'] = $input['tel'];
        if(isset($input['remark']) && !empty($input['remark'])){
            $data['remark'] = $input['remark'];
        }
        $res = $this->where('id',$id)->update($data);
        return $res;
    }
    /*
     * 获取某仓库的信息
     */
    public function getWarehouseInfo($id){
        $data = $this->where('id',$id)->first(['ware_name','admin','tel','remark']);
        if(empty($data)){
            throw new CommonException('103102');
        }
        return $data;
    }
    
    
    /*
     * 根据ID获取仓库名称
     */
    public function getWarehouseName($id){
        $data = $this->where('id',$id)->first(['ware_name']);
        if(empty($data)){
            throw new CommonException('103102');
        }
        return $data->ware_name;
    }
    
    
    
    /*
     * 获取仓库序号
     */
    /*public function getWareNum(){
        $data = $this->orderBy('ware_number','DESC')->limit(1)->first(['ware_number']);
        if(empty($data)){
            return 001;
        }
        $num = $data['ward_number']+1;
        return $num;
    }*/
    /*
     * 获取where条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['wareName']) && !empty($input['wareName'])){
            $where[] = ['ware_name', 'like', '%'.$input['wareName'].'%'];//仓库名称
        }
        if(isset($input['adminName']) && !empty($input['adminName'])){
            $where[] = ['admin_name', 'like', '%'.$input['adminName'].'%'];//负责人
        }
        
        return $where;
    }
    
    
    
    
    
}
