<?php

namespace App\Http\Controllers\Operation;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Models\Admin\User;
use App\Http\Models\Operation\Package;
use App\Exceptions\CommonException;

class PackageController extends Controller
{
    protected $rules = [
            'packageType'=>'required',
            'packageName'=>'required',
            'settlementType'=>'required',
            'timeLength'=>'required',
            'timeUnit'=>'required',
            'price'=>'required|decimal',
            'minSalePrice'=>'required|decimal',
            'consumption'=>'required',
            'feesType'=>'required',
            'isInternationalPack'=>'required',
            'describe'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'decimal'=>':attribute必须为整数或小数',
        ];
    
    /*
     * get.api/Operation/package
     * 套餐列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new Package)->getPackages($request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /* 
     * 
     * 创建套餐的页面数据
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
     * post.api/Operation/package
     * 新建套餐
     */
    public function store(Request $request)
    {
        try{
            $input = $request->all();
            $validate = $this->validateStr($request->all(),'add',$input['packageType']);
            if($validate != 1){
                return $validate;
            }
            $result = (new Package)->addPackage($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('103001');
            } 
        } catch (Exception $ex) {
            throw new CommonException('103001');
        }
        
    }
    /**
     * get.api/Operation/package/{id}
     * 显示指定套餐
     */
    public function show($id){
        try{
            $result = (new Package)->getPackageInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * put.api/Operation/package
     * 编辑套餐
     */
    public function update(Request $request, $id){
        
        try{
            $input = $request->all();
            $validate = $this->validateStr($request->all(),'edit',$input['packageType']);
            if($validate != 1){
                return $validate;
            }

            $result = (new Package)->updatePackage($request->all(),$id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('103002');
            }
        } catch (Exception $ex) {
            throw new CommonException('103002');
        }
        
    }
    /*
     * delete.api/Operation/package
     * 设置用户失效
     */
    public function destroy($id){
        try{
            $result = (new Package)->destroys($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('103003');
            }
        } catch (Exception $ex) {
            throw new CommonException('103003');
        }
        
    }
   
    
    /*
     * 验证器
     */
    public function validateStr($input,$handle,$type){
        if($handle == 'add'){
            //$this->rules['packageType'] = 'required';
        }
        if($type == 'FLOW' || $type == 'VOICE'){
                $this->rules['maxPrice'] = 'required|decimal';
                $this->rules['minPrice'] = 'required|decimal';
        }
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            'packageName'=>'套餐名称',
            'settlementType'=>'结算类型',
            'timeLength'=>'时长',
            'timeUnit'=>'时间单位',
            'price'=>'价格',
            'minSalePrice'=>'最低销售价',
            'consumption'=>'短信/流量/语音',
            'feesType'=>'计费类型',
            'isInternationalPack'=>'是否国际套餐',
            'describe'=>'套餐描述',
            'packageType'=>'套餐类型',
            'maxPrice'=>'价格上限',
            'minPrice'=>'价格下限',
        ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }
    

}
