<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\User;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeDetailModel;


class Gateway extends BaseModel
{
    protected $table = 'sys_gateway_config';
    /*
     * 获取所有用户列表
     */
    public function getGateways($input){
        $where = array();
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = User::getWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        
        $data = $this->setPageData($where,$input['page'],$input['pageSize']);
        //var_dump($data);exit;
        return $data;
    }
    /*
     * 获取所有网关详细信息
     */
    public function setPageData($where,$page,$pageSize){
        $str = ['id','gateway_name','gateway_type','gateway_ip','gateway_port','sp_id','sp_code','shared_secret','connect_count','time_out','service_id','is_use'];
        $offset = ($page-1) * $pageSize;
        $sqlObject = $this;
        if(!empty($where)){
            $sqlObject = $sqlObject->where($where);
        }
        $count = $sqlObject->count('id');//总条数
        $data = $sqlObject->offset($offset)->limit($pageSize)
                ->get($str);
        //print_r($where);exit;
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['data'] = $data;
        return $result; 
    }
    /*
     * 添加网关信息
     */
    public function addGateway($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $id = getUuid();
        $gatewayModel = new Gateway();
        $gatewayModel->id = $id;
        $gatewayModel->gateway_name =$input['gatewayName'];
        $gatewayModel->gateway_type = $input['gatewayType'];
        $gatewayModel->gateway_ip = $input['gatewayIp'];
        $gatewayModel->gateway_port = $input['gatewayPort'];
        $gatewayModel->sp_id = $input['spId'];
        $gatewayModel->sp_code = $input['spCode'];
        $gatewayModel->shared_secret = $input['sharedSecret'];
        $gatewayModel->connect_count = $input['connectCount'];
        $gatewayModel->time_out = $input['timeOut'];
        $gatewayModel->service_id = $input['serviceId'];
        $gatewayModel->is_use = $input['isUse'];
        $gatewayModel->create_user_id = $loginUser['id'];
        $gatewayModel->create_user_name = $loginUser['real_name'];
        $res = $gatewayModel->save();
        return $res;   
    }
    /*
     * 编辑网关信息
     */
    public function updateGateway($input,$id,$loginUser){
        $data = array();
        $data['gateway_name'] =$input['gatewayName'];
        $data['gateway_type'] = $input['gatewayType'];
        $data['gateway_ip'] = $input['gatewayIp'];
        $data['gateway_port'] = $input['gatewayPort'];
        $data['sp_id'] = $input['spId'];
        $data['sp_code'] = $input['spCode'];
        $data['shared_secret'] = $input['sharedSecret'];
        $data['connect_count'] = $input['connectCount'];
        $data['time_out'] = $input['timeOut'];
        $data['service_id'] = $input['serviceId'];
        $data['is_use'] = $input['isUse'];
        $data['create_user_id'] = $loginUser['id'];
        $data['create_user_name'] = $loginUser['real_name'];
        $res = $this->where('id',$id)->update($data);
        return $res;
    }
    
    /*
     * 删除网关信息
     */
    public function destroyGateway($id){
        $res = $this->where('id',$id)->delete();
        return $res;
    }
    /*
     * 根据ID获取网关名称
     */
    public function getGatewayName($id){
        $data = $this->where('id',$id)->first(['gateway_name']);
        if(empty($data)){
            throw new CommonException('101254');
        }
        return $data->gateway_name;
    }
    
    
    
    
    
    
    
    
    
    
    
}
