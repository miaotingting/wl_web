<?php

namespace App\Http\Models\Sms;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\TypeDetailModel;

class SmsSendLogModel extends BaseModel
{
    protected $table = 'sms_send_log';
    public $timestamps = false;
    
    /*
     * 短信发送日志列表
     */
    public function getSmsSendLog($input,$loginUser){
        $where = [];
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        $orWhere = "";
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            
            $where = $this->getWhere($search);
            //print_r($search);exit;
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
            //$where[] = ['sms_send_log.create_user_id','=',$loginUser['id']];
        }else{//客户
            $where[] = ['sms_send_log.belong_customer_id','=',$loginUser['customer_id']];
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize'],$orWhere);
        
        return $data;
    }
    /*
     * 获取列表分类数据
     */
    public function getPageData($where,$page,$pageSize,$orWhere){
        $offset = ($page-1) * $pageSize;
        $sqlObjectJoin = DB::table('sms_send_log')
                ->leftJoin('sys_user as u','sms_send_log.create_user_id','=','u.id')
                ->leftJoin('sys_customer as c','sms_send_log.belong_customer_id','=','c.id')
                ->select('sms_send_log.id','sms_send_log.mobile','sms_send_log.iccid','sms_send_log.content',
                        'sms_send_log.send_time','sms_send_log.create_time','sms_send_log.status',
                        //DB::raw("CASE t_sms_send_log.status WHEN t_sms_send_log.status=1 THEN '发送成功' WHEN t_sms_send_log.status=2 THEN '发送失败' ELSE '短信已提交' END as status "),
                        DB::raw("CONCAT('(',t_c.customer_code,')',t_c.customer_name) as customer"),
                        'u.real_name as create_name');
        if(!empty($orWhere)){
            $sqlObjectJoin =$sqlObjectJoin->orWhere(function ($query) use ($orWhere) {
                            $query->where('c.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('c.customer_code','like','%'.$orWhere.'%');
                            });
        }
        $count = $sqlObjectJoin->where($where)->count('sms_send_log.id');//总条数
        $smsSendLog = $sqlObjectJoin->where($where)->orderBy('create_time','DESC')
                ->offset($offset)->limit($pageSize)->get();
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($smsSendLog)){
            $result['data'] = []; 
            return $result;
        }
        foreach($smsSendLog as $value){
            $statusGroup = TypeDetailModel::getDetailsByCode('sms_send_log_status');
            $value->status = $statusGroup[$value->status];
        }
        $result['data'] = $smsSendLog;
        return $result;
    }
    
    /*
     * 条件查询
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['mobile']) && !empty($input['mobile'])){
            $where[] = ['sms_send_log.mobile', 'like', '%'.$input['mobile'].'%'];//接收号码
        }
        if(isset($input['iccid']) && !empty($input['iccid'])){
            $where[] = ['sms_send_log.iccid', 'like', '%'.$input['iccid'].'%'];//iccid
        }
        if(isset($input['content']) && !empty($input['content'])){
            $where[] = ['sms_send_log.content', 'like', '%'.$input['content'].'%'];//短信内容
        }
        if(isset($input['createTimeStart']) && !empty($input['createTimeStart'])){
            $where[] = ['sms_send_log.create_time', '>=', $input['createTimeStart']];//创建时间起始时间
        }
        if(isset($input['createTimeEnd']) && !empty($input['createTimeEnd'])){
            $where[] = ['sms_send_log.create_time', '<=', $input['createTimeEnd']];//创建时间结束时间
        }
        if(isset($input['sendTimeStart']) && !empty($input['sendTimeStart'])){
            $where[] = ['sms_send_log.send_time', '>=', $input['sendTimeStart']];//发送时间起始时间
        }
        if(isset($input['sendTimeEnd']) && !empty($input['sendTimeEnd'])){
            $where[] = ['sms_send_log.send_time', '<=', $input['sendTimeEnd']];//发送时间结束时间
        }
        
        return $where;
    }
    
    
    
    
    

}







