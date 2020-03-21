<?php

namespace App\Http\Models\Card;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Operation\Package;
use App\Http\Models\Admin\TypeDetailModel;

class TCCardRestartDetailModel extends BaseModel
{
    protected $table = 'c_card_restart_detail';
    
    /*
     * 停复机卡片详情
     */
    public function restartCardList($input,$loginUser){
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
        $where[] = ['rd.restart_id','=',$input['restartId']];
        $data = $this->getPageData($where,$input['page'],$input['pageSize'],$orWhere);
        return $data;
    }
    /*
     * 获取所有分页数据
     */
    public function getPageData($where,$page,$pageSize,$orWhere){
        
        $offset = ($page-1) * $pageSize;
        $sqlObject = DB::table('c_card_restart_detail as rd')
                ->leftJoin('c_card_restart as restart','restart.id','=','rd.restart_id')
                ->leftJoin('c_card as card','rd.card_no','=','card.card_no')
                ->leftJoin('sys_customer as customer','customer.id','=','restart.customer_id')
                ->where($where);
        if(!empty($orWhere)){
            $sqlObject = $sqlObject->where(function ($query) use ($orWhere) {
                            $query->where('customer.customer_name','like' ,'%'.$orWhere.'%')
                                  ->orWhere('customer.customer_code','like','%'.$orWhere.'%');
                        });
        }
        $count = $sqlObject->count('rd.id');//总条数
        $data = $sqlObject->orderBy('rd.create_time','DESC')->offset($offset)->limit($pageSize)
            ->get(['rd.id','rd.card_no','rd.iccid','restart.operate_type','restart.station_name',
                'rd.operate_time','rd.create_time','rd.status','card.sale_date','card.active_date',
                'card.valid_date','customer.customer_code','customer.customer_name']);
        $result = array();
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        if($data->isEmpty()){
            $result['data'] = [];
        }else{
            
            $statusGroup = TypeDetailModel::getDetailsByCode('card_restart_detail_status');
            $operatorTypeGroup = TypeDetailModel::getDetailsByCode('card_restart_operate_type');
            foreach($data as $value){
                $value->customer = '('.$value->customer_code.')'.$value->customer_name;
                $value->status = $statusGroup[$value->status];
                $value->operate_type = $operatorTypeGroup[$value->operate_type];
                unset($value->customer_code);
                unset($value->customer_name);
            }
            $result['data'] = $data;
        }
        
        return $result;
    }
    /*
     * 获取查询where条件
     */
    public function getSearchWhere($input){
        $where = array();
        if(isset($input['cardNo']) && !empty($input['cardNo'])){
            $where[] = ['rd.card_no', 'like', '%'.$input['cardNo'].'%'];
        }
        if(isset($input['iccid']) && !empty($input['iccid'])){
            $where[] = ['rd.iccid', 'like', '%'.$input['iccid'].'%'];
        }
        if(isset($input['stationName']) && !empty($input['stationName'])){
            $where[] = ['restart.station_name','like', '%'.$input['stationName'].'%'];
        }
        if(isset($input['restartStatus'])){
            if(!empty($input['restartStatus'])){
                $where[] = ['rd.status', '=', $input['restartStatus']];
            }elseif($input['restartStatus'] == '0'){
                $where[] = ['rd.status', '=', 0];
            }
        }
        //创建时间起始时间
        if(isset($input['startTime']) && !empty($input['startTime'])){
            $where[] = ['rd.create_time', '>=', $input['startTime']];
        }
        //创建时间结束时间
        if(isset($input['endTime']) && !empty($input['endTime'])){
            $where[] = ['rd.create_time', '<=', $input['endTime']];
        }
        return $where;
    }
    
    
    
}







