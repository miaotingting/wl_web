<?php

namespace App\Http\Models\Operation;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeDetailModel;

class Package extends BaseModel
{
    protected $table = 'c_package';
    /*
     * 获取所有套餐列表
     */
    public function getPackages($input){
        $where = array();
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = self::getWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        if(isset($input['type']) || !empty($input['type'])){
            $where[] = ['package_type', '=', $input['type']];
            $where[] = ['fees_type', '=', '1001'];
            $where[] = ['status', '=', 0];
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 创建客户页面显示内容
     */
    public function createPage(){
        
        $result['fees_type'] = getEnums(config("info.fees_type"));
        $result['time_unit'] = getEnums(config("info.time_unit"));
        $result['settlement_type'] = getEnums(config("info.settlement_type"));
        $result['package_type'] = getEnums(config("info.package_type"));
        return $result;
        
    }
    /*
     * 添加套餐
     */
    public function addPackage($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $package = new Package();
        $package->id = getUuid();
        $package->package_name = $input['packageName'];
        $package->package_code = getOrderNo('S');
        //$packageTypeGroup = config('info.package_type');
        $package->package_type = $input['packageType'];
        $package->settlement_type = $input['settlementType'];
        $package->time_length = $input['timeLength'];
        $package->time_unit = $input['timeUnit'];
        $package->price = $input['price'];
        $package->min_sale_price = $input['minSalePrice'];
        $package->consumption = $input['consumption'];
        $package->fees_type = $input['feesType'];
        $package->is_international_pack = $input['isInternationalPack'];
        $package->describe = $input['describe'];
        $package->create_user_id = $loginUser['id'];
        $package->create_user_name = $loginUser['user_name'];
        if($input['packageType'] == 'FLOW' || $input['packageType'] == 'VOICE'){
            $package->max_price = $input['maxPrice'];
            $package->min_price = $input['minPrice'];
        }
        $res = $package->save();
        return $res;
        
    }
    /*
     * 编辑套餐
     */
    public function updatePackage($input,$id){
        $data = array();
        $data['package_name'] = $input['packageName'];
        $data['settlement_type'] = (int)$input['settlementType'];
        $data['time_length'] = (int)$input['timeLength'];
        $data['time_unit'] = $input['timeUnit'];
        $data['price'] = $input['price'];
        $data['min_sale_price'] = $input['minSalePrice'];
        $data['consumption'] = $input['consumption'];
        $data['fees_type'] = $input['feesType'];
        $data['is_international_pack'] = $input['isInternationalPack'];
        $data['describe'] = $input['describe'];
        if($input['packageType'] == 'FLOW' || $input['packageType'] == 'VOICE'){
            $data['max_price'] = $input['maxPrice'];
            $data['min_price'] = $input['minPrice'];
        }
        $res = $this->where('id',$id)->update($data);
        return $res;
        
    }
    
    
    /*
     * 设置套餐失效
     */
    public function destroys($id){
        $res = $this->where('id',$id)->update(['status'=>1]);
        return $res;
    }
    
    /*
     * 获取所有套餐详细信息
     */
    public function getPageData($where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $sqlObject = $this;
        if(!empty($where)){
            $sqlObject = $sqlObject->where($where);
        }
        $count = $sqlObject->count('id');//总条数
        $packageData = $sqlObject->offset($offset)->limit($pageSize)
                ->get(['id','package_name','package_code','status','package_type','settlement_type',
                    'is_international_pack','time_length','time_unit','consumption','price',
                    'min_sale_price','fees_type']);
        
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($packageData->isEmpty()){
            $result['data']=[];
            return $result;
        }
        //print_r($packageData);exit;
        $packageTypeGroup = TypeDetailModel::getDetailsByCode('package_type');
        $settlementTypeGroup = TypeDetailModel::getDetailsByCode('settlement_type');
        $isInternationalPackGroup = TypeDetailModel::getDetailsByCode('is_international_pack');
        $packageStatusGroup = TypeDetailModel::getDetailsByCode('package_status');
        $timeUnitGroup = TypeDetailModel::getDetailsByCode('time_unit');
        $feesTypeGroup = TypeDetailModel::getDetailsByCode('fees_type');
        foreach ($packageData as $value){
            $value->package_type = $packageTypeGroup[$value->package_type]['name'];//套餐类型
            $value->settlement_type = $settlementTypeGroup[$value->settlement_type]['name'];//结算类型
            $value->is_international_pack = $isInternationalPackGroup[$value->is_international_pack]['name'];//是否是国际套餐组
            $value->status = $packageStatusGroup[$value->status]['name'];//套餐状态
            $value->time_unit = $timeUnitGroup[$value->time_unit]['name'];//时间单位
            $value->fees_type = $feesTypeGroup[$value->fees_type]['name'];//计费类型
            
        }
        $result['data'] = $packageData;
        return $result; 
    }
    /*
     * 获取某个套餐详细信息
     */
    public function getPackageInfo($id){
        
        $data = $this->where('id',$id)
                ->first(['id','package_name','package_code','status','package_type','settlement_type','is_international_pack','time_length','time_unit','consumption','price','min_sale_price','describe','fees_type','min_price','max_price']);
        if(empty($data)){
            throw new CommonException('103004');
        }
        $data->package_type = self::getTypeDetail('package_type',$data->package_type);//套餐类型
        $data->settlement_type = self::getTypeDetail('settlement_type',$data->settlement_type);//结算类型
        $data->is_international_pack = self::getTypeDetail('is_international_pack',$data->is_international_pack);//是否是国际套餐组
        $data->status = self::getTypeDetail('package_status',$data->status);//套餐状态
        $data->time_unit = self::getTypeDetail('time_unit',$data->time_unit);//时间单位
        $data->fees_type = self::getTypeDetail('fees_type', $data->fees_type);//计费类型
        return $data;
    }
    
    /*
     * 获取where条件
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['packageName']) && !empty($input['packageName'])){
            $where[] = ['package_name', 'like', '%'.$input['packageName'].'%'];
        }
        if(isset($input['packageCode']) && !empty($input['packageCode'])){
            $where[] = ['package_code', 'like', '%'.$input['packageCode'].'%'];
        }
        if(isset($input['status'])){
            if(empty($input['status'])){
                    if($input['status'] == "0"){
                        $where[] = ['status', '=', $input['status']];
                    }
                }else{
                    $where[] = ['status', '=', $input['status']];
                }
        }
        if(isset($input['packageType']) && !empty($input['packageType'])){
            $where[] = ['package_type', '=', $input['packageType']];
        }
        if(isset($input['settlementType'])){
            if(empty($input['settlementType'])){
                    if($input['settlementType'] == "0"){
                        $where[] = ['settlement_type', '=', $input['settlementType']];
                    }
                }else{
                    $where[] = ['settlement_type', '=', $input['settlementType']];
                }
        }
        if(isset($input['isInternationalPack'])){
            if(empty($input['isInternationalPack'])){
                    if($input['isInternationalPack'] == "0"){
                        $where[] = ['is_international_pack', '=', 0];
                    }
                }else{
                    $where[] = ['is_international_pack', '=', $input['isInternationalPack']];
                }
        }
        if(isset($input['timeUnit']) && !empty($input['timeUnit'])){
            $where[] = ['time_unit', '=', $input['timeUnit']];
        }
        if(isset($input['maxConsumption']) && !empty($input['maxConsumption'])){
            $where[] = ['consumption', '<=', $input['maxConsumption']];
        }
        if(isset($input['minConsumption']) && !empty($input['minConsumption'])){
            $where[] = ['consumption', '>=', $input['minConsumption']];
        }
        if(isset($input['maxPrice']) && !empty($input['maxPrice'])){
            $where[] = ['price', '<=', $input['maxPrice']];
        }
        if(isset($input['minPrice']) && !empty($input['minPrice'])){
            $where[] = ['price', '>=', $input['minPrice']];
        }
        return $where;
    }
    /*
     * 从数据字典获取详细数据
     * name 数据字典编码
     * value 当前值
     */
    public static function getTypeDetail($name,$value){
        $nameGroup = TypeDetailModel::getDetailsByCode($name);
        
        return $nameGroup[$value];
        
    }
    
    
    
    
    
    
}
