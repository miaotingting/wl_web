<?php

namespace App\Http\Controllers\Customer;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Customer\CustomerContact;
use Illuminate\Support\Facades\Validator;
use App\Http\Models\Admin\TSysAreaModel;
use App\Exceptions\CommonException;

class CustomerController extends Controller
{
    protected $rules = [
            'customerName'=>'required|min:2|max:25',
            'customerAbbr'=>'required|min:2|max:25',
            'cityCode'=>'required',
            //'accountManagerId'=>'required',
            'customerType'=>'required',
            'renewalWay'=>'required',
            'companyAddress'=>'required',
            'contactName'=>'required',
            'contactSex'=>'required',
            'contactMoible'=>'required|mobile',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'min'=>':attribute 长度不符合要求',
            'max'=>':attribute 长度不符合要求',
            'mobile'=>':attribute 格式错误',
            'unique'=>'该:attribute已经被注册',
            'email'=>':attribute 格式错误',
        ];
    private $customer;

    function __construct(Request $request, Customer $customer)
    {
        parent::__construct($request);
        $this->customer = $customer;
    }
    
    function getCustomers(Request $request) {
        try{
            $search = [];
            if (array_has($request, 'search')) {
                $search = json_decode($request['search'], true);
            }
            $level = 1;
            if (array_has($request, 'level')) {
                $level = 2;
            }
            //查询所有客户
            $res = $this->customer->getCustomerFullNames($search, $this->user,$level);
            return $this->success($res);
        }catch(Exception $e) {

        }
    }
    /*
     * get.api/Customer/info
     * 客户列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new Customer)->getCustomers($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /* 
     * get.api/Customer/info/create
     * 创建用户的页面数据
     */
    public function create()
    {
        try{
            $result = (new Customer)->createPage();
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('102001');
        }
        
    }
    /* 
     * post.api/Customer/info
     * 新建客户
     */
    public function store(Request $request, Customer $customer)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $result = $customer->add($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('102002');
        }
        
    }
    /**
     * get.api/Customer/info/{$id}
     * 显示指定客户信息
     */
    public function show($id){
        try{
            
            $result = (new Customer)->getInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * put.api/Customer/info
     * 编辑客户
     */
    public function update(Request $request, $id){
        try{
            $validate = $this->validateStr($request->all(),'edit');
            if($validate != 1){
                return $validate;
            }
            $result = (new Customer)->updates($request->all(),$id, $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('102004');
            } 
        } catch (Exception $ex) {
            throw new CommonException('102004');
        }
        
    }
    /*
     * delete.api/Customer/info
     * 删除客户
     */
    public function destroy($id){
        try{
            $result = (new Customer)->destroyCustomer($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('102005');
            }
        } catch (Exception $ex) {
            throw new CommonException('102005');
        }
        
        
    }
   /*
     * put.api/Customer/changeManager
     * 变更经理
     */
    public function changeManager(Request $request,$id){
        try{
            if(empty($id)){
                throw new CommonException('102003');
            }
            $result = (new Customer)->changeManager($request->all(),$id);
            if($result > 0){
                return $this->success([]);
            }else{
                
                throw new CommonException('102007');
            }
        } catch (Exception $ex) {
            
            throw new CommonException('102007');
        }
        
    }
    /*
     * 查询所有省市
     */
    public function getCity(){
        try{
            $result = (new TSysAreaModel)->getAllCity();
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /*
     * 查询此客户的下一级客户
     */
    public function getOneChild(){
        try{
            
            $result = (new Customer)->getOneChild($this->user,['id','customer_name']);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input,$type){
        if($type == "add"){
            $this->rules['parentId'] = 'required';
        }
        if(!empty($input['contactEmail'])){
            $this->rules['contactEmail'] = 'email';
         }
        $validator = Validator::make($input,$this->rules,$this->messages,[
            'customerName'=>'客户名称',
            'customerAbbr'=>'客户简称',
            'cityCode'=>'所属省市',
            'accountManagerId'=>'客户经理',
            'customerType'=>'客户类型',
            'renewalWay'=>'续费方式',
            'companyAddress'=>'公司地址',
            'contactName'=>'联系人姓名',
            'contactSex'=>'联系人性别',
            'parentId'=>'上级公司',
            'contactEmail'=>'联系人邮箱',
            'contactMoible'=>'联系人手机号'
        ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        
        return 1;
    }
    
    /**
     * 网来客户树形结构
     * @param Request $request
     * @return void
     */
    public function customerTree(Request $request)
    {
        $customer = new Customer();
        $data = $customer->getCustomerTree($this->user); 
        return setTResult($data);
    }

    /**
     * 一级客户子客户树形结构
     * @param Request $request
     * @return void
     */
    public function subCustomerTree(Request $request)
    {
        if(!$request->has('id')){
            return setFResult('001','参数缺失！');
        }
        if(empty($request->get('id'))){
            return setFResult('001','参数缺失！');
        }
        $customer = new Customer();
        $data = $customer->getSubCustomerTree($request->get('id'), $this->user); 
        return setTResult($data);
    }
    /*
     * 新建客户时获取其上级公司名称和ID
     */
    public function getParentInfo(){
        try{
            $result = (new Customer)->getParentInfo($this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 设置经理
     * put.api/Customer/setManager
     */
    public function setManager(Request $request){
        try{
            $result = (new Customer)->setManager($request->all());
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('102020');
            }
        } catch (Exception $ex) {
            
            throw new CommonException('102020');
        }
    }
    /*
     * 获取所有一级客户列表
     */
    public function getFirstCustomer(){
        try{
            $result = (new Customer)->getFirstCustomer();
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }

}
