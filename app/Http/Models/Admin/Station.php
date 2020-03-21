<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\User;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TSysAreaModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Admin\TypeDetailModel;

class Station extends BaseModel
{
    protected $table = 'sys_station_config';
    /*
     * 获取落地列表
     */
    public function getStation($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        
        $where = "";
        $areaWhereIn = "";
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = User::getWhere($search);
            if(isset($search['areaCode']) && !empty($search['areaCode'])){
                $areaWhereIn = (new TSysAreaModel)->getCityCodeByName($search['areaCode']);    
            }
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize'],$areaWhereIn);
        return $data;
    }
    
    /*
     * 获取所有落地详细信息
     */
    public function getPageData($where,$page,$pageSize,$areaWhereIn){
        $offset = ($page-1) * $pageSize;
        $str = ['id','station_name','operator_type','api_key','api_url','station_code','balance_amount','station_account','sub_api_key','area_code'];
        $sqlObject = $this;
        if(!empty($where)){
            $sqlObject = $sqlObject->where($where);
        }
        if(!empty($areaWhereIn)){
            $sqlObject = $sqlObject->whereIn('area_code',$areaWhereIn);
        }
        $count = $sqlObject->count();//总条数
        $data = $sqlObject->offset($offset)->limit($pageSize)
                ->get($str);
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
        $operatorTypeGroup = TypeDetailModel::getDetailsByCode('operator_type');
        foreach ($data as $value){
            $areaName = $this->getAreaName($value->area_code);
            $value->areaName = $areaName;
            if(!empty($value->operator_type)){
                $value->operator_type = $operatorTypeGroup[$value->operator_type];
            }
        }
        $result['data'] = $data;
        return $result;
    }
    
    /*
     * 根据ID获取省市名称
     */
    public function getAreaName($code){
        $data = TSysAreaModel::where('code',$code)
                ->first(['area_name']);
        if(empty($data)){
            return $data;
        }
        return $data->area_name;
    }
    /*
     * 获取落地名称
     */
    public function getStationName($id){
        $data = $this->where('id',$id)->first(['station_name']);
        if(empty($data)){
            throw new CommonException('101201');//该落地不存在
        }
        return $data->station_name;
    }
    
    
    
    
    
    
    
}
