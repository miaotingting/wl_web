<?php

namespace App\Http\Controllers\Impl;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Impl\TSysImplModel;

class TSysImplController extends Controller
{
    protected $rules = [
            'name'=>'required',
            'method'=>'required',
            'requestWay'=>'required',
            'url'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    
    /*
     * get.api/Impl/implConfig
     * 接口配置列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new TSysImplModel)->implList($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    /* 
     * post.api/Impl/implConfig
     * 新建接口
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TSysImplModel)->addImpl($request->all(), $this->user);
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
     * put.api/Impl/implConfig/{id}
     * 编辑接口
     */
    public function update(Request $request, $id){
        
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TSysImplModel)->updateImpl($request->all(),$id);
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
     * delete.api/Impl/implConfig/{id}
     * 删除接口
     */
    public function destroy($id){
        try{
            $result = (new TSysImplModel)->destroyImpl($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('110002');
            }
        } catch (Exception $ex) {
            throw new CommonException('110002');
        }
        
    }
    /**
     * get.api/Impl/implConfig/{id}
     * 查看接口详情
     */
    public function show($id){
        try{
            $result = (new TSysImplModel)->getImplInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 接口文档(客户显示)
     */
    public function implDocument(){
        try{
            $result = (new TSysImplModel)->implDocument($this->user);
            if($result){
                return $this->success($result);
            }else{
                throw new CommonException('110002');
            }
        } catch (Exception $ex) {
            throw new CommonException('110002');
        }
    }
    /*
     * 下载接口文档
     */
    public function downloadImplDoc()
    {
        $path = 'template/impl/implDoc.zip';
        return response()->download(public_path($path));
    }   
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            'name'=>'接口名称',
            'method'=>'接口方法',
            'requestWay'=>'请求方式',
            'url'=>'接口地址',
            ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }

}



