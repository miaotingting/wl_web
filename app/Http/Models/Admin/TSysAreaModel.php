<?php

namespace App\Http\Models\Admin;
use App\Http\Models\BaseModel;

class TSysAreaModel extends BaseModel
{
    protected $table = 'sys_area';
    /*
     * 获取所有城市列表
     */
    public function getAllCity(){
        $secondLevelData = $this->where('level',2)->get(['id','area_name','parent_id']);
        $result = array();
        foreach($secondLevelData as $key=>$value){
            $parentData = $this->where('id',$value['parent_id'])->first(['area_name']);
            $city = $parentData->area_name.'-'.$value->area_name;
            $result[$key]['cityId'] = $value->id;
            $result[$key]['cityName'] = $city;
        }
        return $result;
    }
    /*
     * 根据城市名模糊查询该城市获取ID
     */
    public function getCityCodeByName($name){
        $data = $this->where('area_name','like','%'.$name.'%')
                ->get(['code']);
        return $data;
    }
    
    
}
