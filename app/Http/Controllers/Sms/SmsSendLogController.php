<?php

namespace App\Http\Controllers\Sms;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Sms\SmsSendLogModel;

class SmsSendLogController extends Controller
{
    /*
     * get.api/Sms/smsSendLog
     * 短信发送日志列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new SmsSendLogModel)->getSmsSendLog($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    
    
    /**
     * get.api/Sms/smsSendLog/{id}
     * 显示指定ID数据
     */
    public function show($id){
        /*try{
            $result = (new SmsCommandModel)->getPackageInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }*/
        
    }
    

}



