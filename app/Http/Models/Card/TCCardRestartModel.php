<?php

namespace App\Http\Models\Card;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Models\Card\CardModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Card\TCCardRestartDetailModel;
use App\Http\Models\Operation\Package;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Admin\Station;
use App\Http\Models\Admin\TypeDetailModel;

class TCCardRestartModel extends BaseModel
{
    protected $table = 'c_card_restart';
    
    /*
     * 新建停复机申请
     */
    public function addRestartCard($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        
        $cardNoArr = explode(',', trim($input['cardNo'],','));
        $this->estimateType($input, $cardNoArr);
        
        $restartId = getUuid();
        DB::beginTransaction();
        $resRestart = $this->addRestart($restartId, $input, $loginUser,count($cardNoArr));
        $resDetail = $this->addRestartDetail($restartId,$cardNoArr);
        
        if($resRestart == TRUE && $resDetail == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
        
    }
    /*
     * 添加卡片停复机记录
     */
    public function addRestart($restartId,$input,$loginUser,$cardCount){
        $data = array();
        $data['id'] = $restartId;
        $data['restart_code'] = getOrderNo('TF');
        $data['operate_type'] = $input['operateType'];
        $data['status'] = 0;
        $data['create_user_id'] = $loginUser['id'];
        $data['create_user_name'] = $loginUser['real_name'];
        $data['customer_id'] = $input['customerId'];
        $data['customer_name'] = (new Customer)->getCustomerName($input['customerId']);
        $data['restart_num'] = $cardCount;
        $data['apply_reason'] = $input['applyReason'];
        $data['create_time'] = date('Y-m-d H:i:s',time());
        $data['station_id'] = $input['stationId'];
        $data['station_name'] = (new Station)->getStationName($input['stationId']);
        $res = TCCardRestartModel::insert($data);
        return $res;
    }
    /*
     * 添加卡片停复机详细记录
     */
    public function addRestartDetail($restartId,$cardNoArr){
        $data = array();
        foreach ($cardNoArr as $value){
            $cardInfoData = CardModel::where('card_no',$value)
                    ->first(['iccid','card_type']);
            $temp['id'] = getUuid();
            $temp['card_no'] = $value;
            $temp['iccid'] = $cardInfoData->iccid;
            $temp['restart_id'] = $restartId;
            //$temp['operate_type'] = $type;
            $temp['status'] = 0;
            $temp['card_type'] = $cardInfoData->card_type;
            $temp['create_time'] = date('Y-m-d H:i:s',time());
            $data[] = $temp;
        }
        $res = TCCardRestartDetailModel::insert($data);
        return $res;
    }
    /*
     * 新建停复机申请单时的条件
     */
    public function estimateType($input,$cardNoArr){
        $where = array();
        $where[] = ['c.station_id','=',$input['stationId']];
        $where[] = ['o.customer_id','=',$input['customerId']];
        /*$sqlObject = DB::table('c_card as c')
                ->leftjoin('c_sale_order as o','c.order_id','o.id')
                ->where($where)
                ->whereIn('c.card_no',$cardNoArr);*/
        $cardDataCount = DB::table('c_card as c')
                ->leftjoin('c_sale_order as o','c.order_id','o.id')
                ->where($where)
                ->whereIn('c.card_no',$cardNoArr)
                ->count('c.id');
        if($cardDataCount != count($cardNoArr)){
            //操作失败，卡片不属于所选落地和客户
            throw new CommonException('106023');
        }
        if($input['operateType'] == 2){ //复机
            $statusCount = DB::table('c_card')->where(function ($query) {
                            $query->where('status',3)
                                ->orWhere('status',4);
                            })->whereIn('card_no',$cardNoArr)
                            ->count('id');
            if($statusCount != count($cardNoArr)){
                //操作失败，卡片状态有问题
                throw new CommonException('106026');
            }                
        }
        //$countCard = $sqlObject->count('c.id');
        
        if($input['operateType'] == 2){//复机
            $nowTime = time();//当天时间
            $time25 = strtotime(date('Y-m-25',time()));//25号时间
            $BeginDate=date('Y-m-01', strtotime(date("Y-m-d")));
            $lastDay = strtotime("$BeginDate +1 month -1 day");//月末
            $monthTime = date('Y-m',time());
            $validCard = CardModel::where([['valid_date','like',$monthTime.'%']])
                ->whereIn('card_no',$cardNoArr)
                ->count('id');
            if($validCard > 0 && $nowTime>=$time25 && $nowTime<=$lastDay){
                //此阶段不支持卡片复机操作
                throw new CommonException('106024');
            }
        }
        
    }
    /*
     * 停复机管理列表
     */
    public function restartList($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        $where = array();
        $orWhere = '';
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $where = $this->getSearchWhere($search);
            if(isset($search['customer']) && !empty($search['customer'])){
                $orWhere = $search['customer'];
            }
        }
        $data = $this->getPageData($where,$input['page'],$input['pageSize'],$orWhere);
        return $data;
    }
    /*
     * 获取所有分页数据
     */
    public function getPageData($where,$page,$pageSize,$orWhere){
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_card_restart as restart')
                ->leftJoin('sys_customer as customer','restart.customer_id','=','customer.id');
        if(!empty($orWhere)){
            $sqlObject = $sqlObject->orWhere(function ($query) use ($orWhere) {
                            $query->where('restart.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('customer.customer_code','like','%'.$orWhere.'%');
                            });
        }
        if(!empty($where)){
            $sqlObject = $sqlObject->where($where);
        }
        
        $count = $sqlObject->count('restart.id');//总条数
        $data = $sqlObject->orderBy('restart.create_time','DESC')->offset($offset)->limit($pageSize)
            ->get(['restart.id','restart.restart_code','restart.operate_type','restart.operate_time',
                'restart.status','restart.customer_name','restart.restart_num','restart.apply_reason',
                'restart.create_time','customer.customer_code']);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
        }else{
            $statusGroup = TypeDetailModel::getDetailsByCode('card_restart_status');
            $operatorTypeGroup = TypeDetailModel::getDetailsByCode('card_restart_operate_type');
            foreach($data as $value){
                $value->status = $statusGroup[$value->status];
                $value->operate_type = $operatorTypeGroup[$value->operate_type];
                $value->customer = '('.$value->customer_code.')'.$value->customer_name;
                $value->restart_num = (string)$value->restart_num;
                unset($value->customer_code);
                unset($value->customer_name);
            }
            $result['data'] = $data;
        }
        return $result;
    }
    /*
     * 获取查询的where条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['restartCode']) && !empty($input['restartCode'])){
            $where[] = ['restart.restart_code', 'like', '%'.$input['restartCode'].'%'];
        }
        if(isset($input['operateType']) && !empty($input['operateType'])){
            $where[] = ['restart.operate_type', '=', $input['operateType']];
        }
        if(isset($input['restartStatus']) ){
            if(!empty($input['restartStatus'])){
                $where[] = ['restart.status', '=', $input['restartStatus']];
            }elseif($input['restartStatus'] == '0'){
                $where[] = ['restart.status', '=',0];
            }
        }
        
        return $where;
    }
    
    /*
     * 新建停复机申请(接口使用)
     */
    public function createImplRestart($input){
        $restartId = getUuid();
        DB::beginTransaction();
        $data = array();
        $data['id'] = $restartId;
        $data['restart_code'] = getOrderNo('TF');
        $data['operate_type'] = 2; //1停机，2复机',
        $data['status'] = 0;
        $data['create_user_id'] = '0';
        $data['create_user_name'] = '管理员';
        $data['customer_id'] = $input['customerId'];
        $data['customer_name'] = $input['customerName'];
        $data['restart_num'] = 1;
        $data['apply_reason'] = '接口停机保号续费进行复机';
        $data['create_time'] = date('Y-m-d H:i:s',time());
        $data['station_id'] = $input['stationId'];
        $data['station_name'] = $input['stationName'];
        $res = TCCardRestartModel::insert($data);

        $dataDetail = [];
        $dataDetail['id'] = getUuid();
        $dataDetail['card_no'] = $input['cardNo'];
        $dataDetail['iccid'] = $input['iccid'];
        $dataDetail['restart_id'] = $restartId;
        $dataDetail['status'] = 0;
        $dataDetail['card_type'] = $input['cardType'];
        $dataDetail['create_time'] = date('Y-m-d H:i:s',time());
        $resDetail = TCCardRestartDetailModel::insert($dataDetail);
        
        if($res == TRUE && $resDetail == TRUE){
            DB::commit();
        }else{
            DB::rollBack();
            return FALSE;
        }
        return TRUE;
    }
    
    
}







