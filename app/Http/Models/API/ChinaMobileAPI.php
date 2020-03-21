<?php

namespace App\Http\Models\API;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\GatewayModel;
use App\Http\Models\Admin\Station;
use Illuminate\Support\Facades\DB;

/**
 * 中国移动基地OneLink接口API
 */
class ChinaMobileAPI extends BaseModel
{
    private $host = 'https://api.iot.10086.cn';

    /**应用编码，第三方应用唯一标识。由物联卡集团客户向中国移动提出API接入申请 中国移动物联网全网管理员在运营管理
     * 平台上分配并反馈给集团客户（反馈方式：邮箱），appid样例：100001*/
    private $appId = '';

    // 密码
    private $appPwd = '';

    /**事务编码，由物联卡集团客户按照相应规则自主生成。生成规则：APPID+YYYYMMDDHHMISS+8位数字序列（此序列由集团客户自主生成，
     * 比如从00000001开始递增等等），transid样例：1000012014101615303080000001*/
    private $transId = '';

    /**令牌，APPID+PASSWORD+TRANSID（PASSWORD同appid、ebid一起由中国移动反馈给集团客户）并使用64位SHA-256算法进行加密，
     * token样例：4962ad69adcbf490c9f749fff734b5706a874ebab1120aaa23c9d288290534ca*/
    private $token = '';

    private $station = '';

    private $getApiData = [];

    function __construct(string $stationId){

        if (!empty($stationId)) {
            $stationModel = new Station;
            $station = $stationModel->where('id', $stationId)->first();
            $appId = $station->api_id;
            $appPwd = $station->api_pwd;
            $randStr = (string)rand(10000000,99999999);
            $transId = $appId . date('YmdHis') . $randStr;
            $token = hash('sha256', $appId . $appPwd . $transId);
            $this->appId = $appId;
            $this->appPwd = $appPwd;
            $this->transId = $transId;
            $this->token = $token;
            $this->station = $station;

            $data['appid'] = $this->appId;
            $data['transid'] = $this->transId;
            $data['token'] = $this->token;
            $this->getApiData = $data;
        }
    }

    
    /*********************************************用户信息类*********************************************/
    /**
     *在线信息实时查询（集团客户根据所属物联卡的码号信息查询该卡的GPRS在线状态、IP地址、APN、RAT信息）
     * @param [type] $cardNo 卡片号码
     * @param [type] $type cardNo卡号、iccid、imsi
     */
    function CMIOT_API2001($cardNo, $type) {
        $ebId = '0001000000008';
        $url = "$this->host/v2/gprsrealsingle";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        if($type == 'cardNo'){
            $url .= "&msisdn=" . $cardNo;
        }else if($type == 'iccid'){
            $url .= "&iccid=" . $cardNo;
        }else if($type == 'imsi'){
            $url .= "&imsi=" . $cardNo;
        }else{
            return '请求参数有误！';
        }
        
        $res = $this->https_request($url);
        return $res;
    }

    /**
     *用户状态信息实时查询（集团客户可根据所属物联卡的码号信息实时查询该卡的状态信息。）
     * @param [type] $cardNo 卡片号码
     * @param [type] $type cardNo卡号、iccid、imsi
     */
    function CMIOT_API2002($cardNo, $type) {
        $ebId = '0001000000009';
        $url = "$this->host/v2/userstatusrealsingle";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        if($type == 'cardNo'){
            $url .= "&msisdn=" . $cardNo;
        }else if($type == 'iccid'){
            $url .= "&iccid=" . $cardNo;
        }else if($type == 'imsi'){
            $url .= "&imsi=" . $cardNo;
        }else{
            return '请求参数有误！';
        }
        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 批量用户状态信息实时查询（集团客户可根据所属物联卡的码号信息实时查询该卡的状态信息。）
     * @param [type] $cardNo 卡片号码
     * @正常使用
     */
    function BATCH_CMIOT_API2002(array $cardNo) {
        $ebId = '0001000000009';
        $url = "$this->host/v2/userstatusrealsingle?";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $urls = [];
        foreach ($cardNo as $cardNo => $card) {
            $data['msisdn'] = $cardNo;
            $urls[$cardNo] = $url . http_build_query($data);
        }
        $res = $this->https_piliang_request($urls);
        return $res;
    }

    /**
     *码号信息查询（根据ICCID、IMSI、MSISDN任意1个码号查询剩余2个码号信息的能力。）
     * @param [type] card_info	所查询用户的msisdn、imsi或iccid	
     * @param [type] $type type	 0—msisdn，1—imsi，2—iccid
     */
    function CMIOT_API2003($cardInfo, $type=0) {
        $ebId = '0001000000010';
        $url = "$this->host/v2/cardinfo";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&card_info=" . $cardInfo;
        $url .= "&type=" . $type;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 批量码号信息查询（根据ICCID、IMSI、MSISDN任意1个码号查询剩余2个码号信息的能力。）
     * @param [array] card_info	所查询用户的msisdn、imsi或iccid	
     * @param [int] $type type	 0—msisdn，1—imsi，2—iccid
     * @正常使用
     */
    function BATCH_CMIOT_API2003(array $cardInfo, $type=0) {
        $ebId = '0001000000010';
        $url = "$this->host/v2/cardinfo?";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['type'] = $type;
        $urls = [];
        foreach ($cardInfo as $cardInfo) {
            $data['card_info'] = $cardInfo;
            $urls[$cardInfo] = $url . http_build_query($data);
        }
        $res = $this->https_piliang_request($urls);
        return $res;
    }

    /**
     *开关机信息实时查询（根据物联卡码号信息实时查询终端开关机状态。）
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2008($msisdn) {
        $ebId = '0001000000025';
        $url = "$this->host/v2/onandoffrealsingle";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 物联卡短信服务开通查询（集团客户可以通过卡号（MSISDN/ICCID/IMSI，单卡）信息查询此卡的短信服务开通状态。）
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2102($msisdn) {
        $ebId = '0001000000429';
        $url = "$this->host/v2/querysmsopenstatus";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 物联卡GPRS服务开通查询（集团客户可以通过卡号（MSISDN/ICCID/IMSI，单卡）信息查询此卡的GPRS服务开通状态。）
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2103($msisdn) {
        $ebId = '0001000000430';
        $url = "$this->host/v2/querygprsopenstatus";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 物联卡APN服务开通查询（集团客户可以通过卡号（MSISDN/ICCID/IMSI，单卡）信息查询此卡的APN服务开通状态。）
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2104($msisdn) {
        $ebId = '0001000000431';
        $url = "$this->host/v2/queryapnopenstatus";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 物联卡生命周期查询（集团客户根据卡号（imsi、msisdn、iccid三个中任意一个），
     * 查询物联卡当前生命周期，生命周期包括：00:正式期，01:测试期，02:沉默期，03:其他。）
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2105($msisdn) {
        $ebId = '0001000000432';
        $url = "$this->host/v2/querycardlifecycle";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 单个用户已开通服务查询（集团客户可以通过卡号（仅MSISDN）查询物联卡当前的服务开通状态 ）
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2107($msisdn) {
        $ebId = '0001000000447';
        $url = "$this->host/v2/useropenservice";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 物联卡开户日期查询（集团客户可以通过API来实现对单个询物联卡基础信息的查询，包括ICCID、MSISDN、IMSI、入网日期（开户日期））
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2110($msisdn) {
        $ebId = '0001000000901';
        $url = "$this->host/v2/querycardopentime";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 码号信息批量查询（集团客户可以根据ICCID、IMSI、MSISDN任意1个码号批量查询剩余2个码号的信息。每次查询不超过100张卡）
     * @param [type] msisdn 卡片号码数组 
     * 所查询的物联卡号码，最长13位数字，举例：14765004176，批量查询多个号码用下划线分隔。例如：xxxx_xxxx_xxxx；
     */
    function CMIOT_API2111($msisdns) {
        $ebId = '0001000000928';
        $msisdn = implode('_',$msisdns);
        $url = "$this->host/v2/batchquerycardinfo";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }

    /**
     * 物联卡生命周期批量查询（集团客户根据卡号（imsi、msisdn、iccid三个中任意一个），批量查询物联卡当前生命周期，
     * 生命周期包括：00:正式期，01:测试期，02:沉默期，03:其他。每次查询不超过100张卡。）
     * @param [type] msisdn 卡片号码数组  所查询的物联卡号码，最长13位数字，
     * 举例：14765004176，批量查询多个号码用下划线分隔。例如：xxxx_xxxx_xxxx；
     */
    function CMIOT_API2112($msisdns) {
        $ebId = '0001000000929';
        $msisdn = implode('_',$msisdns);
        $url = "$this->host/v2/batchquerycardlifecycle";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }
    /*********************************************财务类*********************************************/
    /**
     * 用户余额信息实时查询（集团客户可以查询所属物联卡的实时余额情况，每次查询一张指定物联卡的实时余额）
     * @param [type] msisdn 卡片号码（所查询的物联卡号码，最长13位数字，举例：14765004176。）
     */
    function CMIOT_API2011($msisdn) {
        $ebId = '0001000000035';
        $url = "$this->host/v2/balancerealsingle";
        $url .= "?appid=" . $this->appId;
        $url .= "&transid=" . $this->transId;
        $url .= "&ebid=" . $ebId;
        $url .= "&token=" . $this->token;
        $url .= "&msisdn=" . $msisdn;

        $res = $this->https_request($url);
        return $res;
    }
    /*********************************************用量类*********************************************/

    /**
     * （用户当月gprs查询）集团客户可查询所属物联卡当月截止到前一天24点为止的GPRS使用量（单位：KB）
     * @param [string] $cardNo 卡片号码
     */
    function CMIOT_API2005($cardNo) {
        $ebId = '0001000000012';
        $url = "$this->host/v2/gprsusedinfosingle";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * （用户当月gprs批量查询）集团客户可查询所属物联卡当月截止到前一天24点为止的GPRS使用量（单位：KB）
     * @param [array] $cardNo 卡片号码
     */
    function BATCH_CMIOT_API2005(array $cardNos) {
        $ebId = '0001000000012';
        $url = "$this->host/v2/gprsusedinfosingle?";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $urls = [];
        foreach ($cardNos as $cardNo) {
            $data['msisdn'] = $cardNo;
            $urls[$cardNo] = $url . http_build_query($data);
        }
        $res = $this->https_piliang_request($urls);
        return $res;
    }

    /**
     * （短信批量查询）集团客户可以查询所属物联卡近期短信使用情况，批量查询多个用户、指定日期的短信使用量（仅支持查询最近7天中某一天的数据）
     * @param [array] $cardNo 卡片号码
     * @param [datetime] $queryDate 查询时间（例如：系统当前日期20150421，7日内，即20150414-20150420有效）
     */
    function CMIOT_API2009(array $cardNo, $queryDate) {
        $ebId = '0001000000026';
        $url = "$this->host/v2/batchsmsusedbydate";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $data['query_date'] = $queryDate;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (流量信息批量查询)集团客户可以查询所属物联卡近期GPRS流量使用情况，批量查询多个用户、指定日期的GPRS使用量（仅支持查询最近7天中某一天的数据）
     * @param [array] $cardNo 卡片号码
     * @param [datetime] $queryDate 查询时间（例如：系统当前日期20150421，7日内，即20150414-20150420有效）
     */
    function CMIOT_API2010(array $cardNo, $queryDate) {
        $ebId = '0001000000027';
        $url = "$this->host/v2/batchgprsusedbydate";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $data['query_date'] = $queryDate;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (用户当月短信查询) 集团客户可以查询所属物联卡当月截止到前一天24点为止的短信使用情况，每次查询一张物联卡的当月短信使用量。
     * @param [string] $cardNo 卡片号码
     */
    function CMIOT_API2012($cardNo) {
        $ebId = '0001000000036';
        $url = "$this->host/v2/smsusedinfosingle";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * 批量(用户当月短信查询) 集团客户可以查询所属物联卡当月截止到前一天24点为止的短信使用情况，每次查询一张物联卡的当月短信使用量。
     * @param [array] $cardNo 卡片号码
     */
    function BATCH_CMIOT_API2012(array $cardNos) {
        $ebId = '0001000000036';
        $url = "$this->host/v2/smsusedinfosingle?";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $urls = [];
        foreach($cardNos as $cardNo) {
            $data['msisdn'] = $cardNo;
            $urls[$cardNo] = $url . http_build_query($data);
        }
        
        $res = $this->https_piliang_request($urls);
        return $res;
    }

    /**
     * (用户短信使用查询) 集团客户可以查询所属物联卡某一天的短信使用情况（该时间点最晚比实时早一天）
     * @param [string] $cardNo 卡片号码
     * @param [datetime] $queryDate 查询时间（例如：系统当前日期20150421，7日内，即20150414-20150420有效）
     */
    function CMIOT_API2014($cardNo, $queryDate) {
        $ebId = '0001000000040';
        $url = "$this->host/v2/smsusedbydate";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $data['query_date'] = $queryDate;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * 批量查询物联卡单日短信使用查询(集团客户可以查询所属物联卡某一天的短信使用情况（该时间点最晚比实时早一天）)
     * @param [string] $cardNo 卡片号码
     * @param [datetime] $queryDate 查询时间（例如：系统当前日期20150421，7日内，即20150414-20150420有效）
     * @正常使用
     */
    function BATCH_CMIOT_API2014($cardNos, $queryDate) {
        $ebId = '0001000000040';
        $url = "$this->host/v2/smsusedbydate?";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['query_date'] = $queryDate;
        $urls = [];
        foreach ($cardNos as $cardEntity) {
            if(is_object($cardEntity)){
                $data['msisdn'] = $cardEntity->card_no;
                $urls[$cardEntity->card_no] = $url . http_build_query($data);
            }else{
                $data['msisdn'] = $cardEntity;
                $urls[$cardEntity] = $url . http_build_query($data);
            }
        }
        $res = $this->https_piliang_request($urls);
        return $res;
    }

    /**
     * (套餐内GPRS流量使用情况实时查询 (集团客户)) 集团客户可查询所属物联卡当月套餐内GPRS流量使用情况（若物联卡加入了流量池或流量共享，则返回的是归属流量池或流量共享的使用情况）。
     * @param [string] $cardNo 卡片号码
     */
    function CMIOT_API2020($cardNo) {
        $ebId = '0001000000083';
        $url = "$this->host/v2/gprsrealtimeinfo";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * 批量(套餐内GPRS流量使用情况实时查询 (集团客户)) 集团客户可查询所属物联卡当月套餐内GPRS流量使用情况（若物联卡加入了流量池或流量共享，则返回的是归属流量池或流量共享的使用情况）。
     * @param [array] $cardNo 卡片号码
     * @正常使用
     */
    function BATCH_CMIOT_API2020(array $cardNos) {
        $ebId = '0001000000083';
        $url = "$this->host/v2/gprsrealtimeinfo?";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $urls = [];
        foreach ($cardNos as $cardNo) {
            $data['msisdn'] = $cardNo;
            $urls[$cardNo] = $url . http_build_query($data);
        }
        
        $res = $this->https_piliang_request($urls);
        return $res;
    }

    /**
     * (物联卡单日GPRS使用量查询) 集团客户可以主动查询某张物联卡、某一天的GPRS使用量，单位KB（仅能查询昨天或昨天之前的最近7天的某一天的使用量）
     * @param [string] $cardNo 卡片号码
     * @param [datetime] $queryDate 查询时间（例如：系统当前日期20150421，7日内，即20150414-20150420有效）
     */
    function CMIOT_API2300($cardNo, $queryDate) {
        $ebId = '0001000000407';
        $url = "$this->host/v2/gprsusedinfosinglebydate";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $data['queryDate'] = $queryDate;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * 批量查询物联卡单日GPRS使用量查询(集团客户可以主动查询某张物联卡、某一天的GPRS使用量，单位KB)
     * （仅能查询昨天或昨天之前的最近7天的某一天的使用量）
     * @param [string] $cardNo 卡片号码数组
     * @param [datetime] $queryDate 查询时间（例如：系统当前日期20150421，7日内，即20150414-20150420有效）
     * @正常使用
     */
    function BATCH_CMIOT_API2300($cardNos, $queryDate) {
        $ebId = '0001000000407';
        $url = "$this->host/v2/gprsusedinfosinglebydate?";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['queryDate'] = $queryDate;
        $urls = [];
        foreach ($cardNos as $cardEntity) {
            if(is_object($cardEntity)){
                $data['msisdn'] = $cardEntity->card_no;
                $urls[$cardEntity->card_no] = $url . http_build_query($data);
            }else{
                $data['msisdn'] = $cardEntity;
                $urls[$cardEntity] = $url . http_build_query($data);
            }
        }
        $res = $this->https_piliang_request($urls);
        return $res;
    }

    /**
     * (物联卡当月套餐内短信信息批量查询) 集团客户根据卡号（IMSI、MSISDN、ICCID三个中任意一个），批量按套餐查询当月物联卡短信信息（如果今日是当月第一天，则查询上月全月短信信息；其他情况下，查询本月截止到T-1日24:00之前的短信信息，不需传入日期，默认T为查询当日，日期不可选择）。每次查询能力最大不超过100张卡。
     * @param [array] $cardNo 卡片号码
     */
    function CMIOT_API2302(array $cardNo) {
        $ebId = '0001000000930';
        $url = "$this->host/v2/batchquerymonthsmsinfo";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡当月流量共享使用信息实时查询) 集团客户可以通过卡号（msisdn\iccid\imsi，单卡）查询集团归属物联卡当月流量共享的实时使用情况。
     * @param [string] $cardNo 卡片号码
     */
    function CMIOT_API2115($cardNo) {
        $ebId = '0001000008899';
        $url = "$this->host/v2/querygprsshareused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡当月流量共享使用信息实时批量查询) 集团客户可以通过卡号（msisdn\iccid\imsi，不超过20张卡）查询集团归属物联卡当月流量共享的实时使用情况。
     * @param [array] $cardNo 卡片号码
     */
    function CMIOT_API2116(array $cardNo) {
        $ebId = '0001000008900';
        $url = "$this->host/v2/batchquerygprsshareused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡单月GPRS使用量查询) 集团客户可以通过卡号（msisdn\iccid\imsi，单卡）查询集团归属物联卡指定月份的GPRS使用量（仅支持查询最近6个月的数据，不含当月）
     * @param [string] $cardNo 卡片号码
     * @param [date] $month 必须为不含当月的最近6个月，格式为yyyymm
     */
    function CMIOT_API2306($cardNo, $month) {
        $ebId = '0001000008901';
        $url = "$this->host/v2/cardsinglemonthgprsused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $data['queryMonth'] = $month;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡单月GPRS使用量批量查询) 集团客户可以通过卡号（msisdn\iccid\imsi，不超过100张卡）查询集团归属物联卡指定月份的GPRS使用量（仅支持查询最近6个月的数据，不含当月）
     * @param [array] $cardNo 卡片号码
     * @param [datetime] $month 必须为不含当月的最近6个月，格式为yyyymm
     * @正常使用
     */
    function CMIOT_API2307(array $cardNo, $month) {
        $ebId = '0001000008902';
        $url = "$this->host/v2/batchcardsinglemonthgprsused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $data['queryMonth'] = $month;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡单月短信使用量查询) 集团客户可以通过卡号（msisdn\iccid\imsi，单卡）查询集团归属物联卡指定月份的短信使用量（仅支持查询最近6个月的数据，不含当月）
     * @param [string] $cardNo 卡片号码
     * @param [datetime] $month 必须为不含当月的最近6个月，格式为yyyymm
     */
    function CMIOT_API2310($cardNo, $month) {
        $ebId = '0001000016895';
        $url = "$this->host/v2/cardsinglemonthsmsused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $data['queryMonth'] = $month;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡单月短信使用量批量查询) 集团客户可以通过卡号（msisdn\iccid\imsi，不超过100张卡）查询集团归属物联卡指定月份的短信使用量（仅支持查询最近6个月的数据，不含当月）
     * @param [array] $cardNo 卡片号码
     * @param [datetime] $month 必须为不含当月的最近6个月，格式为yyyymm
     * @正常使用
     */
    function CMIOT_API2311(array $cardNo, $month) {
        $ebId = '0001000016896';
        $url = "$this->host/v2/batchcardsinglemonthsmsused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $data['queryMonth'] = $month;
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡当月GPRS使用量批量查询) 集团客户可以根据卡号（IMSI、MSISDN、ICCID三个中任意一个）批量查询所属物联卡当月截止到前一天24点为止的GPRS使用量（单位：KB）。每次查询不超过30张卡
     * @param [array] $cardNo 卡片号码
     */
    function CMIOT_API2317(array $cardNo) {
        $ebId = '0001000024901';
        $url = "$this->host/v2/batchcurrentmonthgprsused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡当月短信使用量批量查询) 集团客户可以根据卡号（IMSI、MSISDN、ICCID三个中任意一个）批量查询所属物联卡当月截止到前一天24点为止的短信使用量。每次查询不超过30张卡
     * @param [array] $cardNo 卡片号码
     */
    function CMIOT_API2318(array $cardNo) {
        $ebId = '0001000024902';
        $url = "$this->host/v2/batchcurrentmonthsmsused";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $res = $this->getApi($url, $data);
        return $res;
    }

    /**
     * (物联卡当月套餐内GPRS流量信息批量查询) 集团客户根据卡号（IMSI、MSISDN、ICCID三个中任意一个），批量按套餐查询当月物联卡GPRS流量信息（如果今日是当月第一天，则查询上月全月GPRS信息；其他情况下，查询本月截止到T-1日24:00的GPRS信息，不需传入日期，默认T为查询当日，日期不可选择）。每次查询不超过30张卡
     * @param [array] $cardNo 卡片号码
     */
    function CMIOT_API2319(array $cardNo) {
        $ebId = '0001000024903';
        $url = "$this->host/v2/batchcurrentmonthgprsinfo";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdns'] = implode("_",$cardNo);
        $res = $this->getApi($url, $data);
        return $res;
    }
    /*********************************************套餐类*********************************************/

    /**
     * (物联卡资费套餐查询) 集团客户可以根据物联卡码号信息查询该卡的套餐信息
     * @param [string] $cardNo 卡片号码
     */
    function CMIOT_API2037($cardNo) {
        $ebId = '0001000000264';
        $url = "$this->host/v2/querycardprodinfo";
        $data = $this->getApiData;
        $data['ebid'] = $ebId;
        $data['msisdn'] = $cardNo;
        $res = $this->getApi($url, $data);
        return $res;
    }
    /*********************************************短信类*********************************************/


}
