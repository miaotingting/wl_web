<?php

namespace App\Http\Models\API;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\Gateway;
use App\Http\Models\Admin\Station;
use Illuminate\Support\Facades\DB;

/**
 * 山东济南移动API
 */
class ChinaMobileAPI_Shandong extends BaseModel
{
    private $host = 'http://223.99.141.141';
    private $port = '10110';

    private $appKey = '';
    private $appSecret = '';
    private $token = '';
    private $station = '';
    private $gateway = '';
    private $groupid = '';

    const MSISDN = "1";
    const SHANDONG_PROVINCEID = '531';

    function __construct(string $stationId, string $gatewayId = ''){

        if (!empty($stationId)) {
            $stationModel = new Station;
            $station = $stationModel->where('id', $stationId)->first();
            $appKey = $station->api_key;
            $appSecret = $station->api_pwd;
            $token = hash('sha256', $appKey . $appSecret);
            $this->appKey = $appKey;
            $this->appSecret = $appSecret;
            $this->token = $token;
            $this->station = $station;
            $this->groupid = $station->sub_api_id;
        }

        if (!empty($gatewayId)) {
            $gatewayModel = new Gateway;
            $gateway = $gatewayModel->where('id', $gatewayId)->first();
            $this->gateway = $gateway;
        }
    }

    /**
     * 发送下行短信(短信sp上给卡号码发送短信)
     *
     * @param string $content 短信内容
     * @param array $dests 接受号码的list或数组
     * @return void
     */
    function sendUpSMS(string $content, array $dests) {
        $url = "$this->host:$this->port/sdiot/smsopen/sendUpSMS";

        // $content = mb_convert_encoding($content, "UTF-16", "UTF-8");
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'spCode' => $this->gateway->sp_code,
            'spId'   => $this->gateway->sp_id,
            'serviceId' => $this->gateway->service_id,
            'loginName' => $this->gateway->sp_id,
            'password' => $this->gateway->shared_secret,
            'msgFmt' => '0',
            'content' => $content,
            'dests' => $dests,
        ];
        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 读取上行短信
     */
    function readDownSMS() {
        $url = "$this->host:$this->port/sdiot/smsopen/readDownSMS";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'spCode' => $this->gateway->sp_code,
            'spId'   => $this->gateway->sp_id,
            'serviceId' => $this->gateway->service_id,
            'loginName' => $this->gateway->sp_id,
            'password' => $this->gateway->shared_secret,
        ];
        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 分页查询短信列表
     */
    function queryOpenSMSList(array $args = []) {
        $url = "$this->host:$this->port/sdiot/smsopen/queryOpenSMSList";

        if (!array_has($args, 'pageSize')) {
            $args['pageSize'] = 10;
        }
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'spCode' => $this->gateway->sp_code,
            'spId'   => $this->gateway->sp_id,
            'serviceId' => $this->gateway->service_id,
            'loginName' => $this->gateway->sp_id,
        ];
        
        dump($args);
        foreach ($args as $key => $arg) {
            $data[$key] = $arg;
        }
        // dd($data);
        dump($data);
        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 省公司短信状态重置
     */
    function resetSMSStatus($qryNum, $provinceid, $numType = 1) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/resetSMSStatus";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'qryNum' => $qryNum,
            'provinceid'   => $provinceid,
            'numType' => $numType,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 省公司用户当月短信查询
     */
    function qrySMSUsedInfo($qryNum, $provinceid, $numType = 1) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qrySMSUsedInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'qryNum' => $qryNum,
            'provinceid'   => $provinceid,
            'numType' => $numType,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     *单卡月短信用量查询
     *
     * @param [type] $telnum 卡片号码
     * @param [type] $date 查询月份
     * @return void
     */
    function qrySms($telnum, $date) {
        $url = "$this->host:$this->port/sdiot/sdiotopen/qrySms";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
            'date'   => $date,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 用户订购套餐查询
     */
    function qrySubsPackage(string $telnum) {
        $url = "$this->host:$this->port/sdiot/bossopen/qrySubsPackage";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 用户套餐余量接口查询【11位调用BOSS】
     */
    function qryRestOfPackageBoss(string $telnum) {
        $url = "$this->host:$this->port/sdiot/bossopen/qryRestOfPackageBoss";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 批量用户套餐余量接口查询
     *
     * @param array $telnums 卡片号码
     * @return void
     */
    function batchQryRestOfPackageBoss(array $telnums) {
        $url = "$this->host:$this->port/sdiot/bossopen/qryRestOfPackageBoss";
        
        $datas = [];
        foreach ($telnums as $telnum) {
            $data = [
                'appkey' => $this->appKey,
                'token'  => $this->token,
                'telnum' => $telnum,
            ];
            $datas[$telnum] = $data;
        }

        $res = $this->piliang_request($url, $datas);
        return $res;
    }

    /**
     * 用户余额查询
     */
    function qrySubsBalance(string $telnum) {
        $url = "$this->host:$this->port/sdiot/bossopen/qrySubsBalance";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 用户套餐余量接口查询【11位调用BOSS/13位调用中移物联网】
     */
    function qryRestOfPackage(string $telnum) {
        $url = "$this->host:$this->port/sdiot/bossopen/qryRestOfPackage";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 实时话费账单查询
     */
    function qryRealTimeBill(string $telnum, $cycleoffset = 0) {
        $url = "$this->host:$this->port/sdiot/bossopen/qryRealTimeBill";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
            'cycleoffset' => $cycleoffset,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 历史账单导出
     */
    function exportOpenApi() {
        $url = "$this->host:$this->port/sdiot/bossopen/exportOpenApi";

        return $url;
    }

    /**
     * 充值缴费
     */
    function recharge() {

    }

    /**
     * 批量充值缴费
     */
    function batchRecharge() {

    }

    /**
     * 单卡日或月账单查询
     */
    function qryBill(string $telnum, string $date) {
        $url = "$this->host:$this->port/sdiot/sdiotopen/qryBill";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
            'date' => $date,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 充值记录查询
     */
    function qryRechargeRecord() {

    }

    /**
     * 充值记录查询文件推送
     */
    function RechargeRecordFile() {

    }

    /**
     * 集团日账单查询文件推送
     */
    function pushBillInfoFile() {

    }

    /**
     * 实时流量使用查询（调用BOSS或者中移物联网接口）
     */
    function qryGPRSRealTimeInfo(string $queryNum, int $numType = 1, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryGPRSRealTimeInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'numType' => $numType,
            'queryNum' => $queryNum,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 省公司客户流量池信息查询
     */
    function qryGPRSPoolInfo() {

    }

    /**
     * 省公司IP地址查询GPRS信息
     */
    function qryGprsInfoByIp($ip, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryGprsInfoByIp";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'ip' => $ip,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 省用户当月GPRS查询
     */
    function qryCurMonthGprsUsedInfo(string $queryNum, int $numType = 1, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryCurMonthGprsUsedInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'numType' => $numType,
            'queryNum' => $queryNum,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    } 

    /**
     * 集团GPRS在线数实时查询
     */
    function qryGprsOnlineCount(string $ecid, int $ecname, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryCurMonthGprsUsedInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'ecid' => $ecid,
            'ecname' => $ecname,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }
    
    /**
     * 用户日流量使用量查询(日用量：根据号码查询当天日期之前的日流量使用量)
     *
     * @param [type] $date 日期：格式为yyyyMMdd，日期不能大于等于当天的日期
     * @param [type] $telnum 用户号码
     * @param string $groupid 集团编号：150278
     * @return void
     */
    function qryDayGprsUsedInfo($date, $telnum, $groupid = '') {
        $url = "$this->host:$this->port/sdiot/sdiotopen/qryDayGprsUsedInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'date' => $date,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 批量查询日用量(日用量：根据号码查询当天日期之前的日流量使用量)
     *
     * @param [type] $date 日期：格式为yyyyMMdd，日期不能大于等于当天的日期
     * @param [type] $telnums 用户号码数组
     * @return void
     */
    function batchQryDayGprsUsedInfo($date, $telnums) {
        $url = "$this->host:$this->port/sdiot/sdiotopen/qryDayGprsUsedInfo";
        
        $datas = [];
        foreach ($telnums as $telnum) {
            $data = [
                'appkey' => $this->appKey,
                'token'  => $this->token,
                'date' => $date,
                'telnum' => $telnum->card_no,
            ];
            $datas[$telnum->card_no] = $data;
        }

        $res = $this->piliang_request($url, $datas);
        return $res;
    }

    /**
     * 用户月流量使用量查询
     */
    function qryMonGprsUsedInfo($date, $telnum, $groupid = '') {
        $url = "$this->host:$this->port/sdiot/sdiotopen/qryMonGprsUsedInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'date' => $date,
            'groupid' => $groupid,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 物联卡GPRS服务开通状况推送
     */
    function gprsInfo() {

    }

    /**
     * 单卡GPRS状态查询
     */
    function gprsStatus($telnum, $groupid) {
        $url = "$this->host:$this->port/sdiot/bossopen/GprsStatus";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'groupid' => $groupid,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 批量GPRS状态查询
     */
    function gprsStatusBatch(array $telnum, $groupid) {
        $url = "$this->host:$this->port/sdiot/bossopen/GprsStatusBatch";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'groupid' => $groupid,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * GPRS状态查询结果查询
     */
    function gprsStatusResult($groupid, $recoid) {
        $url = "$this->host:$this->port/sdiot/sdiotopen/gprsStatusResult";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'groupid' => $groupid,
            'recoid' => $recoid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 实时流量查询
     */
    function qryRealTimeGPRS($groupid, $telnum) {
        $url = "$this->host:$this->port/sdiot/bossopen/qryRealTimeGPRS";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'groupid' => $groupid,
            'telnum' => $telnum,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 批量实时流量查询
     */
    function batchQryRealTimeGPRS($arr) {
        $url = "$this->host:$this->port/sdiot/bossopen/qryRealTimeGPRS";

        $datas = [];
        foreach ($arr as $info) {
            // dd($arr);
            // $cardNo = $info['telnum'];
            $data = [
                'appkey' => $this->appKey,
                'token'  => $this->token,
                'groupid' => $info['groupid'],
                'telnum' => $info['telnum'],
            ];
            $datas[$info['telnum']] = $data;
        }
        // dd($data);
        return $this->piliang_request($url, $datas);
    }

    /**
     * 流量超额查询
     */
    function qryFlowNotInPackage($groupid, $telnum, $cycle) {
        $url = "$this->host:$this->port/sdiot/bossopen/qryFlowNotInPackage";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'groupid' => $groupid,
            'telnum' => $telnum,
            'cycle'  => $cycle,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 单日GPRS使用量推送
     */
    function dayGprsUsedInfoFile() {

    }

    /**
     * 企业内流量池信息推送
     */
    function gprsPoolFile() {

    }

    /**
     * 集团日流量使用量查询文件推送
     */
    function pushDayGprsUsedInfoFile() {

    }

    /**
     * 省公司开关机状态实时查询
     *
     * @param [type] $msisdn 卡号
     * @param integer $provinceid 省份编码
     * @return void 开关机状态：0-关机 1-开机
     */
    function qryOnAndOffRealSingle($msisdn, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryOnAndOffRealSingle";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'msisdn' => $msisdn,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 省公司用户卡状态实时查询（卡片状态查询）
     *
     * @param [type] $msisdn 卡号
     * @param string $provinceid 省地区编号(531)
     * @return void
     */
    function qryCardStatusResult($msisdn, $provinceid = "531") {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryCardStatusResult";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'msisdn' => $msisdn,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 批量省公司用户卡状态实时查询
     */
    function batchQryCardStatusResult(array $msisdns, $callBack = null, $provinceid = "531") {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryCardStatusResult";
        
        $datas = [];
        foreach ($msisdns as $msisdn) {
            $data = [
                'appkey' => $this->appKey,
                'token'  => $this->token,
                'msisdn' => $msisdn,
                'provinceid' => $provinceid,
            ];
            $datas[$msisdn] = $data;
            // array_push(, $data);
        }
        
        // dd($datas);
        $res = $this->piliang_request($url, $datas);
        return $res;
    }

    /**
     * 省公司用户状态信息实时查询
     */
    function qryUserStatusRealSingle($msisdn, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryUserStatusRealSingle";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'msisdn' => $msisdn,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 省公司在线信息实时查询
     */
    function qryGPRSInfo($msisdn, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryGPRSInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'msisdn' => $msisdn,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 物联卡服务状态批量查询
     */
    function batchQryCardServiceStatus(array $queryNum, string $numType = "1", $provinceid = "531") {
        $url = "$this->host:$this->port/sdiot/cmiotopen/batchQryCardServiceStatus";
        dump($this->appKey);
        dump($this->token);
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'numType' => $numType,
            'queryNum' => implode("_", $queryNum),
            'provinceid' => $provinceid,
        ];
        dump($data);
        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 在线状态批量查询
     */
    function batchQryGprsStatus(array $queryNum, int $numType, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/batchQryGprsStatus";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'numType' => $numType,
            'queryNum' => implode("_", $queryNum),
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 集团内漫游卡信息查询
     */
    function roaminfo($telnum, $date, $groupid) {
        $url = "$this->host:$this->port/sdiot/sdiotopen/roaminfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
            'date' => $date,
            'groupid' => $groupid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 集团卡多APN信息查询
     */
    function qryAPNInfo($msisdn, $provinceid = 531) {
        $url = "$this->host:$this->port/sdiot/cmiotopen/qryAPNInfo";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'msisdn' => $msisdn,
            'provinceid' => $provinceid,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 用户申请停开机
     * @param String $telnum 卡号
     * @param String $stoptype 停开机操作类型 StopSubs：申请停机 OpenSubs：申请开机
     * @param Int $needPwd 停开机是否进行身份验证 1验证 0不验证
     * @param String $password 服务密码 needPwd 为1时必填
     * @param String $certid 证件号码 needPwd 为1时必填
     * @param String $changeReason 停开机原因
     * 
     */
    function stopOpenSubs($telnum, $stoptype, $needPwd = 0, $password = '', $certid = '', $changeReason = '') {
        $url = "$this->host:$this->port/sdiot/bossopen/stopOpenSubs";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'telnum' => $telnum,
            'stoptype' => $stoptype,
            'needPwd' => $needPwd,
            'password' => $password,
            'certid' => $certid,
            'changeReason' => $changeReason,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }

    /**
     * 用户申请停开机
     * 
     * @param String $stoptype 停开机操作类型 StopSubs：申请停机 OpenSubs：申请开机
     * @param String $runRule 执行规则：01立即生效02次月生效
     * @param Array $subsList 停开机号码信息列表
     * @param String $subsList.telnum 卡号
     * @param Int $subsList.needPwd 停开机是否进行身份验证 1验证 0不验证
     * @param String $subsList.password 服务密码 needPwd 为1时必填
     * @param String $subsList.certid 证件号码 needPwd 为1时必填
     * @param String $subsList.changeReason 停开机原因
     * 
     */
    function batchStopOpenSubs($subsList, $groupId, $stoptype, $runRule = '01') {
        $url = "$this->host:$this->port/sdiot/bossopen/batchStopOpenSubs";
        
        $data = [
            'appkey' => $this->appKey,
            'token'  => $this->token,
            'stoptype' => $stoptype,
            'groupId' => $groupId,
            'runRule' => $runRule,
            'subsList' => $subsList,
        ];

        $res = $this->postApi($url, $data);
        return $res;
    }
}
