<?php

namespace App\Http\Controllers\Impl;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Impl\TSysImplDetailModel;

class TSysImplDetailController extends Controller
{
    protected $rules = [
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    
    /*
     * get.api/Impl/implDetailConfig
     * 请求参数列表
     */
    public function index(Request $request)
    {
        try{
            $this->rules['implId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TSysImplDetailModel)->implDetailList($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    /* 
     * post.api/Impl/implDetailConfig
     * 新建请求参数
     */
    public function store(Request $request)
    {
        try{
            $this->rules['paramName'] = 'required';
            $this->rules['type'] = 'required';
            $this->rules['isRequired'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TSysImplDetailModel)->addImplDetail($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('109017');
            } 
        } catch (Exception $ex) {
            throw new CommonException('109017');
        }
        
    }
    /**
     * get.api/Impl/implDetailConfig/{id}
     * 查看请求参数详情
     */
    public function show($id){
        try{
            $result = (new TSysImplDetailModel)->getImplDetailInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /**
     * put.api/Impl/implDetailConfig/{id}
     * 编辑请求参数
     */
    public function update(Request $request, $id){
        
        try{
            $this->rules['paramName'] = 'required';
            $this->rules['type'] = 'required';
            $this->rules['isRequired'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TSysImplDetailModel)->updateImplDetail($request->all(),$id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('110001');
            }
        } catch (Exception $ex) {
            throw new CommonException('110001');
        }
        
    }
    /*
     * delete.api/Impl/implDetailConfig/{id}
     * 删除请求参数
     */
    public function destroy($id){
        try{
            $result = (new TSysImplDetailModel)->destroyImplDetail($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('110002');
            }
        } catch (Exception $ex) {
            throw new CommonException('110002');
        }
        
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            'implId'=>'接口ID',
            'paramName'=>'参数名称',
            'type'=>'类型',
            'isRequired'=>'是否必填'
            ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }

}



