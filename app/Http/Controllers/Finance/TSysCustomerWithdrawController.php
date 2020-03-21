<?php

namespace App\Http\Controllers\Finance;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\Validator;
use App\Http\Models\Finance\TSysCustomerWithdrawModel;

class TSysCustomerWithdrawController extends Controller
{
    protected $rules = [
            'amount'=>'required',
            'transactionType'=>'required',
            'accountBank'=>'required',
            'accountName'=>'required',
            'accountNumber'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
    /*
     * 
     * 我的提现列表
     */
    public function myWithdraw(Request $request)
    {
        try{
            $result = (new TSysCustomerWithdrawModel)->getList($request->all(), $this->user,1);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /*
     * 
     * 申请提现列表
     */
    public function applyWithdraw(Request $request)
    {
        try{
            $result = (new TSysCustomerWithdrawModel)->getList($request->all(), $this->user,2);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /* 
     * 新建提现申请单
     */
    public function addMyWithdraw(Request $request, TSysCustomerWithdrawModel $TSysCustomerWithdrawModel)
    {
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = $TSysCustomerWithdrawModel->add($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('104101');
            }
        } catch (Exception $ex) {
            throw new CommonException('104101');
        }
        
    }
    /**
     * 显示指定提现申请单信息
     */
    public function getInfo($code){
        try{
            $result = (new TSysCustomerWithdrawModel)->getInfo($code);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /*
     * 修改提现申请单
     */
    public function updateMyWithdraw(Request $request, TSysCustomerWithdrawModel $TSysCustomerWithdrawModel,$code)
    {
        try{
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = $TSysCustomerWithdrawModel->updateMyWithdraw($code,$request->all(), $this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('104108');
            }
        } catch (Exception $ex) {
            throw new CommonException('104108');
        }
        
    }
    /*
     * 提现申请:操作
     * 
     */
    public function operateWithdraw(Request $request, TSysCustomerWithdrawModel $TSysCustomerWithdrawModel){
        try{
            $this->rules = [
                'code'=>'required',
                'status'=>'required'
            ];
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = $TSysCustomerWithdrawModel->operateWithdraw($request->all(), $this->user);
            if($result == 1){
                return $this->success([]);
            }else{
                throw new CommonException('104106');
            }
        } catch (Exception $ex) {
            throw new CommonException('104106');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        
        $validator = Validator::make($input,$this->rules,$this->messages,[
                //'customerId'=>'客户',
                'amount'=>'提现金额',
                'transactionType'=>'交易类型',
                'accountBank'=>'开户银行',
                'accountName'=>'开户姓名',
                'accountNumber'=>'提现账号',
                'code'=>'提现申请单号',
                'status'=>'申请单操作',
            ]);
        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        
        return 1;
    }
    
    

}
