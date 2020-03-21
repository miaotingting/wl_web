<?php

namespace App\Http\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use App\Http\Utils\Response as UserResponse;
use App\Http\Utils\Matter;
use Illuminate\Support\Facades\DB;

class BaseModel extends Model
{
    use UserResponse;
    use Matter;
    public $incrementing = false;
    protected $keyType = "string";

    public $dicArr = [];

    const LIKE = 'like';
    const ORWHERE = 'or';

    /**
     * 时间转月份最后一天
     * @param $date 时间
     * @param $month 加几个月
     */
    function dateToMonthLastDay($date, $month = 1) {
        $oneMonth = $this->dateToMonthOne($date);
        return date('Y-m-t', strtotime($oneMonth . '+'.$month.' months'));
    }

    /**
     * 时间转月份最后一天
     * @param $date 时间
     * @param $month 加几个月
     */
    function dateToMonthOne($date) {
        $date1 = explode('-',$date);
        return $date1[0] . '-' . $date1[1] . '-01';
    }

    /**
     * 返回相差的月份数量
     * @param $date1 string 开始日期
     * @param $date2 string 结束日期
     * @return float|int
     */
    function getMonth( $date){
        $time1 = strtotime($date);
        if($time1 > time()){
            // 服务期内
            $year = date('Y') - date('Y',$time1);
            $month = date('m',$time1) - date('m');
            if ($year == 0) {
                if ($month == 0) {
                    return 0;
                }
            }
            return -1;
         
        }else{
            // 停机保号
            $year = date('Y') - date('Y',$time1);
            $month = date('m') - date('m',$time1);
            if($year == 0){
                return $month;
            }else{
                return (12 - date('m',$time1)) + date('m');
            }
        }
    }

    protected function getApi($url, $data) {
        $url = $url . '?' . http_build_query($data);
        $req = \Httpful\Request::get($url);
        // dump($req->payload);
        $res = $req->send();
        // dump($res);
        $body = $res->body;
        return $body;
    }

    // 用curl实现多进程批量请求
    function https_post_batch_request($url, $data){
        // dd($data);
        $mh = curl_multi_init();  
        // 添加批量请求资源
        foreach($data as $id=>$post) {  
            // dd($post);
            $conn[$id] = curl_init();  
            curl_setopt($conn[$id], CURLOPT_URL, $url);    
            curl_setopt($conn[$id], CURLOPT_POST, 1);
            curl_setopt($conn[$id], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($conn[$id], CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($conn[$id], CURLOPT_POSTFIELDS, json_encode($post));
            curl_setopt($conn[$id], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn[$id], CURLOPT_HTTPHEADER, array('Content-type:application/json'));
            curl_multi_add_handle ($mh, $conn[$id]);
        } 
        
        // 执行资源 这样写可以防止卡死,它是至少执行一次，没有资源就会是false就会跳出
        do {     
            curl_multi_exec($mh,$active); 
            // dump($active);    
        } while ($active);  
        
        $data_arr = [];
        foreach($data as $id=>$post) {  
            $res = curl_multi_getcontent($conn[$id]);
            // dump($res);
            if (!empty($res)) {
                $data_arr[$id] = $res;
            } 
            
            curl_multi_remove_handle($mh, $conn[$id]);  
            curl_close($conn[$id]);
        }  
        curl_multi_close($mh);  
        return $data_arr;
    }

    // 用curl实现多进程批量请求
    function piliang_request($url, $data){
        // dd($data);
        $mh = curl_multi_init();  
        // 添加批量请求资源
        foreach($data as $id=>$post) {  
            // dd($post);
            $conn[$id] = curl_init();  
            curl_setopt($conn[$id], CURLOPT_URL, $url);    
            curl_setopt($conn[$id], CURLOPT_POST, 1);
            curl_setopt($conn[$id], CURLOPT_POSTFIELDS, json_encode($post));
            curl_setopt($conn[$id], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn[$id], CURLOPT_HTTPHEADER, array('Content-type:application/json'));
            curl_multi_add_handle ($mh, $conn[$id]);
        } 
        
        // 执行资源 这样写可以防止卡死,它是至少执行一次，没有资源就会是false就会跳出
        do {     
            curl_multi_exec($mh,$active); 
            // dump($active);    
        } while ($active);  
        
        $data_arr = [];
        foreach($data as $id=>$post) {  
            $res = curl_multi_getcontent($conn[$id]);
            // dump($res);
            if (!empty($res)) {
                $data_arr[$id] = $res;
            } 
            
            curl_multi_remove_handle($mh, $conn[$id]);  
            curl_close($conn[$id]);
        }  
        curl_multi_close($mh);  
        return $data_arr;
    }

    //HTTPS请求中国移动基地接口：application/x-www-form-urlencoded
    function https_request($url, $data=null, $cookie=null){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type:application/x-www-form-urlencoded'));
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    // Https批量GET请求中国移动基地接口
    function https_piliang_request($urls){
        // dd($data);
        $mh = curl_multi_init();  
        // 添加批量请求资源
        foreach($urls as $id=>$url) {  
            // dd($post);
            $conn[$id] = curl_init();  
            curl_setopt($conn[$id], CURLOPT_URL, $url);
            curl_setopt($conn[$id], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($conn[$id], CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($conn[$id], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn[$id], CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($conn[$id], CURLOPT_TIMEOUT, 60);
            curl_setopt($conn[$id], CURLOPT_HTTPHEADER, array('Content-type:application/x-www-form-urlencoded'));
            curl_multi_add_handle ($mh, $conn[$id]);
        } 
        
        // 执行资源 这样写可以防止卡死,它是至少执行一次，没有资源就会是false就会跳出
        do {     
            curl_multi_exec($mh,$active); 
            // dump($active);    
        } while ($active);  
        
        $data_arr = [];
        foreach($urls as $id=>$url) {  
            // dump($conn[$id]);
            $res = curl_multi_getcontent($conn[$id]);
            // dump($res);
            if (!empty($res)) {
                $data_arr[$id] = $res;
            } 
            
            curl_multi_remove_handle($mh, $conn[$id]);  
            curl_close($conn[$id]);
        }  
        curl_multi_close($mh);  
        return $data_arr;
    }

    private function getQueryable(array $where = [], array $orderBy = []) {
        $tempSql = $this;
        // dd($tempSql);
        $compareOP = ["<","<=",">",">=","=", '!='];
        if (count($where) > 0) {
            foreach ($where as $col => $val) {
                if (is_array($val)) {
                    if (in_array($val[0], $compareOP)) {
                        $tempSql = $tempSql->where($col, $val[0], $val[1]);
                    } else if ($val[0] == self::LIKE) {
                        $tempSql = $tempSql->where($col, $val[0], "%".$val[1]."%");
                    } else if ($val[0] == self::ORWHERE) {
                        //使用orWhere
                        if (is_array($val[1])) {
                            $orWhereArr = $val[1];
                            if (in_array($orWhereArr[0], $compareOP)) {
                                $tempSql = $tempSql->orWhere($col, $orWhereArr[0], $orWhereArr[1]);
                            } elseif ($val[0] == self::LIKE) {
                                $tempSql = $tempSql->orWhere($col, $orWhereArr[0], "%".$orWhereArr[1]."%");
                            }
                        } else {
                            $tempSql = $tempSql->orWhere($col, $val[1]);
                        }
                    } else {
                        $tempSql = $tempSql->whereBetween($col, $val);
                    }
                } else {
                    $tempSql = $tempSql->where($col, $val);
                }
            }
        }

        if (count($orderBy) > 0) {
            foreach ($orderBy as $key => $value) {
                $tempSql = $tempSql->orderBy($key, $value);
            }
        }
        return $tempSql;
    }

    /**
     * 查询分页
     */
    function queryPage(int $pageSize, int $pageIndex, array $where = [], array $filed = ['*'], $orderBy = [],  $countFiled = '*') {
        $offset = ($pageIndex-1) * $pageSize;
        // dd($where);
        // DB::connection()->enableQueryLog();
        $sql = $this->getQueryable($where, $orderBy);
        
        $count = $sql->count([$countFiled]);
        
        $data = $sql->offset($offset)->limit($pageSize)->get($filed);
        // dd(DB::getQueryLog());
        $pageCount = ceil($count/$pageSize); #计算总页面数 
        $result = [];
        $result['data'] = $data;
        $result['count'] = intval($count);
        $result['page'] = intval($pageIndex);
        $result['pageSize'] = intval($pageSize);
        $result['pageCount'] = intval($pageCount);
        return $result;
    }

    /**
     * 连表查询分页
     */
    function joinQueryPage(int $pageSize, int $pageIndex, array $filed, array $where = [], array $joins = []) {
        $offset = ($pageIndex-1) * $pageSize;
        // dd($where);
        // DB::connection()->enableQueryLog();
        $sql = $this->getQueryable($where);
        
        foreach ($joins as $join) {
            $sql = $sql->leftJoin($join[0], $join[1], $join[2], $join[3]);
        }
        
        $count = $sql->count(['*']);
        
        $data = $sql->offset($offset)->limit($pageSize)->get($filed);
        // dd(DB::getQueryLog());
        // dump($count);
        $pageCount = ceil($count/$pageSize); #计算总页面数 
        $result = [];
        $result['data'] = $data;
        $result['count'] = intval($count);
        $result['page'] = intval($pageIndex);
        $result['pageSize'] = intval($pageSize);
        $result['pageCount'] = intval($pageCount);
        return $result;
    }

    /**
     * 处理卡片套餐有效日期  xyh 2019-07-31
     * @param [type] $startTime 开始时间
     * @param [type] $timelength 套餐时长
     * @param [type] $timeUnit  套餐时长单位
     * @param [type] $expireTimes 发卡套餐有效期时长
     * 日：expireTimes/12 * 365 /timelength（年天数待考虑）
     * 月：expireTimes 			/timelength
     * 年：expireTimes/12 	   /timelength
     * @return void
     */
    public function getPackageValidDate($startTime, $timelength, $timeUnit, $expireTimes){
        if($timeUnit == "day"){// 以日为单位
			// 结束时间 = 开始时间+（开通时效*套餐表时长）-1天（减一天是因为第一天已经生效了）
            $allTimeSecond = strtotime($startTime)+($timelength*$expireTimes*3600*24);
            $date = date('Y-m-d',$allTimeSecond-3600*24);
		}else if($timeUnit == "month"){// 以月为单位
            // 结束时间 = 开始时间+（开通时效*套餐表时长）-1天（减一天是因为第一天已经生效了）
            $allMonth = $timelength*$expireTimes;
            $date = date('Y-m-d',strtotime("{$startTime} +{$allMonth} month")-3600*24);
		}
		return $date;
    }

    /**
     * 日志
     * @param [type] $logStr
     * @return void
     */
    public function log($logStr){
        // Linux上要写绝对路径
        // $filename = './logs/cmpp3Submit-' . date('Y-m-d', time()) . '.log';
        if(!is_dir(storage_path('app/logs'))){
            mkdir(storage_path('app/logs'),0777);
        }
        $filename = storage_path('app/logs/applogs-'.date('Y-m-d').'.log');
        file_put_contents($filename, $logStr . PHP_EOL, FILE_APPEND);
    }
    
    
    protected function postApi($url, $data) {
        $req = \Httpful\Request::post($url)
                ->sendsJson()
                ->body(json_encode($data));
        // dump($req->payload);
        $res = $req->send();
        // dump($res);
        $body = $res->body;
        return $body;
    }
}
