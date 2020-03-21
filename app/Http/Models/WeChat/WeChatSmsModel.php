<?php

namespace App\Http\Models\WeChat;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Sms\SmsSendLogModel;
use App\Http\Models\Sms\SmsReceiveLogModel;


class WeChatSmsModel extends BaseModel
{
    /*
     * 卡片的短信详情
     */
    public function mobileSmsList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $sqlCount = $this->getSql($input['cardNo'], 'count', $input['page'], $input['pageSize']);
        $countRes = DB::select($sqlCount);
        $count = $countRes[0]->total+$countRes[1]->total;
        
        $sqlData = $this->getSql($input['cardNo'], 'data', $input['page'], $input['pageSize']);
        $dataRes = DB::select($sqlData);
        $result = array();
        $pageCount = ceil($count/$input['pageSize']); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$input['page'];
        $result['pageSize'] = $input['pageSize'];
        $result['pageCount'] = $pageCount;
        $result['data'] = $dataRes;
        return $result; 
    }
    
    public function getSql($cardNo,$type,$page,$pageSize){
        $limit = "";
        if($type=='count'){
            $str1 = 'count(send.mobile) as total';
            $str2 = 'count(receive.mobile) as total';
        }else{
            $str1 = 'mobile,send_time,content';
            $str2 = 'mobile,send_time,content';
            $start = ($page-1) * $pageSize;
            $limit = "ORDER BY send_time DESC  limit ".$start.",".$pageSize;
        }
        $sql = "SELECT ".$str1.",1 AS type FROM t_c_card card 
                LEFT JOIN t_sms_send_log send ON card.card_no = send.mobile
                WHERE card_no = '".$cardNo."' 
                UNION ALl
                SELECT ".$str2.",2 AS type FROM t_c_card card 
                LEFT JOIN t_sms_receive_log receive ON card.card_no = receive.mobile
                WHERE card_no = '".$cardNo."'".$limit;
        return $sql;
    }
           

    
    
    
    

}







