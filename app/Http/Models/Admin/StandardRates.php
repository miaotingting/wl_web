<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Operation\Package;


class StandardRates extends BaseModel
{
    protected $table = 'sys_standard_rates';
    /*
     * 获取资费列表
     */
    public function getRates($input){
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $data = $this->getPageData($input['page'],$input['pageSize']);
        return $data;
    }
    
    
    
    
    /*
     * 获取分页数据
     */
    public function getPageData($page,$pageSize){
        $str = ['id','package_name','operator','flow_price','voice_price','sms_price'];
        $offset = ($page-1) * $pageSize;
        $count = $this->count('id');//总条数
        $data = $this->offset($offset)->limit($pageSize)
                ->get($str);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
        }else{
            $operatorTypeGroup = TypeDetailModel::getDetailsByCode('operator_type');
            foreach($data as $value){
                if(empty($value['operator'])){
                    $value['operator'] = '';
                }else{
                    $value['operator'] = $operatorTypeGroup[$value['operator']];
                }
            }
            $result['data'] = $data;
        }
        return $result; 
    }
    /*
     * 编辑资费信息
     */
    public function updateRates($input,$id){
        $data = array();
        $data['flow_price'] =$input['flowPrice'];
        $data['voice_price'] = $input['voicePrice'];
        $data['sms_price'] = $input['smsPrice'];
        $res = $this->where('id',$id)->update($data);
        return $res;
    }
    /*
     * 根据ID获取资费信息
     */
    public function getInfo($id){
        $data = $this->where('id',$id)
                ->first(['id','package_name','operator','flow_price','voice_price','sms_price']);
        return $data;
    }
    
    
    
    
    
    
    
    
    
    
    
}
