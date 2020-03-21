<?php

namespace App\Http\Models\Sms;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;

class SmsReceiveLogModel extends BaseModel
{
    protected $table = 'sms_receive_log';
    public $timestamps = false;
    
    /*
     * 短信发送日志列表
     */
    public function getSmsReceiveLog($input,$loginUser){
        $where = [];
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $orWhere = "";
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getWhere($search);
            if(isset($search['customer']) && !empty($search['customer'])){
                $orWhere = $search['customer'];
            }
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        if($loginUser['is_owner'] == 1){//网来员工
            //$where[] = ['sms_receive_log.create_user_id','=',$loginUser['id']];
        }else{//客户
            $where[] = ['sms_receive_log.belong_customer_id','=',$loginUser['customer_id']];
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize'],$orWhere);
        
        return $data;
    }
    /*
     * 获取列表分类数据
     */
    public function getPageData($where,$page,$pageSize,$orWhere){
        $offset = ($page-1) * $pageSize;
        $sqlObjectJoin = DB::table('sms_receive_log')
                ->leftJoin('sys_customer as c','sms_receive_log.belong_customer_id','=','c.id')
                ->select('sms_receive_log.id','sms_receive_log.mobile','sms_receive_log.iccid','sms_receive_log.content',
                        'sms_receive_log.send_time','sms_receive_log.receive_time',
                        //DB::raw("CASE t_sms_receive_log.status WHEN t_sms_receive_log.status=1 THEN '已读' ELSE '未读' END as status "),
                        DB::raw("CONCAT('(',t_c.customer_code,')',t_c.customer_name) as customer"));
        if(!empty($orWhere)){
            $sqlObjectJoin =$sqlObjectJoin->orWhere(function ($query) use ($orWhere) {
                            $query->where('c.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('c.customer_code','like','%'.$orWhere.'%');
                            });
        }
        $count = $sqlObjectJoin->where($where)->count('sms_receive_log.id');//总条数
        $smsSendLog = $sqlObjectJoin->where($where)->orderBy('send_time','DESC')
                ->offset($offset)->limit($pageSize)->get();
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($smsSendLog)){
            $result['data'] = []; 
        }else{
            $result['data'] = $smsSendLog; 
        }
        return $result;
    }
    
    /*
     * 条件查询
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['status']) && !empty($input['status'])){
            $where[] = ['sms_receive_log.status', '=', $input['status']];//状态
        }
        if(isset($input['mobile']) && !empty($input['mobile'])){
            $where[] = ['sms_receive_log.mobile', 'like', '%'.$input['mobile'].'%'];//接收号码
        }
        if(isset($input['iccid']) && !empty($input['iccid'])){
            $where[] = ['sms_receive_log.iccid', 'like', '%'.$input['iccid'].'%'];//iccid
        }
        if(isset($input['content']) && !empty($input['content'])){
            $where[] = ['sms_receive_log.content', 'like', '%'.$input['content'].'%'];//短信内容
        }
        
        if(isset($input['sendTimeStart']) && !empty($input['sendTimeStart'])){
            $where[] = ['sms_receive_log.send_time', '>=', $input['sendTimeStart']];//发送时间起始时间
        }
        if(isset($input['sendTimeEnd']) && !empty($input['sendTimeEnd'])){
            $where[] = ['sms_receive_log.send_time', '<=', $input['sendTimeEnd']];//发送时间结束时间
        }
        if(isset($input['receiveTimeStart']) && !empty($input['receiveTimeStart'])){
            $where[] = ['sms_receive_log.receive_time', '>=', $input['receiveTimeStart']];//到达时间起始时间
        }
        if(isset($input['receiveTimeEnd']) && !empty($input['createTimeEnd'])){
            $where[] = ['sms_receive_log.receive_time', '<=', $input['createTimeEnd']];//到达时间结束时间
        }
        
        return $where;
    }
    
    
    
    
    

}







