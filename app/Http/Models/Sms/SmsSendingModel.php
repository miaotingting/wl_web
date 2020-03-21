<?php

namespace App\Http\Models\Sms;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Card\CardModel;

class SmsSendingModel extends BaseModel
{
    protected $table = 'sms_sending';
    public $timestamps = false;
    /*
     * 短信发送操作
     */
    public function addSms($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(strlen($input['mobiles']) > 70000){
            throw new CommonException('108005');//最多操作5000张卡片
        }
        $mobileArr = explode(',',trim($input['mobiles'],','));
        $mobileCardCount = CardModel::where([['status','<>',-1],['status','<>',0]])
                ->whereIn('card_no',$mobileArr)
                ->count('id');
        if(count($mobileArr) != $mobileCardCount){
            throw new CommonException('108002');//包含不正确或重复的卡片
        }
        $haveGatewayCount = DB::table('c_card as c')
                ->leftJoin('sys_gateway_config as gc','c.gateway_id','=','gc.id')
                ->where(['gc.is_use'=>1])
                ->whereIn('c.card_no',$mobileArr)
                ->count('c.id');
        if($haveGatewayCount != count($mobileArr)){
            throw new CommonException('108003');//卡片必须配置网关
        }
        if($loginUser['is_owner'] == 0){//如果是客户
            $isNoUserCardCount = CardModel::where('customer_id',$loginUser['customer_id'])
                    ->whereIn('card_no',$mobileArr)->count('id');
            if($isNoUserCardCount != count($mobileArr)){
                throw new CommonException('108004');//包含不属于自己的卡片
            }
        }
        foreach($mobileArr as $value){
                $cardData = CardModel::where('card_no',$value)->first(['id','card_type','iccid','gateway_id','customer_id']);
                $temp['mobile'] = $value;
                $temp['gateway_id'] = $cardData->gateway_id;
                $temp['card_type'] = $cardData->card_type;
                $temp['iccid'] = $cardData->iccid;
                $temp['content'] = $input['content'];
                $temp['create_time'] = date("Y-m-d H:i:s");
                $temp['create_user_id'] = $loginUser['id'];
                $temp['belong_customer_id'] = $cardData->customer_id;
                $data[] = $temp;
        }
        $res = SmsSendingModel::insert($data);
        return $res;
    }
    /*
     * 短信发送列表
     */
    public function getSmsSending($input,$loginUser){
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
            //$where[] = ['sms_sending.create_user_id','=',$loginUser['id']];
        }else{//客户
            $where[] = ['sms_sending.belong_customer_id','=',$loginUser['customer_id']];
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize'],$orWhere);
        
        return $data;
    }
    /*
     * 获取列表分类数据
     */
    public function getPageData($where,$page,$pageSize,$orWhere){
        $offset = ($page-1) * $pageSize;
        $sqlObjectJoin = DB::table('sms_sending')
                ->leftJoin('sys_user as u','sms_sending.create_user_id','=','u.id')
                ->leftJoin('sys_customer as c','sms_sending.belong_customer_id','=','c.id')
                ->select('sms_sending.id','sms_sending.mobile','sms_sending.iccid','sms_sending.content',
                        'sms_sending.create_time','sms_sending.status',
                        DB::raw("CONCAT('(',t_c.customer_code,')',t_c.customer_name) as customer"),
                        'u.real_name as create_name');
        if(!empty($orWhere)){
            $sqlObjectJoin =$sqlObjectJoin->where(function ($query) use ($orWhere) {
                            $query->where('c.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('c.customer_code','like','%'.$orWhere.'%');
                            });
        }
        $count = $sqlObjectJoin->where($where)->count('sms_sending.id');//总条数
        $smsSending = $sqlObjectJoin->where($where)->orderBy('create_time','DESC')
                ->offset($offset)->limit($pageSize)->get();
        $result = array();
        $pageCount = ceil((int)$count/(int)$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = $page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if(empty($smsSending)){
            $result['data'] = []; 
            return $result;
        }
        foreach($smsSending as $value){
            $statusGroup = TypeDetailModel::getDetailsByCode('sms_sending_status');
            $value->status = $statusGroup[$value->status];
        }
        $result['data'] = $smsSending;
        return $result;
    }
    
    /*
     * 条件查询
     */
    public function getWhere($input){
        $where = array();
        if(isset($input['mobile']) && !empty($input['mobile'])){
            $where[] = ['sms_sending.mobile', 'like', '%'.$input['mobile'].'%'];//接收号码
        }
        if(isset($input['iccid']) && !empty($input['iccid'])){
            $where[] = ['sms_sending.iccid', 'like', '%'.$input['iccid'].'%'];//iccid
        }
        if(isset($input['content']) && !empty($input['content'])){
            $where[] = ['sms_sending.content', 'like', '%'.$input['content'].'%'];//短信内容
        }
        if(isset($input['createTimeStart']) && !empty($input['createTimeStart'])){
            $where[] = ['sms_sending.create_time', '>=', $input['createTimeStart']];//创建时间起始时间
        }
        if(isset($input['createTimeEnd']) && !empty($input['createTimeEnd'])){
            $where[] = ['sms_sending.create_time', '<=', $input['createTimeEnd']];//创建时间结束时间
        }
        if(isset($input['cardType']) && !empty($input['cardType'])){
            $where[] = ['sms_sending.card_type', '=', $input['cardType']];//卡片类型
        }
        return $where;
    }
    
    
    
    
    

}







