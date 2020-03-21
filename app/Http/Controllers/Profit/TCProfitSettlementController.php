<?php

namespace App\Http\Controllers\Profit;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\CommonException;
use App\Http\Models\Profit\TCProfitSettlementModel;

class TCProfitSettlementController extends Controller
{
    
    /* 网来人员登录这看所有报表（其中销售自己看自己客户的）
     * get.api/Profit/directProfitIndex
     * 直销分润明细列表
     */
    public function directProfitIndex(Request $request)
    {
        if(empty($this->user)){
            throw new CommonException('300001');
        }
        try{
            $result = (new TCProfitSettlementModel())->directSellingIndex($request->all(),$this->user,'direct');
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }

    /* 网来分润报表
     * get.api/Profit/ownProfitReport
     * 网来分润报表
     */
    public function ownProfitReport(Request $request)
    {
        if(empty($this->user)){
            throw new CommonException('300001');
        }
        try{
            $result = (new TCProfitSettlementModel())->directSellingIndex($request->all(),$this->user,'direct');
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }

    /*
     * get.api/Profit/agentProfitIndex
     * 代理分润明细列表
     */
    public function agentProfitIndex(Request $request)
    {
        try{
            $result = (new TCProfitSettlementModel())->directSellingIndex($request->all(),$this->user,'agent');
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    

}
