<?php

namespace App\Http\Controllers\OpenAPI;

use App\Exceptions\CommonException;
use App\Http\Controllers\Controller;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Customer\Customer;
use Illuminate\Http\Request;
use App\Http\Models\OpenAPI\OpenAPIModel;
use App\Http\Models\OpenAPI\TImplAuthModel;
use App\Http\Models\Order\SaleOrderModel;

class OpenAPIController extends Controller
{
    protected $rules = [
            'clientId'=>'required',
            'cardNo'=>'required',
            'renewLength'=>'required',
            'sign'=>'required',
        ];
    protected $messages = [
            'required'=>'缺少必填参数:attribute',
        ];

    /**
     * 行业卡接口续费（单卡-行业卡账户余额续费）
     * @param Request $request
     * @return void
     */
    public function packageRenew(Request $request){
        try{
            $input =  $request->all();
            // 验证参数完整性
            $validate = $this->validateStr($input);
            if($validate !== true){
                return $validate;
            }
            // 验证能力ID
            $TSysCustomerEntity = (new Customer)->getCustomerData($input['clientId']);
            if(empty($TSysCustomerEntity)){
                return setFResult('600002','调用能力ID错误');
            }
            // 验证接口调用权限
            $TImplAuthEntity = TImplAuthModel::find($input['clientId']);
            if(empty($TImplAuthEntity)){
                return setFResult('600003','调用能力权限限制');
            }
            if($TImplAuthEntity->is_auth != 1){
                return setFResult('600003','调用能力权限限制');
            }
            $customerCode = $TSysCustomerEntity->customer_code;
            $OpenAPIModelEntity = new OpenAPIModel();
            $signArr = [
                'clientId'=>$input['clientId'],
                'cardNo'=>$input['cardNo'],
                'renewLength'=>$input['renewLength'], //续费周期（年）
            ];
            // 验证签名
            $sign = $OpenAPIModelEntity->getSign($signArr,$customerCode);
            if($sign != $input['sign']){
                return setFResult('600004','签名错误');
            }
            // 验证续费周期
            if($input['renewLength'] <= 0){
                return setFResult('600005','非法续费周期');
            }
            if(!is_int($input['renewLength'])){
                return setFResult('600006','续费周期不为整数（年）');
            }
            // 验证客户ID与卡号是否匹配
            $TCCardEntity = CardModel::where('card_no',$input['cardNo'])->first(['order_id']);
            if(empty($TCCardEntity)){
                return setFResult('600007','卡片号码有误');
            }
            $TCSaleOrderEntity = SaleOrderModel::where('id',$TCCardEntity->order_id)->first(['customer_id']);
            if($TCSaleOrderEntity->customer_id != $input['clientId']){
                return setFResult('600008','卡片不属于能力ID');
            }
            
            $result = (new OpenAPIModel)->packageRenew($input);
            if($result['status']){
                return setTResult($result['data'],'续费成功！');
            }else{
                return setFResult($result['code'],$result['msg']);
            }
        } catch (Exception $ex) {
            return setFResult(999999,'操作失败！');
        }
    }

    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
                'clientId'=>'调用能力ID',
                'cardNo'=>'卡号或ICCID',
                'renewLength'=>'续费周期（年）',
                'sign'=>'签名',
            ]);
        if($validator->fails()){
            return setFResult(600001, $validator->errors()->first());
        }
        return true;
    }
    
    // 测试续费获取签名使用（自用）
    public function getSign(Request $request)
    {
        $user = $this->user;
        if(empty($user)){
            throw new CommonException('300001');
        }
        if($user['is_owner'] != 1){
            return setFResult('001','无权调用');
        }
        $input =  $request->all();
        $signArr = [
            'clientId'=>$input['clientId'],
            'cardNo'=>$input['cardNo'],
            'renewLength'=>$input['renewLength'], //续费周期（年）
        ];
        $customerCode = $input['customerCode'];
        // 验证签名
        return $sign = (new OpenAPIModel())->getSign($signArr,$customerCode);
    }
    

}



