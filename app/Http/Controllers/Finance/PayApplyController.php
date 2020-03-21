<?php

namespace App\Http\Controllers\Finance;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Customer\Customer;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\CommonException;
use App\Http\Models\Finance\PayApplyModel;

class PayApplyController extends Controller
{
    protected $rules = [
            'payType'=>'required',
            'payName'=>'required',
            'payTime'=>'required',
            'payAmount'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    /*
     * post.api/Finance/payApply
     * 申请单列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new PayApplyModel)->getPayApplys($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    /* 
     * post.api/Finance/payApply
     * 新建充值申请单
     */
    public function store(Request $request, PayApplyModel $PayApplyModel)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $result = $PayApplyModel->addPayApply($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('104001');
            }
        } catch (Exception $ex) {
            throw new CommonException('104001');
        }
        
    }
    /**
     * get.api/Finance/payApply{$id}
     * 显示指定充值申请单信息
     */
    public function show($id){
        try{
            $result = (new PayApplyModel)->getInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * put.api/Finance/payApply{$id}
     * 编辑申请单
     */
    public function update(Request $request, $id){
        try{
            $validate = $this->validateStr($request->all(),'edit',$id);
            if($validate != 1){
                return $validate;
            }
            $result = (new PayApplyModel)->updatePayApply($request->all(),$id,$this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('104002');
            } 
        } catch (Exception $ex) {
            throw new CommonException('104002');
        }
        
    }
    /*
     * delete.api/Finance/payApply{$id}
     * 删除申请单
     */
    public function destroy($id){
        try{
            $result = (new PayApplyModel)->destroyPayApply($id,$this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('104005');
            }
        } catch (Exception $ex) {
            throw new CommonException('104005');
        }
        
        
    }
   /*
     * put.api/Finance/operatePayApply{$id}
     * 确认申请单
     */
    public function operatePayApply(Request $request,$id){
        try{
            if(empty($id)){
                throw new CommonException('104007');
            }
            $result = (new PayApplyModel)->operatePayApply($request->all(),$id);
            if($result > 0){
                return $this->success([]);
            }else{
                
                throw new CommonException('101009');
            }
        } catch (Exception $ex) {
            
            throw new CommonException('101009');
        }
        
    }
    
    /*
     * 验证器
     */
    public function validateStr($input,$type,$id = 0){
        
        $validator = Validator::make($input,$this->rules,$this->messages,[
            ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        
        return 1;
    }
    
    

}
