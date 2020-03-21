<?php

namespace App\Http\Models\Impl;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Impl\TSysImplDetailModel;

class TSysImplModel extends BaseModel
{
    protected $table = 'sys_impl';
    public $timestamps = false;
    /*
     * 接口列表
     */
    public function implList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $where = [];
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
     * 获取列表分页数据
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $sqlObject = $this->where($where);
        $count = $sqlObject->count('id');//总条数
        $data =  $sqlObject->orderBy('create_time','DESC')
                ->offset($offset)->limit($pageSize)
                ->get();
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(!($data->isEmpty())){
            $statusGroup = TypeDetailModel::getDetailsByCode('impl_status');
            foreach($data as $value){
                $value->status = $statusGroup[$value->status]['name'];//接口状态
            }
        }
        $result['data'] = $data; 
        return $result;
    }
    /*
     * 新建接口
     */
    public function addImpl($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $data['id'] = getUuid();
        $data['name'] = $input['name'];
        $data['method'] = $input['method'];
        $data['request_way'] = $input['requestWay']; 
        $data['url'] = $input['url'];
        if(isset($input['describe']) && !empty($input['describe'])){
            $data['describe'] = $input['describe'];
        }
        $data['status'] = 0;
        $data['create_time'] = date('Y-m-d H:i:s',time());
        $res = $this->insert($data);
        return $res;
    }
    /*
     * 修改接口
     */
    public function updateImpl($input,$id){
        $implData = $this->where('id',$id)->first(['id']);
        if(empty($implData)){
            throw new CommonException('110004');
        }
        $data['name'] = $input['name'];
        $data['method'] = $input['method'];
        $data['request_way'] = $input['requestWay']; 
        $data['url'] = $input['url'];
        if(isset($input['describe']) && !empty($input['describe'])){
            $data['describe'] = $input['describe'];
        }
        $res = $this->where('id','=',$id)->update($data);
        return $res;
    }
    /*
     * 删除接口
     */
    public function destroyImpl($id){
        $res = $this->where('id',$id)->delete();
        return $res;
    }
    /*
     * 查看接口详情
     */
    public function getImplInfo($id){
        $implData = $this->where('id',$id)
                ->first(['name','method','request_way','url','describe']);
        if(empty($implData)){
            throw new CommonException('110004');
        }
        $detailData = TSysImplDetailModel::orderBy('create_time','ASC')
                ->where('impl_id',$id)
                ->get(['id','param_name','type','is_required','default','describe'])
                ->toArray();
        if(!empty($detailData)){
            foreach($detailData as &$value){
                $value['is_required']= $value['is_required'] == 1?'是':'否';//是否必填
            }
        }
        $implData->detailData = $detailData;
        return $implData;
        //print_r($implData);exit;
    }
    /*
     * 搜索条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['name']) && !empty($input['name'])){
            $where[] = ['name', 'like', '%'.$input['name'].'%'];
        }
        if(isset($input['status'])){
            $where[] = ['status', '=', $input['status']];
        }
        return $where;
    }
    /*
     * 接口文档
     */
    public function implDocument($loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if($loginUser['is_owner'] == 1){
            $result['id'] = '';
            $result['customerCode'] = '';
            return $result;
        }
        $customerData = Customer::where('id',$loginUser['customer_id'])
                ->first(['id','customer_code']);
        if(empty($customerData)){
            throw new CommonException('102003');
        }
        return $customerData;
    }
    
    
    
    
    
    

}







