<?php

namespace App\Http\Models\Order;

use App\Events\MatterEvent;
use App\Http\Utils\Errors;
use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Matter\ProcessModel;
use App\Http\Models\Matter\ThreadModel;

class TCOrderTemplateModel extends BaseModel
{
    protected $table = 'c_order_template';
    public $timestamps = false;
    
    const CARD_TYPE_VOICE = "1002";  //语音卡
    const XF_FEES_TYPE = "1002";
    const STATUS_CHECKING = 1;
    const STATUS_CHECKEND = 2;
    const STATUS_DELETE = 3;
    const STATUS_REJECT = 5; //驳回

    const TASK_CODE = 'zfsp';

    /**
     * 修改状态
     * @param $no 单号
     * @param $status 要修改成的状态
     */
    function saveStatus($no,$status) {
        $info = $this->where(['template_code' => $no])->first();
        $info->status = $status;
        $info->save();
    }
    
    
    /*
     * 获取计划列表
     */
    public function getList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $orWhere = '';
        $where = [];
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search);
            if(isset($search['customerName']) && !empty($search['customerName'])){
                $orWhere = $search['customerName'];
            }
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $isSeller = RoleUser::getUserRoleData($loginUser['id'],config('info.role_xiaoshou_id'));
        if($isSeller == TRUE){
            $where[] = ['t.create_user_id','=',$loginUser['id']];//销售自己看自己的定制
        }
        $data = $this->getPageData($where,$orWhere,$input['page'],$input['pageSize']);
        return $data;
    }
    /*
     * 获取计划详细信息
     */
    public function getPageData($where,$orWhere,$page,$pageSize){
        $str = ['t.id','t.template_code','t.template_name','c.customer_code','t.customer_name',
            't.operator_type','t.fees_type','t.industry_type','t.status','t.describe','t.created_at'];
        $offset = ($page-1) * $pageSize;
        $object = DB::table('c_order_template as t')
                ->leftJoin('sys_customer as c','t.customer_id','=','c.id')
                ->where($where);
        if(!empty($orWhere)){
            $object = $object->where(function ($query) use ($orWhere) {
                            $query->where('t.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('c.customer_code','like','%'.$orWhere.'%');
                            });
        }
        $count = $object->count('t.id');//总条数
        $data = $object->orderBy('t.created_at','DESC')
                ->offset($offset)->limit($pageSize)
                ->get($str);
        //print_r($data);exit;
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
            return $result;
        }
        $statusGroup = TypeDetailModel::getDetailsByCode('t_c_order_template_status');
        $operatorTypeGroup = TypeDetailModel::getDetailsByCode('operator_type');
        $feesTypeGroup = TypeDetailModel::getDetailsByCode('t_c_order_template_fees_type');
        $industryTypeGroup = TypeDetailModel::getDetailsByCode('industry_type');
        foreach ($data as &$value){
            $value->status = $statusGroup[$value->status];
            $value->operator_type = $operatorTypeGroup[$value->operator_type];
            $value->fees_type = $feesTypeGroup[$value->fees_type];
            $value->industry_type = $industryTypeGroup[$value->industry_type];
        }
        $result['data'] = $data;
        return $result;
        
    }
    /**
     * 创建
     */
    function add($request,$loginUser) {
        if (empty($loginUser)) {
            throw new CommonException(Errors::NOT_LOGIN);
        }
        /*
         * 判断是否已经给此用户定制过此套餐(流量)
         */
        $orderTemplateData = $this->where([
                'customer_id'=>$request['customerId'],
                'flow_package_id'=>$request['flowPackageId']
                ])->first(['id']);
        if(!empty($orderTemplateData)){
            throw new CommonException('107153');
        }
        /*
         * 判断客户是否存在
         */
        $customerData = $this->getCustomerData($request['customerId']);
        
        DB::beginTransaction();
        $time = now();
        $this->id = getUuid($this->table);
        $this->template_code = getOrderNo('XFZF',$customerData->customer_code);
        $this->template_name = $request['templateName'];
        $this->customer_id = $request['customerId'];
        $this->customer_name = $customerData->customer_name;
        $this->contacts_name = $request['contactsName'];
        $this->contacts_mobile = $request['contactsMobile'];
        $this->operator_type = $request['operatorType'];
        $this->industry_type = $request['industryType'];
        $this->card_type = $request['cardType'];
        $this->model_type = $request['modelType'];
        $this->standard_type = $request['standardType'];
        $this->describe = $request['describe'];
        //$this->real_name_type = $request['realNameType'];
        $this->flow_card_price = $request['flowCardPrice'];
        $this->sms_card_price = $request['smsCardPrice'];
        //$this->voice_card_price = $request['voiceCardPrice'];
        $this->status = 0;//状态
        $this->create_user_id = $loginUser['id'];
        $this->create_user_name = $loginUser['real_name'];
        $this->created_at = $time;
        $this->updated_at = $time;
        //$this->pay_type = $request['payType'];
        $this->fees_type = self::XF_FEES_TYPE;
        //套餐
        if (array_has($request, 'isSms') && !empty($request['isSms']) && intval($request['isSms']) === 1
            && array_has($request, 'smsPackageId') && !empty($request['smsPackageId'])
            && array_has($request, 'smsExpiryDate') && !empty($request['smsExpiryDate']) && $request['smsExpiryDate'] > 0) {
            $isZero = is_zero($request['isSms']);
            if($isZero){
                $this->is_sms = 0;
            }else{
                $this->is_sms = 1;
            }
            $this->sms_package_id = $request['smsPackageId'];
            $this->sms_expiry_date = $request['smsExpiryDate'];
        }
        //流量必填
        $this->is_flow = 1;
        $this->flow_package_id = $request['flowPackageId'];
        $this->flow_expiry_date = $request['flowExpiryDate'];
        
        //如果语音卡，语音必填
        if ($request['cardType'] == self::CARD_TYPE_VOICE) {
            //$this->is_voice = $request['isVoice'];
            $this->voice_package_id = $request['voicePackageId'];
            $this->voice_expiry_date = $request['voiceExpiryDate'];
        } else if (array_has($request, 'isVoice') && !empty($request['isVoice']) && intval($request['isVoice']) === 1
            && array_has($request, 'voicePackageId') && !empty($request['voicePackageId'])
            && array_has($request, 'voiceExpiryDate') && !empty($request['voiceExpiryDate']) && $request['voiceExpiryDate'] > 0) {
            //$this->is_voice = $request['isVoice'];
            $this->voice_package_id = $request['voicePackageId'];
            $this->voice_expiry_date = $request['voiceExpiryDate'];
        }
        
        $isZero = is_zero($request['isVoice']);
        if($isZero){
            $this->is_voice = 0;
        }else{
            $this->is_voice = 1;
        }

        $res = $this->save();
        //修改客户续费方式
        //$resWay = $this->setCustomerRenewalWay($request['customerId']);
        if (!$res) {
           DB::rollBack();
           throw new CommonException('107151');
        }
        //创建开卡订单给6个角色发消息提醒 
        event(new MatterEvent(self::TASK_CODE,'有新的资费计划', '有新的资费计划待处理', $loginUser));
        //开启流程
        $this->startProcess(self::TASK_CODE, $this->template_code, $loginUser, $customerData->customer_name);
        DB::commit();
        return TRUE;
        
    }
    /*
     * 修改客户续费方式
     */
    public function setCustomerRenewalWay($customerId){
        $idData = Customer::where('id',$customerId)->first();
        $res = 1;
        if($idData->renewal_way == 1){
            $res = Customer::where('id',$customerId)->update(['renewal_way'=>2]);
        }
        return $res;
        
    }
    /**
     * 更新订单信息
     */
    function updateOrder($code, $request) {
        /*$orderTemplate = $this->getOrderTemplate($id);
        /*$data = $this->getIdData($id);
        if($data->status <= 1 ){
            throw new CommonException('107155');
        }*/
        //判断客户是否存在
        //unset($orderTemplate->feesType);
        /*$customerData = $this->getCustomerData($request['customerId']);
        $request = array_except($request, ['token','q','id','status','feesType']);
        //print_r($request);exit;
        foreach ($request as $key => $value) {
            $field = snake_case($key);//把驼峰格式换成带下划线的格式
            $orderTemplate->$field = $value;
        }
        unset($orderTemplate->fees_type);
        unset($orderTemplate->status);
        unset($orderTemplate->id);
        $orderTemplate->customer_name = $customerData->customer_name;
        $res = $orderTemplate->save();
        return $res;*/
        //DB::beginTransaction();
        /*
         * 判断客户是否存在
         */
        $customerData = $this->getCustomerData($request['customerId']);
        $time = now();
        $data['template_name'] = $request['templateName'];
        $data['customer_id'] = $request['customerId'];
        $data['customer_name'] = $customerData->customer_name;
        $data['contacts_name'] = $request['contactsName'];
        $data['contacts_mobile'] = $request['contactsMobile'];
        $data['operator_type'] = $request['operatorType'];
        $data['industry_type'] = $request['industryType'];
        $data['card_type'] = $request['cardType'];
        $data['model_type'] = $request['modelType'];
        $data['standard_type'] = $request['standardType'];
        $data['describe'] = $request['describe'];
        $data['flow_card_price'] = $request['flowCardPrice'];
        $data['sms_card_price'] = $request['smsCardPrice'];
        $data['updated_at'] = $time;
        //套餐
        if (array_has($request, 'isSms') && !empty($request['isSms']) && intval($request['isSms']) === 1
            && array_has($request, 'smsPackageId') && !empty($request['smsPackageId'])
            && array_has($request, 'smsExpiryDate') && !empty($request['smsExpiryDate']) && $request['smsExpiryDate'] > 0) {
            $data['is_sms'] = $request['isSms'];
            $data['sms_package_id'] = $request['smsPackageId'];
            $data['sms_expiry_date'] = $request['smsExpiryDate'];
        }
        //流量必填
        $data['is_flow'] = $request['isFlow'];
        $data['flow_package_id'] = $request['flowPackageId'];
        $data['flow_expiry_date'] = $request['flowExpiryDate'];
        
        //如果语音卡，语音必填
        if ($request['cardType'] == self::CARD_TYPE_VOICE) {
            $data['is_voice'] = $request['isVoice'];
            $data['voice_package_id'] = $request['voicePackageId'];
            $data['voice_expiry_date'] = $request['voiceExpiryDate'];
        } else if (array_has($request, 'isVoice') && !empty($request['isVoice']) && intval($request['isVoice']) === 1
            && array_has($request, 'voicePackageId') && !empty($request['voicePackageId'])
            && array_has($request, 'voiceExpiryDate') && !empty($request['voiceExpiryDate']) && $request['voiceExpiryDate'] > 0) {
            $data['is_voice'] = $request['isVoice'];
            $data['voice_package_id'] = $request['voicePackageId'];
            $data['voice_expiry_date'] = $request['voiceExpiryDate'];
        }
        $res = $this->where('template_code', $code)->update($data);
        return $res;
    }
    /*
     * 
     */
    public function getIdData($id){
        $data = $this->where('template_code', $id)->first(['id','status']);
        if(empty($data)){
            throw new CommonException('107154');
        }
        return $data;
    }
    /**
     * 获取计划详情
     */
    function getOrderTemplate($id) {
        $str = ['id','template_name','customer_id','customer_name','contacts_name','contacts_mobile',
            'operator_type','industry_type','card_type','status','standard_type','model_type',
            'flow_package_id','sms_package_id','voice_package_id','flow_expiry_date','sms_expiry_date',
            'voice_expiry_date','flow_card_price','sms_card_price','voice_card_price','real_name_type',
            'is_flow','is_sms','is_voice','describe','fees_type'];
        $data = $this->where('template_code', $id)->first($str);
        
        if(empty($data)){
            throw new CommonException('107154');
        }
        if($data->fees_type == self::XF_FEES_TYPE){//续费计划
            unset($data->real_name_type);
            $statusGroup = TypeDetailModel::getDetailsByCode('t_c_order_template_status');
            $feesTypeGroup = TypeDetailModel::getDetailsByCode('t_c_order_template_fees_type');
            $data->status = $statusGroup[$data->status]['name'];
            $data->fees_type = $feesTypeGroup[$data->fees_type]['name'];
        }
        return $data;
    }
    /*
     * 查询客户信息
     */
    public function getCustomerData($customerId){
        $customerData = Customer::where(['id'=>$customerId,'status'=>0])
                ->first(['customer_code','customer_name']);
        if(empty($customerData)){
            throw new CommonException('102003');
        }
        return $customerData;
    }
    /*
     * 删除计划
     */
    public function destroyOrder($id){
        $data = $this->where('id',$id)->first(['template_code','status']);
        $process = ProcessModel::where('business_order',$data->template_code)->first(['process_id']);
        if($data->status == 2 || $data->status == 3 || $data->status == 4){
            DB::beginTransaction();
            $res = $this->where('id',$id)->delete();
            $resP = ProcessModel::where('business_order',$data->template_code)->delete();
            $resT = ThreadModel::where('process_id',$process->process_id)->delete();
            if($res == 1 && $resP == 1 && $resT == 1){
                DB::commit();
                return TRUE;
            }else{
                DB::rollBack();
                return FALSE;
            }
        }else{
            throw new CommonException('107155');
        }
        
    }
    /*
     * 设置失效/生效
     */
    public function setStatus($id){
        $idData = $this->where('id',$id)->first(['status']);
        if(empty($idData)){
            throw new CommonException('107154');
        }
        switch ($idData->status) {
            case 2:
                $status = 4;
                break;
            case 4:
                $status = 2;//审核通过
                break;
            default:
                throw new CommonException('107155');
        }
        $res = $this->where('id',$id)->update(['status'=>$status]);
        return $res;
    }
    /*
     * 更新计划名称 
     */
    public function updateName($input){
        $res = $this->where('template_code',$input['templateCode'])
                ->update(['template_name'=>$input['templateName']]);
        return $res;
    }
    /*
     * 获取where条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['templateCode']) && !empty($input['templateCode'])){
            $where[] = ['t.template_code', 'like', '%'.$input['templateCode'].'%'];
        }
        if(isset($input['templateName']) && !empty($input['templateName'])){
            $where[] = ['t.template_name', 'like', '%'.$input['templateName'].'%'];
        }
        if(isset($input['operatorType']) && !empty($input['operatorType'])){
            $where[] = ['t.operator_type', '=', $input['operatorType']];
        }
        if(isset($input['status'])){
            if(empty($input['status'])){
                if($input['status'] == "0"){
                    $where[] = ['t.status', '=', 0];
                }
            }else{
                $where[] = ['t.status', '=', $input['status']];
            }
        }
        //起始时间
        if(isset($input['startTime']) && !empty($input['startTime'])){
            $where[] = ['t.created_at', '>=', $input['startTime']];
        }
        //结束时间
        if(isset($input['endTime']) && !empty($input['endTime'])){
            $where[] = ['t.created_at', '<=', $input['endTime']];
        }
        return $where;
    }
    
}
