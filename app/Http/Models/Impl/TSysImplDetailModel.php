<?php

namespace App\Http\Models\Impl;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\TypeDetailModel;

class TSysImplDetailModel extends BaseModel
{
    protected $table = 'sys_impl_detail';
    public $timestamps = false;
    /*
     * 请求参数列表
     */
    public function implDetailList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $where = [];
        $where[] = ['impl_id', '=', $input['implId']];
        /*if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search);
        }*/
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
        $data =  $sqlObject->orderBy('create_time','ASC')
                ->offset($offset)->limit($pageSize)
                ->get(['id','param_name','type','is_required','default','describe']);
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(!($data->isEmpty())){
            foreach($data as $value){
                $value->is_required = $value->is_required == 1?'是':'否';//是否必填
            }
        }
        $result['data'] = $data; 
        return $result;
    }
    /*
     * 新建请求参数
     */
    public function addImplDetail($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $data['id'] = getUuid();
        $data['impl_id'] = $input['implId'];
        $data['param_name'] = $input['paramName'];
        $data['type'] = $input['type']; 
        $data['is_required'] = $input['isRequired'];
        $data['default'] = $input['default'];
        if(isset($input['describe']) && !empty($input['describe'])){
            $data['describe'] = $input['describe'];
        }
        $data['create_time'] = date('Y-m-d H:i:s',time());
        $res = $this->insert($data);
        return $res;
    }
    /*
     * 修改请求参数
     */
    public function updateImplDetail($input,$id){
        $detailData = $this->getImplDetailInfo($id);
        $data['param_name'] = $input['paramName'];
        $data['type'] = $input['type']; 
        $data['is_required'] = $input['isRequired'];
        $data['default'] = $input['default'];
        if(isset($input['describe']) && !empty($input['describe'])){
            $data['describe'] = $input['describe'];
        }
        $res = $this->where('id','=',$id)->update($data);
        return $res;
    }
    /*
     * 获取请求参数详情
     */
    public function getImplDetailInfo($id){
        $data = $this->where('id',$id)
                ->first(['id','param_name','type','is_required','default','describe']);
        if(empty($data)){
            throw new CommonException('110003');
        }
        return $data;
    }
    /*
     * 删除请求参数
     */
    public function destroyImplDetail($id){
        $res = $this->where('id',$id)->delete();
        return $res;
    }
    
    /*
     * 搜索条件
     */
    /*public function getSearchWhere($input){
        $where = array();
        if(isset($input['name']) && !empty($input['name'])){
            $where[] = ['name', 'like', '%'.$input['name'].'%'];
        }
        if(isset($input['status'])){
            $where[] = ['status', '=', $input['status']];
        }
        return $where;
    }*/
        
    
    
    
    
    

}







