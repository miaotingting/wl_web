<?php

namespace App\Http\Controllers\Order;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Order\TCRenewOrderModel;


class TCRenewOrderController extends Controller
{
    /*
     * get.api/Order/renewOrder
     * 续费订单列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new TCRenewOrderModel)->getList($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /**
     * get.api/Order/renewOrder/{id}
     * 续费订单详情信息
     */
    public function show($id){
        try{
            $result = (new TCRenewOrderModel)->getInfo($id,$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /*
     * 续费订单卡片明细 
     */
    public function renewOrderCards($renewOid){
        try{
            $result = (new TCRenewOrderModel)->renewOrderCards($renewOid,$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 创建续费订单：套餐续费/升级 
     */
    public function addPlanRenew(Request $request)
    {
        try{
            /*$validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }*/
            $result = (new TCRenewOrderModel)->addPlanRenew($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('102002');
        }
        
    }
    /*
     * 创建续费订单：订单续费
     */
    public function addOrderRenew(Request $request){
        try{
            /*$validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }*/
            $result = (new TCRenewOrderModel)->addOrderRenew($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('102002');
        }
    }
    

}



