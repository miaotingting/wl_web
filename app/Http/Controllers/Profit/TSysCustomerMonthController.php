<?php

namespace App\Http\Controllers\Profit;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\CommonException;
use App\Http\Models\Profit\TSysCustomerMonthModel;
use Dotenv\Validator;
use Exception;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;

class TSysCustomerMonthController extends Controller
{
    protected $rules = [
            'customerId'=>'required',
            'minMonth'=>'required|integer|max:120',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'integer'=>':attribute月份数必须为整数',
            'max'=>':attribute月份数不能大于10年',
        ];
    
    /*
     * get.api/Profit/customerMonthIndex
     * 客户续费月份设置列表
     */
    public function customerMonthIndex(Request $request)
    {
        try{
            if($request->has('search') && !empty($request->get('search'))){
                $search = json_decode($request->get('search'),TRUE);
            }else{
                $search = [];
            }
            $result = (new TSysCustomerMonthModel())->getCustomerMonthIndex($request,$search);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('111001'); //获取续费月份列表失败
        }
    }

    /*
     * post.api/Profit/createCustomerMonth
     * 设置客户续费月份
     */
    public function createCustomerMonth(Request $request)
    {
        if(empty($this->user)){
            throw new CommonException('300001');
        }
        //验证参数
        $validator = \Validator::make($request->all(),$this->rules,$this->messages,[
            'customerId'=>'客户ID',
            'minMonth'=>'续费最小月份',
        ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        $result = (new TSysCustomerMonthModel)->add($request,$this->user);
        return $this->success($result);
    }

    /*
     * get.api/Profit/customerMonthInfo/{id}
     * 查看客户续费月份
     */
    public function customerMonthInfo($id){
        try{
            $result = (new TSysCustomerMonthModel())->customerMonthInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('111006');//客户续费月份查询失败！
        }
    }
    
    /*
     * post.api/Profit/updateCustomerMonth/{id}
     * 修改客户续费月份
     */
    public function updateCustomerMonth(Request $request, $id){  
        if(!$request->has('minMonth') || empty($request->post('minMonth'))){
            throw new CommonException('300003');
        }
        $result = (new TSysCustomerMonthModel)->updateCustomerMonth($request->post('minMonth'),$id);
        return $this->success($result);
    }
    /*
     * delete.api/Profit/destoryCustomerMonth/{id}
     * 删除客户续费月份
     */
    public function destoryCustomerMonth($id){
        $res= TSysCustomerMonthModel::where('id',$id)->delete();
        if($res){
            return $this->success(['Success'=>true]);
        }else{
            return setFResult('000001','删除续费月份失败！');
        }
    }


}
