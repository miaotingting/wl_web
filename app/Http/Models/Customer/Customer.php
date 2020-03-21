<?php

namespace App\Http\Models\Customer;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\User;
use App\Http\Models\Customer\CustomerContact;
use App\Http\Models\Admin\TSysAreaModel;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Finance\CustomerAccountModel;
use App\Http\Models\Profit\TSysCustomerMonthModel;
class Customer extends BaseModel
{
    protected $table = 'sys_customer';

    const FIRST_LEVEL = 1;


    /*protected $firstCustomerRoleId = 'd3bbc1ce6ffd55c9844571b600fe3154';//一级客户角色
    protected $secondCustomerRoleId = 'da45c607be5f561fa2f5d6f3cc5f15d7';//二级客户角色
    protected $marketRole = '456e33858433527eb219cffb4a133698';//销售人员角色*/
    //获取客户列表
    function getCustomerFullNames(array $search, $loginUser,$level=1) {
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $sql = $this;
        if (array_has($search, 'fullName') && !empty($search['fullName'])) {
            $sql = $sql->orWhere('sys_customer.customer_name', 'like', '%'.$search['fullName'].'%')
                        ->orWhere('sys_customer.customer_code', 'like', '%'.$search['fullName']. '%');
        }
        //判断登录用户是否是销售人员（是则只显示属于自己的客户）
        $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$loginUser['id']])->first(['id']);
        if(!empty($isNoMarketRole)){
            $sql = $sql->where('sys_customer.account_manager_id','=',$loginUser['id']);
        }
        $where = [];
        $where[] = ['sys_customer_contact.is_main','=', 1];
        $where[] = ['sys_customer.status','=', 0];
        if($level == 1){
            $where[] = ['sys_customer.level','=', 1];
        }
        //->where("sys_customer_contact.is_main", 1)->where('sys_customer.level', 1)->where('sys_customer.status',0)
        $customers = $sql->leftJoin("sys_customer_contact", "sys_customer.id", "=", "sys_customer_contact.customer_id")->where($where)->get(['sys_customer.id', 'sys_customer.customer_name', 'sys_customer.customer_code', 'sys_customer.customer_name as full_name', 'sys_customer_contact.id as contact_id', 'sys_customer_contact.contact_name', 'sys_customer_contact.contact_moible']);
        if ($customers->isEmpty()) {
            return $customers;
        }
        return $customers;
    }

    function getFullNameAttribute() {
        return "({$this->customer_code}){$this->customer_name}";
    }

    /*
     * 获取所有客户列表
     */
    public function getCustomers($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getWhere($search);
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $customerId = "";
        if($loginUser['is_owner'] == 0){//客户登录 
            $customerId = $loginUser['customer_id'];
        }else{//网来人员登录
            //判断登录用户是否是销售人员（是则只显示属于自己的客户）
            $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$loginUser['id']])->first(['id']);
            if(!empty($isNoMarketRole)){
                $where[] = ['sys_customer.account_manager_id','=',$loginUser['id']];
            }
        }
        if(isset($input['customerId']) && !empty($input['customerId'])){//查询客户ID
            $customerId = $input['customerId'];
        }
        
        $where[] = ['sys_customer.status','=',0];//只查询未被删除客户
        
        $data = $this->getPageData($where,$input['page'],$input['pageSize'],$customerId,$loginUser);
        
        return $data;
    }
    /*
     * 获取所有客户详细信息
     */
    public function getPageData($where,$page,$pageSize,$customerId,$loginUser){
        $str = ['sys_customer.id','sys_customer.customer_code','sys_customer.customer_name',
                'sys_customer.level','sys_customer.account_manager_id','sys_customer.account_manager_name',
                'sys_customer.cooperation_time','sys_customer.city_name','sys_customer.industry_type',
                'sys_customer.parent_customer_name','sys_customer.customer_type','account.balance_amount as customer_balance'];
        
        if(empty($customerId)){
            $pageData = $this->returnAllCustomer($str,$where, $page, $pageSize);
        }else{
            $pageData = $this->returnCustomerAndSubData($str,$customerId, $where, $page, $pageSize);
        }
        $customerData = $pageData['data'];
        $result = array();
        $pageCount = ceil($pageData['count']/$pageSize); #计算总页面数    
        $result['count'] = $pageData['count'];
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($customerData)){
            $result['data'] = [];
            return $result;
        }
        $levelGroup = TypeDetailModel::getDetailsByCode('level');
        $industryTypeGroup = TypeDetailModel::getDetailsByCode('industry_type');
        $customerTypeGroup = TypeDetailModel::getDetailsByCode('customer_type');
        foreach ($customerData as &$value){
            $value['level'] = $levelGroup[$value['level']];
            if(!empty($value['industry_type'])){
                $value['industry_type'] = $industryTypeGroup[$value['industry_type']];
            }
            $value['customer_type'] = $customerTypeGroup[$value['customer_type']];
            if(empty($value['customer_balance'])){
                $value['customer_balance'] = "0.00";
            }
        }
        $result['data'] = $customerData;
        return $result; 
    }
    /*
     * 所有客户数据
     */
    public function  returnAllCustomer($str,$where,$page,$pageSize){
        $offset = ($page-1) * $pageSize;
        $count = $this->where($where)->count('id');//总条数
        $customerData = $this->where($where)
            ->leftJoin('sys_customer_account as account','sys_customer.id','=','account.id')
            ->offset($offset)->orderBy('sys_customer.created_at','ASC')->limit($pageSize)
            ->get($str)->toArray();
        
        $result = array();
        $result['count'] = $count;
        $result['data'] = $customerData;
        return $result;    
    }
    /*
     * 某客户及其下级客户数据
     */
    public function  returnCustomerAndSubData($str,$customerId,$where,$page,$pageSize){
        
        $idData = $this->getCustomerData($customerId);
        if(empty($idData)){
            throw new CommonException('102003');//此客户不存在
        }
        //获取所有二级三级子客户ID
        $aData = array();
        $allChildId = $this->getAllChildID($aData,$customerId);
        $allChildId[]['id'] = $customerId;//加上它本身的客户ID
        
        $allChildData = $this->getAllChildData($allChildId,$where,$str);//获取所有客户信息
        $count = count($allChildData);//总条数
        $start=($page-1)*$pageSize;//偏移量，当前页-1乘以每页显示条数
        $customerData = array_slice($allChildData,$start,$pageSize);
        $result = array();
        $result['count'] = $count;
        $result['data'] = $customerData;
        return $result;
    }
    /*
     * 创建客户页面显示内容
     */
    public function createPage(){
        $result['level'] = getEnums(config("info.level"));
        $result['customer_type'] = getEnums(config("info.customer_type"));
        $result['renewal_way'] = getEnums(config("info.renewal_way"));
        $result['company_type'] = getEnums(config("info.company_type"));
        $result['source_type'] = getEnums(config("info.source_type"));
        $result['area_type'] = getEnums(config("info.area_type"));
        $result['industry_type'] = getEnums(config("info.industry_type"));
        $result['customer_level'] = getEnums(config("info.customer_level"));
        $result['company_size'] = getEnums(config("info.company_size"));
        return $result;
        
    }
    /*
     * 新建客户时获取其上级公司名称和ID
     */
    public function getParentInfo($loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $where = array();
        if($loginUser['is_owner'] == 1){//网来员工
            $where= ['parent_id'=>'0','status'=>0];
            
        }else{
            $where= ['id'=>$loginUser['customer_id']];
        }
        $parentData = $this->where($where)->first(['id','customer_name']);
        return $parentData;
    }
    /*
     * 新建客户
     */
    public function add($data,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        //echo $loginUser['is_owner'];exit;
        if($loginUser['is_owner'] == 0){
            $loginCustomerData = $this->getCustomerData($loginUser['customer_id']);
            if(empty($loginCustomerData)){
                throw new CommonException('102003');//此客户不存在
            }
            if($loginCustomerData->level>2){
                throw new CommonException('102023');//您无创建客户权限
            }
        }
        
        $customerId = getUuid();
        $userId = getUuid();
        $code = $this->getCustomerCode();
        DB::beginTransaction();
        $resCusomer = $this->addCustomer($data, $loginUser, $customerId, $code);
        $resContact = $this->addContact($data, $loginUser,$customerId);
        $resUser = $this->addUser($loginUser,$userId,$customerId, $code,$data['customerName']);
        $resRoleUser = $this->addRoleUser($userId, $loginUser);
        $resAccount = $this->addCustomerAccount($customerId, $loginUser);
        $resCM = $this->addCustomerMonth($customerId, $loginUser,$code,$data['customerName']);
        if($resCusomer == TRUE && $resContact == TRUE && $resUser != "" && $resRoleUser == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
            throw new CommonException('102002');
        }
        return $resUser;
    }
    
    /*
     * 新增客户表
     */
    public function addCustomer($data,$loginUser,$customerId,$code){
        
        $parentData = $this->getCustomerData($data['parentId']);
        $cusomerModel = new Customer();
        $cusomerModel->id = $customerId;
        $cusomerModel->customer_name =$data['customerName'];
        $cusomerModel->customer_code = $code;
        $cusomerModel->customer_abbr = $data['customerAbbr'];
        $cusomerModel->city_code = $data['cityCode'];
        $cusomerModel->city_name = $this->getCityName($data['cityCode']);
        if($loginUser['is_owner'] == 1){//网来员工
            if(empty($data['accountManagerId'])){
                throw new CommonException('102024');//客户经理为空
            }
            $cusomerModel->account_manager_id = $data['accountManagerId'];//客户经理ID
            $cusomerModel->account_manager_name = $this->getRealName($data['accountManagerId']);
        }else{//客户
            $cusomerModel->account_manager_id = $parentData->account_manager_id;
            $cusomerModel->account_manager_name = $parentData->account_manager_name;
        }
        $cusomerModel->parent_id = $data['parentId'];//上级客户ID
        
        $cusomerModel->parent_customer_name = $parentData->customer_name;//上级客户名称 
        
        $cusomerModel->customer_type = $data['customerType'];
        $cusomerModel->renewal_way = $data['renewalWay'];
        $cusomerModel->company_address = $data['companyAddress'];
        if(isset($data['companyPhone']) && !empty($data['companyPhone'])){
            $cusomerModel->company_phone = $data['companyPhone'];
        }
        if(isset($data['companyType']) && !empty($data['companyType'])){
            $cusomerModel->company_type = $data['companyType'];
        }
        if(isset($data['legalPerson']) && !empty($data['legalPerson'])){
            $cusomerModel->legal_person = $data['legalPerson'];
        }
        if(isset($data['sourceType']) && !empty($data['sourceType'])){
            $cusomerModel->source_type = $data['sourceType'];
        }
        if(isset($data['companySize']) && !empty($data['companySize'])){
            $cusomerModel->company_size = $data['companySize'];
        }
        if(isset($data['industryType']) && !empty($data['industryType'])){
            $cusomerModel->industry_type = $data['industryType'];
        }
        if(isset($data['businessType']) && !empty($data['businessType'])){
            $cusomerModel->business_type = $data['businessType'];
        }
        if(isset($data['companyWebsite']) && !empty($data['companyWebsite'])){
            $cusomerModel->company_website = $data['companyWebsite'];
        }
        if(isset($data['companyEmail']) && !empty($data['companyEmail'])){
            $cusomerModel->company_email = $data['companyEmail'];
        }
        if(isset($data['companyFax']) && !empty($data['companyFax'])){
            $cusomerModel->company_fax = $data['companyFax'];
        }
        if(isset($data['areaType']) && !empty($data['areaType'])){
            $cusomerModel->area_type = $data['areaType'];
        }
        if(isset($data['customerLevel']) && !empty($data['customerLevel'])){
            $cusomerModel->customer_level = $data['customerLevel'];
        }
        
        $cusomerModel->level = (int)$parentData->level+1;//几级客户
        $cusomerModel->cooperation_time = date("Y-m-d H:i:s",time());
        $cusomerModel->create_user_id = $loginUser['id'];
        $cusomerModel->create_user_name = $loginUser['real_name'];
        $res = $cusomerModel->save();

        //新增用户账户
        $customerAccountModel = new CustomerAccountModel;
        $customerAccountModel->balance_amount = 0;
        $customerAccountModel->id = $cusomerModel->id;
        $customerAccountModel->save();

        return $res;
    }
    /*
     * 新增用户表
     */
    public function addUser($loginUser,$userId,$customerId,$code,$customerName){
        $pwd = random_str();
        $userModel = new User();
        $userModel->id = $userId;
        $userModel->user_name = "admin@".$code;
        $userModel->user_pwd = md5($pwd.config("info.SALT"));
        $userModel->real_name = $customerName;
        $userModel->customer_id = $customerId;
        $userModel->create_user = $loginUser['id'];
        $userModel->is_owner = 0;
        $res = $userModel->save();
        $result = array();
        if($res > 0){
            $result['user_name'] = "admin@".$code;
            $result['user_pwd'] = $pwd;
        }
        return $result;
    }
    /*
     * 新增用户角色
     */
    public function addRoleUser($userId,$loginUser){
        if($loginUser['is_owner'] == 1){//网来员工创建一级客户
            $roleId = config('info.role_first_customer_id');
        }else{
            $levelData = $this->getCustomerData($loginUser['customer_id']);
            if(empty($levelData)){
                
                throw new CommonException('102003');//客户不存在
            }
            //客户创建时角色都是行业卡二级代理商
            $roleId = config('info.role_second_customer_id');
        }
        $roleUserModel = new RoleUser();
        $roleUserModel->id = getUuid();
        $roleUserModel->role_id = $roleId;
        $roleUserModel->user_id = $userId;
        $roleUserModel->create_user_id = $loginUser['id'];
        $roleUserModel->create_user_name = $loginUser['real_name'];
        $res = $roleUserModel->save();
        return $res;
    }
    /*
     * 新增客户联系人表
     */
    public function addContact($data,$loginUser,$customerId,$type='customer'){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $contactModel = new CustomerContact();
        
        $contactModel->id = getUuid();
        $contactModel->customer_id = $customerId;
        $contactModel->contact_name = $data['contactName'];
        $contactModel->contact_sex = (int)$data['contactSex'];
        $contactModel->contact_moible = $data['contactMoible'];
        if(isset($data['contactEmail']) && !empty($data['contactEmail'])){
            $contactModel->contact_email = $data['contactEmail'];
        }
        if(isset($data['contactsQq']) && !empty($data['contactsQq'])){
            $contactModel->contacts_qq = $data['contactsQq'];
        }
        if(isset($data['contactsWeixin']) && !empty($data['contactsWeixin'])){
            $contactModel->contacts_weixin = $data['contactsWeixin'];
        }
        if(isset($data['responseWork']) && !empty($data['responseWork'])){
            $contactModel->response_work = $data['responseWork'];
        }
        if(isset($data['require']) && !empty($data['require'])){
            $contactModel->require = $data['require'];
        }
        if($type == 'customer'){
            $contactModel->is_main = 1;
        }
        $contactModel->create_user_id = $loginUser['id'];
        $contactModel->create_user_name = $loginUser['real_name'];
        $res = $contactModel->save();
        return $res;
    }
    /*
     * 新增客户账户
     * 账户表主键ID = 客户ID
     */
    public function addCustomerAccount($customerId,$loginUser){
        $idData = CustomerAccountModel::where('id',$customerId)->first();
        $res = TRUE;
        if(empty($idData)){
            $data = [];
            $data['id'] = $customerId;
            $data['create_user_id'] = $loginUser['id'];
            $data['create_user_name'] = $loginUser['real_name'];
            $data['created_at'] = date('Y-m-d H:i:s',time());
            $res = CustomerAccountModel::insert($data);
        }
        return $res;
    }
    /*
     * 新增客户续费月份
     * 续费月份表主键ID = 客户ID
     */
    public function addCustomerMonth($customerId,$loginUser,$customerCode,$customerName){
        $idData = TSysCustomerMonthModel::where('id',$customerId)->first();
        $res = TRUE;
        if(empty($idData)){
            $data = [];
            $data['id'] = getUuid();
            $data['customer_id'] = $customerId;
            $data['customer_code'] = $customerCode;
            $data['customer_name'] = $customerName;
            $data['create_user_id'] = $loginUser['id'];
            $data['create_user_name'] = $loginUser['real_name'];
            $data['created_at'] = date('Y-m-d H:i:s',time());
            $res = TSysCustomerMonthModel::insert($data);
        }
        return $res;
    }
    /*
     * 编辑客户
     */
    public function updates($input,$id,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        DB::beginTransaction();
        $resCusomer = $this->updateCustomer($input, $id,$loginUser);
        $resContact = $this->updateContact($input, $id,'customer');
        if($resCusomer == 1 && $resContact == 1 ){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
        
        
    }
    /*
     * 编辑客户联系人表
     */
    public function updateCustomer($input,$id,$loginUser){
        
        //$data['customer_name'] =$input['customerName'];
        //$data['customer_abbr'] = $input['customerAbbr'];
        $data['city_code'] = $input['cityCode'];
        $data['city_name'] = $this->getCityName($input['cityCode']);
        if($loginUser['is_owner'] == 1){//网来员工
            $data['account_manager_id'] = $input['accountManagerId'];//客户经理ID
            $data['account_manager_name'] = $this->getRealName($input['accountManagerId']);
        }
        $data['customer_type'] = $input['customerType'];
        $data['renewal_way'] = $input['renewalWay'];
        $data['company_address'] = $input['companyAddress'];
        if(isset($input['companyPhone']) && !empty($input['companyPhone'])){
            $data['company_phone'] = $input['companyPhone'];
        }
        if(isset($input['companyType']) && !empty($input['companyType'])){
            $data['company_type'] = $input['companyType'];
        }
        if(isset($input['legalPerson']) && !empty($input['legalPerson'])){
            $data['legal_person'] = $input['legalPerson'];
        }
        if(isset($input['sourceType']) && !empty($input['sourceType'])){
            $data['source_type'] = $input['sourceType'];
        }
        if(isset($input['companySize']) && !empty($input['companySize'])){
            $data['company_size'] = $input['companySize'];
        }
        if(isset($input['industryType']) && !empty($input['industryType'])){
            $data['industry_type'] = $input['industryType'];
        }
        if(isset($input['businessType']) && !empty($input['businessType'])){
            $data['business_type'] = $input['businessType'];
        }
        if(isset($input['companyWebsite']) && !empty($input['companyWebsite'])){
            $data['company_website'] = $input['companyWebsite'];
        }
        if(isset($input['companyEmail']) && !empty($input['companyEmail'])){
            $data['company_email'] = $input['companyEmail'];
        }
        if(isset($input['companyFax']) && !empty($input['companyFax'])){
            $data['company_fax'] = $input['companyFax'];
        }
        if(isset($input['areaType']) && !empty($input['areaType'])){
            $data['area_type'] = $input['areaType'];
        }
        if(isset($input['customerLevel']) && !empty($input['customerLevel'])){
            $data['customer_level'] = $input['customerLevel'];
        }
        $res = $this->where('id',$id)->update($data);
        return $res;
    }
    /*
     * 编辑客户联系人表
     */
    public function updateContact($input,$id,$type='customer'){
        $data['contact_name'] = $input['contactName'];
        $data['contact_sex'] = $input['contactSex'];
        $data['contact_moible'] = $input['contactMoible'];
        if(isset($input['contactEmail']) && !empty($input['contactEmail'])){
            $data['contact_email'] = $input['contactEmail'];
        }
        if(isset($input['contactsQq']) && !empty($input['contactsQq'])){
            $data['contacts_qq'] = $input['contactsQq'];
        }
        if(isset($input['contactsWeixin']) && !empty($input['contactsWeixin'])){
            $data['contacts_weixin'] = $input['contactsWeixin'];
        }
        if(isset($input['responseWork']) && !empty($input['responseWork'])){
            $data['response_work'] = $input['responseWork'];
        }
        if(isset($input['require']) && !empty($input['require'])){
            $data['require'] = $input['require'];
        }
        if($type == 'customer'){
            $res = CustomerContact::where([['customer_id','=',$id],['is_main','=',1],['status','=',0]])->update($data);
        }else{
            $res = CustomerContact::where('id',$id)->update($data);
        }
        return $res;
    }
    /*
     * 删除客户
     * 1、该客户下有下级客户不能删除
     * 2、该客户有卡片不能删除
     * 3、删除客户时，客户下用户及客户下联系人也要删除
     */
    public function destroyCustomer($id){
        $customerData = $this->where('id',$id)->first(['id']);
        if(empty($customerData)){
            throw new CommonException('102003');//该客户不存在
        }
        $childCustomerCount = $this->where(['parent_id'=>$id,'status'=>0])->count('id');
        if($childCustomerCount > 0){
            throw new CommonException('102014');//该客户下面有二级客户不能删除
        }
        $customerCardCount = (new CardModel)->getCardCountByCustomerId($id);
        if($customerCardCount > 0){
            throw new CommonException('102016');//该客户下有卡片，不能删除
        }
        
        $time = date('Y-m-d H:i:s',time());
        $customerUserCount = User::where(['customer_id'=>$id,'is_owner'=>0,'is_delete'=>0])->count('id');
        $customerContectCount = CustomerContact::where(['customer_id'=>$id,'status'=>0])->count();
        DB::beginTransaction();
        $resUser = TRUE;
        $resContect = TRUE;
        $res = $this->where('id',$id)->update(['status'=>1,'deleted_at'=>$time]);
        if($customerUserCount > 0){
            $resUser = User::where(['customer_id'=>$id,'is_owner'=>0,'is_delete'=>0])->update(['is_delete'=>1]);
            if($resUser != $customerUserCount){
                $resUser = FALSE;
            }
        }
        if($customerContectCount > 0){
            $resContect = CustomerContact::where(['customer_id'=>$id,'status'=>0])->update(['status'=>1]);
            if($resContect != $customerContectCount){
                $resContect = FALSE;
            }
        }
        if($res == TRUE && $resUser == TRUE && $resContect == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
        }
        return $res;
    }
    /*
     * 变更经理
     */
    public function changeManager($input,$customerId){
        if(!isset($input['managerId']) && empty($input['managerId'])){
            throw new CommonException('102006');
        }
        
        $data['account_manager_id'] = $input['managerId'];
        $data['account_manager_name'] = $this->getRealName($input['managerId']);
        $res = $this->where('id',$customerId)->update($data);
        return $res;
   
    }
    /*
     * 根据ID查出客户基本信息
     */
    public function getCustomerData($id){
        $data = $this->where(['id'=>$id,'status'=>0])
                ->first(['id','level','customer_code','customer_name','account_manager_id','account_manager_name',
                    'renewal_way']);
        return $data;
    }
    
    
    
    /*
     * 获取某客户下的所有子客户(包括二级三级等)
     */
    public function getAllChildData($allChildId,$where,$str){
        $childData = $this->leftJoin('sys_customer_account as account','account.id','=','sys_customer.id')
                ->where($where)->whereIn('sys_customer.id',$allChildId)->orderBy('sys_customer.created_at','ASC')
                ->get($str);
        return $childData->toArray();
    }
    public function getAllChild(&$data,$id,$where,$str){
        $childData = $this->leftJoin('sys_customer_account as account','account.id','=','sys_customer.id')
                ->where($where)->where('sys_customer.parent_id1',$id)->orderBy('sys_customer.created_at','ASC')
                ->get(['sys_customer.id','sys_customer.customer_code','sys_customer.customer_name',
                    'sys_customer.level','sys_customer.account_manager_id','sys_customer.account_manager_name',
                    'sys_customer.cooperation_time','sys_customer.city_name','sys_customer.industry_type',
                    'sys_customer.parent_customer_name','sys_customer.customer_type','account.balance_amount as customer_balance']);
        
        if(empty($childData)){
            return $childData;
        }
        foreach($childData->toArray() as $value){
            $data[] = $value;
            $this->getAllChild($data,$value['id'],$where,$str);
        }
	return $data;
    }
/*
     * 获取某客户下的所有子客户ID(包括二级三级等)
     */
    public function getAllChildID(&$data,$id){
        $childData = $this->where(['parent_id'=>$id,'status'=>0])
                ->get(['id']);
        if(empty($childData)){
            return $childData;
        }
        foreach($childData->toArray() as $value){
            $data[] = $value;
            $this->getAllChildID($data,$value['id']);
        }
	return $data;
    }    
    /*
     * 获取某个客户详细信息
     */
    public function getInfo($id){
        $customerData = $this->where(['id'=>$id,'status'=>0])
                ->first(['id','customer_name','customer_code','customer_abbr','city_name',
                    'city_code','company_type','legal_person','source_type','company_size',
                    'business_type','industry_type','company_phone','company_email','company_fax',
                    'area_type','customer_level','company_website','customer_type','company_address',
                    'account_manager_name','cooperation_time','level','parent_customer_name',
                    'renewal_way','account_manager_id']);
        if(empty($customerData)){
            throw new CommonException('102003');
        }
        $contectData = CustomerContact::where(['customer_id'=>$id,'is_main'=>1,'status'=>0])
                ->first(['contact_name','contact_sex','contact_moible','contact_email','contacts_qq',
                    'contacts_weixin','response_work','require']);
        $customerData['customer_type'] = (string)$customerData['customer_type'];
        $customerData['level'] = (string)$customerData['level'];
        $customerData['renewal_way'] = (string)$customerData['renewal_way'];
        if(empty($contectData)){
            $customerData->contact_name = '';
            $customerData->contact_sex = '';
            $customerData->contact_moible = '';
            $customerData->contact_email = '';
            $customerData->contacts_qq = '';
            $customerData->contacts_weixin = '';
            $customerData->response_work = '';
            $customerData->require = '';
        }else{
            $customerData->contact_name = $contectData->contact_name;
            $customerData->contact_sex = $contectData->contact_sex;
            $customerData->contact_moible = $contectData->contact_moible;
            $customerData->contact_email = $contectData->contact_email;
            $customerData->contacts_qq = $contectData->contacts_qq;
            $customerData->contacts_weixin = $contectData->contacts_weixin;
            $customerData->response_work = $contectData->response_work;
            $customerData->require = $contectData->require;
        }
        return $customerData;
    }
    /*
     * 根据客户ID获取客户名称
     */
   public function getCustomerName($id){
       $name = $this->where(['id'=>$id,'status'=>0])->first(['customer_name']);
       if(empty($name)){
           throw new CommonException('102003');
       }
       return $name->customer_name;
   }
    
    /*
     * 获取编码
     */
    public function getCustomerCode(){
        $data = $this->orderBy('customer_code','DESC')->first(['customer_code']);
        if(empty($data)){
            $code = "100001";
        }else{
            $code = (int)$data->customer_code+1;
        }
        return $code;
    }
    /*
     * 根据城市编码获取城市名称
     */
    public function getCityName($id){
        $data = TSysAreaModel::where('id',$id)->first(['area_name']);
        if(empty($data)){
            return "";
        }else{
            return $data->area_name;
        }
    }
    /*
     * 根据用户ID获取用户真实姓名
     */
    public function getRealName($id){
        $data = User::where('id',$id)->first(['real_name']);
        if(empty($data)){
            return "";
        }else{
            return $data->real_name;
        }
    }
    /*
     * 获取某客户仅下一级子客户
     */
    public function getOneChild($loginUser,$str){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        
        if(empty($loginUser['customer_id'])){
            return "";
        }
        $customerId = $loginUser['customer_id'];
        $customerData = $this->where('id',$customerId)->first(['level']);
        if(empty($customerData)){
            return "";
        }
        $childLevel = (int)$customerData->level+1;
        $childData = $this->where(['parent_id'=>$customerId,'level'=>$childLevel,'status'=>0])->get($str);
        if(empty($childData)){
            return $childData;
        }    
	return $childData->toArray();
    }
    
    /*
     * 获取所有一级客户列表
     */
    public function getFirstCustomer(){
        $data = $this->where('parent_id','62717a8fa61a5b9d8f38353f165a6a49')
                ->get(['id','customer_name','customer_code']);
        if(!$data->isEmpty()){
            foreach($data as $value){
                $value->fullName = "({$value->customer_code}){$value->customer_name}";
            }
        }
        return $data;
    }
    
    /*
     * 获取where条件
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['customerCode']) && !empty($input['customerCode'])){
            $where[] = ['sys_customer.customer_code', 'like', '%'.$input['customerCode'].'%'];
        }
        if(isset($input['customerName']) && !empty($input['customerName'])){
            $where[] = ['sys_customer.customer_name', 'like', '%'.$input['customerName'].'%'];
        }
        if(isset($input['level']) && !empty($input['level'])){
            $where[] = ['sys_customer.level', '=', $input['level']];
        }
        if(isset($input['accountManagerName']) && !empty($input['accountManagerName'])){
            $where[] = ['sys_customer.account_manager_name', 'like', '%'.$input['accountManagerName'].'%'];
        }
        if(isset($input['cityName']) && !empty($input['cityName'])){
            $where[] = ['sys_customer.city_name', 'like', '%'.$input['cityName'].'%'];
        }
        //行业分类
        if(isset($input['industryType']) && !empty($input['industryType'])){
            $where[] = ['sys_customer.industry_type', '=',$input['industryType']];
        }
        //客户类型
        if(isset($input['customerType']) && !empty($input['customerType'])){
            $where[] = ['sys_customer.customer_type', '=', $input['customerType']];
        }
        //合作日期起始时间
        if(isset($input['startTime']) && !empty($input['startTime'])){
            $where[] = ['sys_customer.cooperation_time', '>=', $input['startTime']];
        }
        //合作日期结束时间
        if(isset($input['endTime']) && !empty($input['endTime'])){
            $where[] = ['sys_customer.cooperation_time', '<=', $input['endTime']];
        }
        return $where;
    }
    /*
     * 根据用户ID查询客户信息或者角色信息
     */
    public function getCustomerOrRoleByUserId($loginUser){
        $result = [];
        $sellRole = 0;//是否是销售
        if($loginUser['is_owner'] == 0){//客户
            $customerData = $this->getCustomerData($loginUser['customer_id']);
            if(empty($customerData)){
                throw new CommonException('102003');
            }
            $result['customerData'] = $customerData;
        }else{//网来员工
            $infoSellRole = config('info.role_xiaoshou_id');
            $isNoSellRole = RoleUser::where(['role_id'=>$infoSellRole,'user_id'=>$this->loginUser['id']])->first(['id']);
            if(!empty($isNoSellRole)){
                $sellRole = 1;
            }
            $result['customerData'] = '';
        }
        $result['sellRole'] = $sellRole;
        return $result;
    }
    
    
    // 一级客户树形结构
    public function getCustomerTree($user)
    {
        if(empty($user)){
            throw new CommonException('300001');
        }
        $back = array();
        $data = array();
        if($user['is_owner'] == 1){
            $customerObj = $this->where(['customer_code'=>'100000','status'=>0])->first(['id','customer_name','customer_code']);
            $data['id'] = $customerObj->id;
            $data['customer_name'] = $customerObj->customer_name;
            $data['customer_code'] = $customerObj->customer_code;
            $where [] =['parent_id','=',$customerObj->id];
            $where [] =['status','=',0];
            //判断登录用户是否是销售人员（是则只显示属于自己的客户）
            $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$user['id']])->first(['id']);
            
            if(!empty($isNoMarketRole)){
                $where[] = ['account_manager_id','=',$user['id']];
            }
            $data['children'] = $this->where($where)->get(['id','customer_name','customer_code','parent_id'])->toArray();
            $back[] = $data;
        }else{
            $customerObj = $this->where(['id'=>$user['customer_id'],'status'=>0])->first(['id','customer_name','customer_code']);
            $data['id'] = $customerObj->id;
            $data['customer_name'] = $customerObj->customer_name;
            $data['customer_code'] = $customerObj->customer_code;
            $data['children'] = $this->where(['parent_id'=>$customerObj->id,'status'=>0])->get(['id','customer_name','customer_code','parent_id'])->toArray();
            $back[] = $data;
        }
        return $back;
    }
    
    //子客户树形结构
    public function getSubCustomerTree($id,$loginUser)
    {
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
         //判断登录用户是否是销售人员（是则只显示属于自己的客户）
        $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$loginUser['id']])->first(['id']);
        $where [] =['parent_id','=',$id];
        $where [] =['status','=',0];
        if(!empty($isNoMarketRole)){
            $where[] = ['account_manager_id','=',$loginUser['id']];
        }    
        $data = $this->where($where)->get(['id','customer_name','customer_code','parent_id'])->toArray();
        return $newData = getTree($data,$id);
    }
    /*
     * 设置客户经理
     * 把原客户经理的客户换成新客户经理的客户
     */
    public function setManager($input){
        if($input['oldManageId'] == $input['newManageId']){
            throw new CommonException('102021');//原客户经理和新客户经理不能相同
        }
        $oldManageData = User::where('id',$input['oldManageId'])->first(['id']);
        if(empty($oldManageData)){
            throw new CommonException('102017');//原客户经理不存在
        }
        $newManageData = User::where('id',$input['newManageId'])->first(['id','real_name']);
       
        if(empty($newManageData)){
            throw new CommonException('102018');//新客户经理不存在
        }
        $oldManageCustomerCount = $this
                ->where(['account_manager_id'=>$input['oldManageId'],'status'=>0])
                ->count('id');
        if($oldManageCustomerCount <= 0){
            throw new CommonException('102019');//原客户经理下没有客户
        }
        DB::beginTransaction();
        $updateManageRes = $this->where(['account_manager_id'=>$input['oldManageId'],'status'=>0])
                ->update(['account_manager_id'=>$input['newManageId'],
                    'account_manager_name'=>$newManageData->real_name]);
        if($oldManageCustomerCount == $updateManageRes){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
    }
   
    /**
     * 判断客户级别
     * @param $id 客户id
     * @param $level 客户级别
     * 
     */
    function checkLevel($id, $level) {
        $data = $this->getCustomerData($id);
        return $data->level == $level ? true : false;
    }
}
