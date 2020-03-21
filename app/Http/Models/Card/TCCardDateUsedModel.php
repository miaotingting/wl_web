<?php

namespace App\Http\Models\Card;

use App\Http\Models\BaseModel;

use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Customer\Customer;
use Illuminate\Support\Facades\Redis;

class TCCardDateUsedModel extends BaseModel
{
    protected $table = 'c_card_date_used';
    
    /*
     * 月用量查询
     */
    public function getMonthUsed($input,$loginUser){
        if(empty($loginUser)){
            throw new CommonException('300001');
        }
        if(!isset($input['page']) || empty($input['page'])){
            $input['page'] = 1;
        }
        if(!isset($input['pageSize']) || empty($input['pageSize'])){
            $input['pageSize'] = 10;
        }
        if(isset($input['month']) && !empty($input['month'])){
            $thisMonth = $input['month'];
        }else{
            $thisMonth = date('Y-m',time());
        }
        $monthArr = [];//存放要显示的6个月的月份
        for($i = 5; $i >= 0; $i--){
            $monthArr[] = date('Y-m', strtotime($thisMonth . ' -'.$i.' month')); 
        }
        //$whereData = $this->getWhere($loginUser);
        if($loginUser['is_owner'] == 1){//网来员工
            $where = " where 1=1";
        }else{//客户
            $where = " where sorder.customer_id ='".$loginUser['customer_id']."'";
        }
        $manyCardNoWhere = "";
        if(isset($input['search']) && !empty($input['search'])){
            $search = json_decode($input['search'],TRUE);
            $searchWhere = $this->getSearchWhere($search);
            $where = $where.$searchWhere['where'];
            $manyCardNoWhere = $searchWhere['manyCardNoWhere'];
        }
        $result = $this->getPageData($monthArr,$thisMonth, $where,$manyCardNoWhere,$input['page'],$input['pageSize']);
        return $result;
    }

    //获取缓存
    private function getCache($monthArr, $thisMonth, $where, $manyCardNoWhere, $page, $pageSize) {
        //搜索条件加上页码页数加上月份生成Key，因为数据每月更新，设置30天过期时间
        $key = md5(json_encode($monthArr) . $thisMonth . $where . $manyCardNoWhere . $page . $pageSize . date('m'));
        if (Redis::exists($key)) {
            return json_decode(Redis::get($key), true);
        }
    }

    //设置缓存
    private function setCache($monthArr, $thisMonth, $where, $manyCardNoWhere, $page, $pageSize, $data) {
        //搜索条件加上页码页数加上月份生成Key，因为数据每月更新，设置30天过期时间
        $key = md5(json_encode($monthArr) . $thisMonth . $where . $manyCardNoWhere . $page . $pageSize . date('m'));
        Redis::setex($key, 3600 * 24 * 30, json_encode($data));
    }

    /*
     * 分页信息
     */
    public function getPageData($monthArr,$thisMonth,$where,$manyCardNoWhere,$page,$pageSize){
        
        //缓存策略
        $cache = $this->getCache($monthArr, $thisMonth, $where, $manyCardNoWhere, $page, $pageSize);

        //如果取出来有数据就返回
        if (!empty($cache)) {
            return $cache;
        }
        //没有数据就往下走

        $start = ($page-1) * $pageSize; 
        $sqlCount = $this->getSql('count', $thisMonth, $where,$manyCardNoWhere, $start, $pageSize);
        $sql = $this->getSql('data', $thisMonth, $where,$manyCardNoWhere, $start, $pageSize);
        $monthUsedCount = DB::select($sqlCount);
        $count = count($monthUsedCount);//总条数
        $monthUsedData = DB::select($sql);
        $result = [];
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['title'] = $monthArr;
        $data=[];
        if(!empty($monthUsedData)){
            $data = $this->clearUpMonthUsedList($monthUsedData,$monthArr);
        }
        $result['data'] = $data;

        //放入缓存
        $this->setCache($monthArr, $thisMonth, $where, $manyCardNoWhere, $page, $pageSize, $result);
        return $result;
    }
    /*
     * 查询月用量SQL语句
     * 'data', $thisMonth, $where, $start, $pageSize
     */
    public function getSql($type,$thisMonth,$where,$manyCardNoWhere,$start=0,$pageSize=10){
        $limit = "";
        if($type == 'count'){
            
        }elseif($type == 'data'){
            $limit = " limit ".$start.",".$pageSize;
        }
        $cardWhere1 = "";
        $cardWhere2 = "";
        if(!empty($manyCardNoWhere)){
            $cardWhere1 = " and card.card_no in ".$manyCardNoWhere ;
            $cardWhere2 = " and cdu.card_no in ".$manyCardNoWhere;
        }
        $sql = "SELECT t.card_no,IFNULL(group_concat(t.useDate,':',IFNULL(t.used,0)),2) usedStr FROM(
             SELECT cardData.card_no,used,useDate,order_id FROM 
             (SELECT card_no,order_id FROM t_c_card card WHERE  1=1 ".$cardWhere1." ) cardData
              LEFT JOIN (
               SELECT card_no,DATE_FORMAT(use_date, '%Y-%m') useDate,round(SUM(flow_used)/1024, 2) AS used FROM t_c_card_date_used cdu WHERE 1 = 1 
               AND use_date between date_sub('".$thisMonth."-01',interval 5 month) and date_sub(date_add('".$thisMonth."-01',interval 1 month),interval 1 day)
                ".$cardWhere2." GROUP BY card_no,useDate
              ) dayU on dayU.card_no=cardData.card_no
            ) t
            LEFT JOIN t_c_sale_order sorder on t.order_id = sorder.id".$where.
            " GROUP BY card_no ".$limit;
        return $sql;
    }
    /*
     * 网来员工，客户的登录条件
     */
    public function getWhere($loginUser){
        $isOwnerJoin = "LEFT JOIN t_sys_customer customer on customer.id=c_order.customer_id";
        $where = "";
        if($loginUser['is_owner'] == 1 ){
            //判断登录用户是否是销售人员（是则只显示属于自己的客户卡片）
            $isNoMarketRole = RoleUser::where(['role_id'=>config('info.role_xiaoshou_id'),'user_id'=>$loginUser['id']])->first(['id']);
            if(!empty($isNoMarketRole)){
                $where = "where customer.account_manager_id = '".$loginUser['id']."'";
            }
        }else{//客户登录
            $customerData = (new Customer)->getCustomerData($loginUser['customer_id']);
            if(empty($customerData)){
                throw new CommonException('102003');//客户不存在
            }
            if($customerData->level == 1){ //一级客户
                $where = "where c_order.customer_id = '".$loginUser['customer_id']."'";
            }else{
                $cdata = array();
                $allChildId = (new Customer)->getAllChildID($cdata, $loginUser['customer_id']);
                $allChildId[]['id'] = $loginUser['customer_id'];
                $subCustomerIdStr = (new CardModel)->setArrayChangeStr($allChildId,'id');
                $where = "where card.customer_id in ".$subCustomerIdStr;
                $isOwnerJoin = "LEFT JOIN t_sys_customer customer on customer.id=card.customer_id ";
            }
        }
        if(empty($where)){
            $where = "where 1=1";
        }
        $result = [];
        $result['where'] = $where;
        $result['isOwnerJoin'] = $isOwnerJoin;
        return $result;
    }
    
    /*
     * search条件查询
     */
    public function getSearchWhere($input){
        $where = "";
        $manyCardNoWhere = "";
        if(isset($input['customer']) && !empty($input['customer'])){//客户名称或编号
            $where .= " and sorder.customer_name like  '%".$input['customer']."%'";
        }
        if(isset($input['cardNo']) && !empty($input['cardNo'])){//批量查卡号
            $cardNoArr = explode(',',trim($input['cardNo'],','));
            $cardNoStr = (new CardModel)->setArrayChangeStr($cardNoArr);
            //print_r($cardNoStr);exit;
            $manyCardNoWhere = $cardNoStr;
        }
        if(isset($input['orderNo']) && !empty($input['orderNo'])){//订单号
            $where .= " and sorder.order_no like  '%".$input['orderNo']."%'";
        }
        $result['where'] = $where;
        $result['manyCardNoWhere'] = $manyCardNoWhere;
        return $result;
    }
    /*
     * 整理月用量列表
     */
    public function clearUpMonthUsedList($monthUsedData,$monthArr){
        $data = [];
        foreach($monthUsedData as $value){
            if($value->usedStr == 2){
                
                $singleData['firstMonth'] = '0';
                $singleData['secondMonth'] = '0';
                $singleData['thirdMonth'] = '0';
                $singleData['fourthMonth'] = '0';
                $singleData['fifthMonth'] = '0';
                $singleData['sixthMonth'] = '0';
            }else{
                $oneArr = explode(',',$value->usedStr);
                $secondArr = [];
                foreach($oneArr as $value1){
                    $secondArr[] = explode(':',$value1);
                    $thirdArr = [];
                    foreach($secondArr as $value3){
                        $thirdArr[$value3[0]]=$value3[1];
                    }
                }
                $singleData['firstMonth'] = isset($thirdArr[$monthArr[0]])?$thirdArr[$monthArr[0]]:'0';
                $singleData['secondMonth'] = isset($thirdArr[$monthArr[1]])?$thirdArr[$monthArr[1]]:'0';
                $singleData['thirdMonth'] = isset($thirdArr[$monthArr[2]])?$thirdArr[$monthArr[2]]:'0';
                $singleData['fourthMonth'] = isset($thirdArr[$monthArr[3]])?$thirdArr[$monthArr[3]]:'0';
                $singleData['fifthMonth'] = isset($thirdArr[$monthArr[4]])?$thirdArr[$monthArr[4]]:'0';
                $singleData['sixthMonth'] = isset($thirdArr[$monthArr[5]])?$thirdArr[$monthArr[5]]:'0';
            }
            $singleData['cardNo'] = $value->card_no;
            $data[]=$singleData;
        }
        return $data;
    }
    
    
    
    

}







