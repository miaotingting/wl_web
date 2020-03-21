<?php

namespace App\Http\Models\API;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\GatewayModel;
use App\Http\Models\Admin\Station;
use Illuminate\Support\Facades\DB;

/**
 * 中国移动基地OneLink接口API
 */
class NewChinaMobileAPI extends BaseModel
{
    private $host = 'https://api.iot.10086.cn/v5';

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

    const GET_METHOD = 'get';
    const POST_METHOD = 'post';
    const BATCH_POST_METHOD = 'batchPost';

    function __construct(string $stationId){

        if (!empty($stationId)) {
            $stationModel = new Station;
            $station = $stationModel->where('id', $stationId)->first();
            $appId = $station->api_id;
            $appPwd = $station->api_pwd;
            $randStr = (string)rand(10000000,99999999);
            $transId = $appId . date('YmdHis') . $randStr;

            $this->appId = $appId;
            $this->appPwd = $appPwd;
            $this->transId = $transId;
            // $token = $this->getToken();
            $this->token = '';
            $this->host = $station->api_url;
            $this->station = $station;
        }
    }

    /**
     * 获取token
     * @return string token
     */
    function getToken():string {
        $url = "$this->host/ec/get/token";
        $data = [
            'appid' => $this->appId,
            'password' => $this->appPwd,
            'transid' => $this->transId,
        ];
        $res = parent::getApi($url, $data);
        return $res->result[0]->token;
    }

    
    /***************************************用户信息类***************************************/
    /**
     * 单卡基本信息查询
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{msisdn:卡号,imsi:imsi, iccid:iccid, activeDate:激活日期（首次）,openDate:开卡时间}]}
     */
    function CMIOT_API23S00(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-basic-info";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡状态变更历史查询(通过卡号查询物联卡的状态变更历史)
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{changeHistoryList:[{descStatus:原状态,targetStatus:目标状态, changeDate:变更时间},...]}]}
     * 返回状态说明
     * 1：待激活
     * 2：已激活
     * 4：停机
     * 6：可测试
     * 7：库存
     * 8：预销户
     * 9：已销户
     */
    function CMIOT_API23S02(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-change-history";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':   
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡状态变更(集团客户可以通过卡号（msisdn\iccid\imsi三选一，单卡）变更集团归属物联卡的状态（同一卡号30分钟内不能通过此类接口重复办理业务）)
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @param Int    $operType 0:申请停机(已激活转已停机) 1:申请复机(已停机转已激活) 2:库存转已激活 3:可测试转库存 4:可测试转待激活 5:可测试转已激活 6:待激活转已激活
     * @return Object {status:0, message: "正确", result:[{msisdn:卡号|imsi:imsi|iccid:iccid}]}
     */
    function CMIOT_API23S03(string $cardNo, $operType, $type = 'cardNo') {
        $url = "$this->host/ec/change/sim-status";
        $data = [];
        $data['transid'] = $this->transId;
        $data['operType'] = $operType;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡绑定IMEI实时查询(通过卡号查询物联卡绑定的IMEI信息)
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{imei:imei}]}
     */
    function CMIOT_API23S04(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-imei";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡状态变更批量办理(集团客户可以通过卡号调用该接口批量办理物联卡的状态变更，每次不超过100张卡，同一卡号30分钟内不得重复调用该接口（批量办理中若一批有10个卡号，其中有一个卡号是在30分钟内有成功调用的，则这次不允许调用，这一批卡号中其余9个卡号本次调用失败，不记录在30分钟内有调用的记录中）。如需查询办理结果则根据该接口返回的任务流水号调“CMIOT_API23M10-物联卡业务批量办理结果查询”接口查询办理结果)
     * @param array $cardNo 卡片号码
     * @param String $operType 操作类型：1 可测试->库存；2可测试->待激活；3可测试->已激活；4库存->待激活5库存->已激活6 待激活->库存7待激活->已激活8 待激活->已停机（暂不支持）9已激活->已停机10已停机->待激活（暂不支持）11 已停机->已激活
     * @param String $reason 停复机原因：在operType为9或11时，原因必传 01：主动停复机
     * @return Object {status:0, message: "正确", result:[{jobId:任务流水号}]}
     */
    function CMIOT_API23S06(array $cardNo, $operType, $reason = '') {
        $url = "$this->host/ec/change/sim-status/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['msisdns'] = implode('_',$cardNo);
        $data['operType'] = $operType;
        $data['reason'] = $reason;
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡状态查询（通过卡号查询物联卡的状态信息）
     * @param String $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{cardStatus当开卡平台为OneLink-PB时：00-正常；01-单向停机；02-停机；03-预销号；04-销号；05-过户；06-休眠；07-待激活；99-号码不存在当开卡平台为OneLink-CT时：1：待激活2：已激活4：停机6：可测试7：库存8：预销户9：已销户，lastChangeDate：最后一次变更时间}]}
     */
    function CMIOT_API25S04(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-status";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data);
    }

    /**
     * 批量状态查询（通过卡号查询物联卡的状态信息）
     * @param Array $cardNos 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{cardStatus当开卡平台为OneLink-PB时：00-正常；01-单向停机；02-停机；03-预销号；04-销号；05-过户；06-休眠；07-待激活；99-号码不存在当开卡平台为OneLink-CT时：1：待激活2：已激活4：停机6：可测试7：库存8：预销户9：已销户，lastChangeDate：最后一次变更时间}]}
     */
    function BATCH_CMIOT_API25S04(array $cardNos, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-status";
        $data = [];
        
        $data['transid'] = $this->transId;
        $datas = [];
        switch($type) {
            case 'cardNo':
                foreach($cardNos as $cardNo => $card) {
                    $data['msisdn'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
            case 'iccid':
                foreach($cardNos as $cardNo => $card) {
                    $data['iccid'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
            case 'imsi':
                foreach($cardNos as $cardNo => $card) {
                    $data['imsi'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
            default:
                foreach($cardNos as $cardNo => $card) {
                    $data['msisdn'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
        }
        
        
        return $this->fetchApi($url, $datas, self::BATCH_POST_METHOD);
    }
    /**
     * 码号信息批量查询（根据ICCID、IMSI、MSISDN任意1个码号批量查询剩余2个码号的信息。每次查询不超过100张卡）
     * @param array $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API25S05(array $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-card-info/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_', $cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_', $cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_', $cardNo);
                break;
            default:
                $data['msisdns'] = implode('_', $cardNo);
                break;
        }
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡归属平台批量查询（批量查询物联卡对应的OneLink管理平台）
     * @param array $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API25S06(array $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-platform/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_', $cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_', $cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_', $cardNo);
                break;
            default:
                $data['msisdns'] = implode('_', $cardNo);
                break;
        }
        return $this->fetchApi($url, $data);
    }

    /*********************************************财务类*********************************************/

    /**
     * 集团客户账单实时查询（查询集团客户账单信息）
     * @param date $queryDate 指定月份，YYYYMM格式,只能查询当前月的前6个月账单
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23B00($queryDate) {
        $url = "$this->host/ec/query/ec-bill";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡余额信息实时查询（查询物联卡余额信息）
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23B01(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/balance-info";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡账户充值交费（集团客户可以通过卡号（msisdn）或账户ID获得充值交费的链接地址，进而对物联卡或账户进行充值交费）
     * @param int $entityType 充值标识类型： 1：msisdn 2：账户Id
     * @param int $entityId 充值标识类型对应号码 
     * @param int $chargeMoney 充值金额 单位：元(1.00元—500.00元之间)
     * @param int $paymentType 支付方式：ALIPAY-WAP:支付宝-手机网站支付 ALIPAY-WEB:支付宝-网页即时到账支付 ALIPAY-BANK:网银支付 WEIXIN-JSAPI:微信-公众号支付 WEIXIN-NATIVE:微信-扫码支付
     * @param int $returnUrl 支付成功后跳转页面的URL，无该参数则不跳转
     * @param int $defaultBank 默认网银，在PaymentType为 ALIPAY-BANK（网银支付）时必填。 枚举值参见下表“支持的银行及简码”
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23B02($entityId, $entityType, $chargeMoney, $paymentType, $defaultBank = '', $returnUrl = '') {
        $url = "$this->host/ec/recharge/sim-account";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['entityType'] = $entityType;
        $data['entityId'] = $entityId;
        $data['chargeMoney'] = $chargeMoney;
        $data['paymentType'] = $paymentType;
        // if (!empty($returnUrl)) {
            $data['returnUrl'] = $returnUrl;
        // }
        // if (!empty($defaultBank)) {
            $data['defaultBank'] = $defaultBank;
        // }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡账户充值交费结果查询(集团客户发起充值缴费之后，可通过订单号或充值时间段查询充值缴费的结果)
     * @param int $startNum 起始页，从1开始
     * @param int $pageSize 每页显示记录数，不超过50
     * @param array $params orderNo 充值订单号 startTime 订单开始时间 yyyyMMddHHmmss endTime 订单结束时间
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23B03(int $startNum, int $pageSize, array $params) {
        $url = "$this->host/ec/query/sim-account-recharge-result";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['startNum'] = $startNum;
        $data['pageSize'] = $pageSize;
        if (array_has($params, 'orderNo')) {
            //没传时间用订单号
            $data['orderNo'] = $params['orderNo'];
        } else if (array_has($params, 'startTime') && array_has($params, 'endTime')) {
            //传了时间用时间
            $data['startTime'] = $params['startTime'];
            $data['endTime'] = $params['endTime'];
        }
        
        return $this->fetchApi($url, $data);
    }

    /*********************************************用量类*********************************************/

    /**
     * 群组本月流量累计使用量实时查询(实时查询群组本月GPRS流量累计使用量（若群组有多个流量池商品，使用量为多个商品累加）)
     * @param string $groupId 群组ID
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U00($groupId) {
        $url = "$this->host/ec/query/group-data-usage";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['groupId'] = $groupId;
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡本月语音累计使用量实时查询(实时查询物联卡本月语音累计使用量)
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U01(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-voice-usage";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 群组本月套餐内流量使用量实时查询(实时查询群组本月套餐内GPRS流量使用量信息)
     * @param string $groupId 群组ID
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U04($groupId) {
        $url = "$this->host/ec/query/group-data-margin";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['groupId'] = $groupId;
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡本月套餐内语音使用量实时查询(实时查询物联卡本月套餐内语音使用量)
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U05(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-voice-margin";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡本月套餐内短信使用量实时查询(实时查询物联卡本月套餐内短信使用量)
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U06(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-sms-margin";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡本月套餐内流量使用量实时查询(实时查询物联卡本月套餐内流量使用量)
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U07(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-data-margin";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡单日语音使用量批量查询(批量（100张）查询物联卡某一天语音使用量（仅支持查询近7天中某一天的数据，截止前一天）)
     * @param array $cardNo 卡片号码
     * @param date $queryDate 查询具体某一天时间。当前时间前一天开始的7日内。日期格式为yyyyMMdd
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U08(array $cardNo, $queryDate, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-voice-usage-daily/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_',$cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_',$cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_',$cardNo);
                break;
            default:
                $data['msisdns'] = implode('_',$cardNo);
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡单月语音使用量批量查询(批量（100张）查询物联卡指定月份的语音使用量，仅支持查询最近6个月中某月的使用量，其中本月数据截止为前一天)
     * @param array $cardNo 卡片号码
     * @param date $queryDate 查询最近6个月中的某月，其中本月数据截止为前一天，日期格式为yyyyMM
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U09(array $cardNo, $queryDate, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-voice-usage-monthly/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_',$cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_',$cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_',$cardNo);
                break;
            default:
                $data['msisdns'] = implode('_',$cardNo);
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡流量池内使用量实时查询(集团客户可通过该接口查询单卡在流量池或流量池共享下实时使用量)
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23U12(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-data-usage-inpool";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡单日短信使用量批量查询(批量（100张）查询物联卡某一天短信使用量（仅支持查询近7天中某一天的数据，截止前一天）)
     * @param array $cardNo 卡片号码
     * @param date $queryDate 查询具体某一天时间。当前时间前一天开始的7日内。日期格式为yyyyMMdd
     * @param String String $type cardNo卡号、iccid、imsi
     * @return smsAmount       单日短信用量信息 
     *          msisdn/imsi/iccid	返回查询卡号
     *          sms                 指定日期的短信条数（条）
     */
    function CMIOT_API25U00(array $cardNo, $queryDate, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-sms-usage-daily/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_',$cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_',$cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_',$cardNo);
                break;
            default:
                $data['msisdns'] = implode('_',$cardNo);
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡单日GPRS流量使用量批量查询(批量（100张）查询物联卡某一天GPRS流量使用量（仅支持查询近7天中某一天的数据，截止前一天）)
     * @param array $cardNo 卡片号码
     * @param date $queryDate 查询具体某一天时间。当前时间前一天开始的7日内。日期格式为yyyyMMdd
     * @param String String $type cardNo卡号、iccid、imsi
     * @return dataAmountList   单日数据用量信息  
     *          msisdn/imsi/iccid	返回查询卡号
     *          dataAmount          指定日期的数据使用量（KB）
     *          apnDataAmountList	分APN的数据使用情况列表；dataAmount为0则不返回
     *          apnName	            Apn名称
     *          apnDataAmount	    Apn使用量（KB）
     */
    function CMIOT_API25U01(array $cardNo, $queryDate, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-data-usage-daily/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_',$cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_',$cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_',$cardNo);
                break;
            default:
                $data['msisdns'] = implode('_',$cardNo);
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡单月短信使用量批量查询(批量（100张）查询物联卡指定月份的短信使用量，仅支持查询最近6个月中某月的使用量，其中本月数据截止为前一天)
     * @param array $cardNo 卡片号码
     * @param date $queryDate 查询最近6个月中的某月，其中本月数据截止为前一天，日期格式为yyyyMM
     * @param String String $type cardNo卡号、iccid、imsi
     * @return smsAmount       单月短信用量信息 
     *          msisdn/imsi/iccid	返回查询卡号
     *          sms                 指定日期的短信条数（条）
     */
    function CMIOT_API25U02(array $cardNo, $queryDate, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-sms-usage-monthly/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_',$cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_',$cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_',$cardNo);
                break;
            default:
                $data['msisdns'] = implode('_',$cardNo);
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡单月GPRS流量使用量批量查询(批量（100张）查询物联卡指定月份的GPRS流量使用量，仅支持查询最近6个月中某月的使用量，其中本月数据截止为前一天)
     * @param array $cardNo 卡片号码
     * @param date $queryDate 查询最近6个月中的某月，其中本月数据截止为前一天，日期格式为yyyyMM
     * @param String String $type cardNo卡号、iccid、imsi
     * @return dataAmountList   单月数据用量信息  
     *          msisdn/imsi/iccid	返回查询卡号
     *          dataAmount          指定月份的数据使用量（KB）
     *          apnDataAmountList	分APN的数据使用情况列表；dataAmount为0则不返回
     *          apnName	            Apn名称
     *          apnDataAmount	    Apn使用量（KB）
     */
    function CMIOT_API25U03(array $cardNo, $queryDate, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-data-usage-monthly/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        switch($type) {
            case 'cardNo':
                $data['msisdns'] = implode('_',$cardNo);
                break;
            case 'iccid':
                $data['iccids'] = implode('_',$cardNo);
                break;
            case 'imsi':
                $data['imsis'] = implode('_',$cardNo);
                break;
            default:
                $data['msisdns'] = implode('_',$cardNo);
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 单卡本月流量累计使用量查询(查询集团所属物联卡当月的GPRS使用量，PB号段为截至前一天24点流量，CT号段为实时流量。（单位：KB）)
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API25U04(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-data-usage";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 批量单卡本月流量累计使用量查询(查询集团所属物联卡当月的GPRS使用量，PB号段为截至前一天24点流量，CT号段为实时流量。（单位：KB）)
     * @param array $cardNos 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function BATCH_CMIOT_API25U04(array $cardNos, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-data-usage";
        $data = [];
        
        $data['transid'] = $this->transId;
        $datas = [];
        switch($type) {
            case 'cardNo':
                foreach($cardNos as $cardNo) {
                    $data['msisdn'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
            case 'iccid':
                foreach($cardNos as $cardNo) {
                    $data['iccid'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
            case 'imsi':
                foreach($cardNos as $cardNo) {
                    $data['imsi'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
            default:
                foreach($cardNos as $cardNo) {
                    $data['msisdn'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
        }
        
        return $this->fetchApi($url, $datas, self::BATCH_POST_METHOD);
    }

    /**
     * 单卡本月短信累计使用量查询(查询集团所属物联卡当月短信使用情况，PB号段为截至前一天24点短信用量，CT号段为实时短信用量。（单位：条）)
     * @param string $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API25U05(string $cardNo, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-sms-usage";
        $data = [];
        
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 批量单卡本月短信累计使用量查询(查询集团所属物联卡当月短信使用情况，PB号段为截至前一天24点短信用量，CT号段为实时短信用量。（单位：条）)
     * @param array $cardNo 卡片号码
     * @param String String $type cardNo卡号、iccid、imsi
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function BATCH_CMIOT_API25U05(array $cardNos, $type = 'cardNo') {
        $url = "$this->host/ec/query/sim-sms-usage";
        $data = [];
        
        $data['transid'] = $this->transId;
        $datas = [];
        switch($type) {
            case 'cardNo':
                foreach ($cardNos as $cardNo) {
                    $data['msisdn'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
            case 'iccid':
                foreach ($cardNos as $cardNo) {
                    $data['iccid'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
               
                break;
            case 'imsi':
                foreach ($cardNos as $cardNo) {
                    $data['imsi'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                
                break;
            default:
                foreach ($cardNos as $cardNo) {
                    $data['msisdn'] = $cardNo;
                    $datas[$cardNo] = $data;
                }
                break;
        }
        
        return $this->fetchApi($url, $datas, self::BATCH_POST_METHOD);
    }

    /*********************************************套餐类*********************************************/

    /**
     * 资费订购实时查询(根据用户类型（企业、群组、sim卡）查询已订购的所有资费列表)
     * @param string $cardNo 物联卡号码，queryType取值为3时传入
     * @param int $queryType 查询场景标识类型 1：客户接入类型 2：群组接入类型 3：sim接入类型
     * @param String $groupId 接入群组编号，queryType取值为2时传入
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23R00(string $cardNo,int $queryType = 3, $groupId = '') {
        $url = "$this->host/ec/query/ordered-offerings";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryType'] = $queryType;
        switch($queryType) {
            case 3:
                $data['msisdn'] = $cardNo;
                break;
            case 2:
                $data['groupId'] = $groupId;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 资费详情实时查询(查询指定资费的详细信息)
     * @param string $offeringId 资费ID
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23R01(string $offeringId) {
        $url = "$this->host/ec/query/offerings-detail";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['offeringId'] = $offeringId;
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 可订购资费实时查询(查询指定用户类型（企业、群组、sim卡）可订购的所有附属资费列表)
     * @param string $cardNo 物联卡号码，queryType取值为3时传入
     * @param int $queryType 查询场景标识类型 1：集团客户资费 2：集团群组附属资费 3：集团用户附属资费
     * @param String $groupId 接入群组编号，queryType取值为2时传入
     * @param int pageSize 每次查询查询数目，不超过50条
     * @param int startNum 开始页，从1开始
     * @param int catalogId 目录id，详细信息参考接口CMIOT_API23R07目录节点实时查询
     * @param int categoryId 节点id，多个节点用下划线隔开，例如XXXX_XXXX。详细信息参考接口CMIOT_API23R07目录节点实时查询
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23R02(string $cardNo,$pageSize, $startNum, $catalogId, $categoryId,int $queryType = 3, $groupId = '') {
        $url = "$this->host/ec/query/purchasable-offerings";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryType'] = $queryType;
        $data['pageSize'] = $pageSize;
        $data['startNum'] = $startNum;
        $data['catalogId'] = $catalogId;
        $data['categoryId'] = $categoryId;
        switch($queryType) {
            case 3:
                $data['msisdn'] = $cardNo;
                break;
            case 2:
                $data['groupId'] = $groupId;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 可变更资费实时查询(查询指定用户类型（群组、sim卡）可变更的所有附属资费列表)
     * @param string $cardNo 物联卡号码，queryType取值为2时传入
     * @param int $queryType 查询场景标识类型 1：集团群组附属资费 2：集团用户附属资费
     * @param String $groupId 接入群组编号，queryType取值为1时传入
     * @param int pageSize 每次查询查询数目，不超过50条
     * @param int startNum 开始页，从1开始
     * @param int descOfferingId 原资费ID
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23R03(string $cardNo,$pageSize, $startNum, $descOfferingId,int $queryType = 2, $groupId = '') {
        $url = "$this->host/ec/query/changeable-offerings";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryType'] = $queryType;
        $data['pageSize'] = $pageSize;
        $data['startNum'] = $startNum;
        $data['descOfferingId'] = $descOfferingId;
        switch($queryType) {
            case 2:
                $data['msisdn'] = $cardNo;
                break;
            case 1:
                $data['groupId'] = $groupId;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 目录节点实时查询(根据不同应用场景查询资费的目录节点)
     * @param int $queryType 查询场景标识类型 1：集团客户资费 2：集团群组附属资费 3:个人(集团用户)附属资费
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23R07(int $queryScenes) {
        $url = "$this->host/ec/query/categories";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['queryScenes'] = $queryScenes;
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡流量叠加包订购(集团客户可以通过卡号调用该接口办理订购流量叠加包业务，当前支持10元100M，30元500M（同一卡号30分钟内不能通过此类接口重复办理业务）)
     * @param string $cardNo 物联卡号，最长13位数字，举例：14765004176。
     * @param int $maincommoDity 加油包主商品： 0：物联卡个人（11000001） 1：车联网个人（11100001）
     * @param int $packageType 加油包套餐：0:流量加油包 10元套餐（物联卡，100M） 1:流量加油包10元套餐（车联网，100M）2:流量加油包 30元套餐（物联卡，500M） 3:流量加油包30元套餐（车联网，500M）当Maincommodity=0时，Package只能是取0和2；当Maincommodity=1时，Package只能是取1和3；
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23R08(string $cardNo, int $maincommoDity, $packageType) {
        $url = "$this->host/ec/order/gprspackage-order";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['msisdn'] = $cardNo;
        $data['maincommoDity'] = $maincommoDity;
        $data['packageType'] = $packageType;
        
        return $this->fetchApi($url, $data);
    }

    /**
     * 物联卡流量叠加包批量订购(集团客户可通过物联卡卡号调用该接口实现批量订购流量叠加包，每次不超过100张卡，同一卡号三十分钟内不能重复调用该接口（批量办理中若一批有10个卡号，其中有一个卡号是在三十分钟内有成功调用的，则这次不允许调用，这一批卡号中其余9个卡号本次调用失败，这9个卡号不记录在三十分钟内有调用的记录中）。如需查询办理结果则根据该接口返回的任务流水号调“CMIOT_API23M10-物联卡业务批量办理结果查询”接口查询办理结果)
     * @param array $cardNo 物联卡号，最长13位数字，举例：14765004176。
     * @param int $maincommoDity 加油包主商品： 0：物联卡个人（11000001） 1：车联网个人（11100001）
     * @param int $packageType 加油包套餐：0:流量加油包 10元套餐（物联卡，100M） 1:流量加油包10元套餐（车联网，100M）2:流量加油包 30元套餐（物联卡，500M） 3:流量加油包30元套餐（车联网，500M）当Maincommodity=0时，Package只能是取0和2；当Maincommodity=1时，Package只能是取1和3；
     * @return Object {status:0, message: "正确", result:[{}]}
     */
    function CMIOT_API23R09(array $cardNo, int $maincommoDity, $packageType) {
        $url = "$this->host/ec/order/gprspackage-order/batch";
        $data = [];
        
        $data['transid'] = $this->transId;
        $data['msisdns'] = implode('_', $cardNo);
        $data['maincommoDity'] = $maincommoDity;
        $data['packageType'] = $packageType;
        
        return $this->fetchApi($url, $data);
    }

    

    /***************************************通信类***************************************/
    /**
     * 单卡开关机状态实时查询(查询终端的开关机信息。)0:关机    1:开机
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @return status 终端的开关机状态：0:关机  1:开机
     */
    function CMIOT_API25M00(string $cardNo, $type) {
        $url = "$this->host/ec/query/on-off-status";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 查询物联卡的在线信息，区分APN，返回APN信息、IP地址、会话创建时间。
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @return 在线状态status[00:离线  01：在线]    接入方式rat[1:3G  2:2G  6:4G  8:NB]
     */
    function CMIOT_API25M01(string $cardNo, $type) {
        $url = "$this->host/ec/query/sim-session";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 物联卡短信状态重置（重置HLR上短信的状态，以保证短信正常使用）
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @return Object {"status":"0","message":"正确","result":[]}
     */
    function CMIOT_API25M02(string $cardNo, $type) {
        $url = "$this->host/ec/reset/sim-sms-status";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 物联卡GPRS上网功能重置(在集团客户遇到物联卡上网功能异常的时候可以通过调用该接口来重置恢复)
     * @param String $cardNo 卡片号码
     * @param String $type cardNo卡号、iccid、imsi
     * @return Object {"status":"0","message":"正确","result":[]}
     */
    function CMIOT_API25M03(string $cardNo, $type) {
        $url = "$this->host/ec/operate/sim-gprs-status-reset";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 集团客户短信白名单查询(查询企业客户的短信白名单列表)
     * @param string $cardNo 卡片号码
     * @param [type] $type cardNo卡号、iccid、imsi
     * @param [type] $pageSize 每页查询记录数，不超过50
     * @param [type] $startNum 开始页
     * @return void flag[是否最后一页  Y:是  N:否]
     */
    function CMIOT_API23M02(string $cardNo, $type, $pageSize, $startNum) {
        $url = "$this->host/ec/query/ec-message-white-list";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        $data['pageSize'] = $pageSize;
        $data['startNum'] = $startNum;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 单卡已开通APN信息查询(查询SIM卡已开通APN服务的信息)
     * @param string $cardNo 卡片号码
     * @param [type] $type cardNo卡号、iccid、imsi
     * @return void status [状态0:暂停  1:恢复(或正常)]
     */
    function CMIOT_API23M03(string $cardNo, $type) {
        $url = "$this->host/ec/query/apn-info";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 单卡GPRS流量自主限速办理(集团客户通过此接口办理物联卡流量限速)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @param [type] $apnName  apn名称用户订购客户自发起降速商品的商品ID
     * @param [type] $serviceUsageState   业务配额状态:
     *          1:速率恢复
     *          91:APN-AMBR=2Mbps（月初不自动恢复）
     *          92:APN-AMBR=1Mbps（月初不自动恢复
     *          93:APN-AMBR=512Kbps（月初不自动恢复)
     *          94:APN-AMBR=128Kbps（月初不自动恢复)
     * @return void
     */
    function CMIOT_API23M04(string $cardNo, $type, $apnName, $serviceUsageState) {
        $url = "$this->host/ec/operate/network-speed";
        $data = [];
        $data['transid'] = $this->transId;
        $data['apnName'] = $apnName;
        $data['serviceUsageState'] = $serviceUsageState;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 单卡语音功能开停(集团客户可以通过卡号（msisdn\iccid\imsi三选一，单卡）办理集团归属物联卡的语音功能开/停（同一卡号30分钟内不能通过此类接口重复办理业务）)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @param [type] $operType  [0:开  1:停]
     * @return void
     */
    function CMIOT_API23M05(string $cardNo, $type, $operType) {
        $url = "$this->host/ec/operate/sim-call-function";
        $data = [];
        $data['transid'] = $this->transId;
        $data['operType'] = $operType;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 单卡短信功能开停(集团客户可以通过卡号（msisdn\iccid\imsi三选一，单卡）办理集团归属物联卡的短信功能开/停（同一卡号30分钟内不能通过此类接口重复办理业务）)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @param [type] $operType  [0:开  1:停]
     * @return void
     */
    function CMIOT_API23M06(string $cardNo, $type, $operType) {
        $url = "$this->host/ec/operate/sim-sms-function";
        $data = [];
        $data['transid'] = $this->transId;
        $data['operType'] = $operType;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 单卡APN功能开停(集团客户可以通过卡号（msisdn\iccid\imsi三选一，单卡）办理集团归属物联卡的APN功能开/停（同一卡号30分钟内不能通过此类接口重复办理业务）)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @param [type] $apnName  所要办理的APN Name
     * @param [type] $operType  [0:开  1:停]
     * @return void
     */
    function CMIOT_API23M07(string $cardNo, $type, $apnName, $operType) {
        $url = "$this->host/ec/operate/sim-apn-function";
        $data = [];
        $data['transid'] = $this->transId;
        $data['apnName'] = $apnName;
        $data['operType'] = $operType;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }
    
    /**
     * 单卡通信功能开通查询(查询物联卡通信功能开通情况)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @return serviceTypeList 【通信功能服务：01 基础语音通信服务  08 短信基础服务  
     *                                      10 国际漫游服务  11 数据通信服务】
     * serviceStatus 【通信功能服务状态：0：暂停   1：恢复】
     * apnName APN名称
     */
    function CMIOT_API23M08(string $cardNo, $type) {
        $url = "$this->host/ec/query/sim-communication-function-status";
        $data = [];
        $data['transid'] = $this->transId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     *物联卡通信功能开停批量办理(集团客户可以通过卡号调用该接口批量办理物联卡的通信功能（语音、短信、国际漫游、数据通信服务）开停，每次不超过100张卡，同一卡号30分钟内不得重复调用该接口（批量办理中若一批有10个卡号，其中有一个卡号是在30分钟内有成功调用的，则这次不允许调用，这一批卡号中其余9个卡号本次调用失败，不记录在30分钟内有调用的记录中）。如需查询办理结果则根据该接口返回的任务流水号调“CMIOT_API23M10-物联卡业务批量办理结果查询”接口查询办理结果。（OneLink-CT的主EC客户查询副EC卡数据时，一次查询的卡号必须归属于同一个EC）)
     * @param string $cardNos  卡片号码字符串 xxxx_xxxx_xxxx；最多100个
     * @param [type] $serviceType  服务类型：01-基础语音通信服务,08-短信基础服务
     *                                      10-国际漫游服务,11-数据通信服务
     * @param [type] $operType  操作类型：0：暂停  1：恢复
     * @param [type] $apnName  APN名称，serviceType为11时，必填
     * @return jobId 任务流水号
     */
    function CMIOT_API23M09($cardNos, $serviceType, $operType, $apnName) {
        $url = "$this->host/ec/operate/sim-communication-function/batch";
        $data = [];
        $data['transid'] = $this->transId;
        $data['msisdns'] = $cardNos;
        $data['serviceType'] = $serviceType;
        $data['operType'] = $operType;
        $data['apnName'] = $apnName;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     *物联卡业务批量办理结果查询(集团客户可以通过物联卡批量处理的任务流水号接口查询物联卡业务批量办理的结果)
     * @param [type] $jobId 任务流水号
     * @return jobStatus 任务状态：[0:待处理 1:处理中 2:处理完成 3:包含有处理失败记录的处理完成 4:处理失败]
     */
    function CMIOT_API23M10($jobId) {
        $url = "$this->host/ec/query/sim-batch-result";
        $data = [];
        $data['transid'] = $this->transId;
        $data['jobId'] = $jobId;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 成员语音白名单查询(集团客户可以通过卡号（msisdn\iccid\imsi三选一，单卡）实现集团旗下单个群组成员的语音白名单查询)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @param [type] $groupId  成员归属的群组ID
     * @return status 【语音白名单状态:  0:失效白名单  1:生效白名单】
     */
    function CMIOT_API23M15(string $cardNo, $type, $groupId) {
        $url = "$this->host/ec/query/member-voice-whitelist";
        $data = [];
        $data['transid'] = $this->transId;
        $data['groupId'] = $groupId;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 成员语音白名单配置(集团客户可以通过卡号（msisdn\iccid\imsi三选一，单卡）实现集团旗下单个群组成员的语音白名单配置。语音白名单号码（群组级或成员级）新增个数最大为10个，达到10个后，只允许删除或重新添加已删除的号码。删除10个号码范围内的任一号码后，可再次加入被删除的号码，删除的号码也纳入限制个数统计。如：场景一: 成员/群组已经有5个白名单号码，现删除一个号码，此时统计白名单加入号码为5个，删除号码再次加入，此时统计白名单加入号码还是5个 场景二: 成员/群组已经有10个白名单号码，此时不允许再加入新的号码，只能加入曾经删除过的号码)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @param [type] $groupId  成员归属的群组ID
     * @param [type] $operType  语音白名单配置类型：1：新增  4：删除  3：重新添加
     * @param [type] $whiteNumber  成员配置的语音白名单号码（MSISDN）
     * @return status 【语音白名单状态:  0:失效白名单  1:生效白名单】
     */
    function CMIOT_API23M16(string $cardNo, $type, $groupId, $operType, $whiteNumber) {
        $url = "$this->host/ec/config/member-voice-whitelist";
        $data = [];
        $data['transid'] = $this->transId;
        $data['groupId'] = $groupId;
        $data['operType'] = $operType;
        $data['whiteNumber'] = $whiteNumber;
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 群组语音白名单查询(集团客户可以通过该接口实现集团旗下单个群组的语音白名单查询)
     * @param [type] $groupId  集团旗下的群组ID
     * @return status 【语音白名单状态:  0:失效白名单  1:生效白名单】
     */
    function CMIOT_API23M17($groupId) {
        $url = "$this->host/ec/query/group-voice-whitelist";
        $data = [];
        $data['transid'] = $this->transId;
        $data['groupId'] = $groupId;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 群组语音白名单配置(集团客户可以通过该接口实现集团旗下单个群组的语音白名单配置。语音白名单号码（群组级或成员级）新增个数最大为10个，达到10个后，只允许删除或重新添加已删除的号码。删除10个号码范围内的任一号码后，可再次加入被删除的号码，删除的号码也纳入限制个数统计。如：场景一: 成员/群组已经有5个白名单号码，现删除一个号码，此时统计白名单加入号码为5个，删除号码再次加入，此时统计白名单加入号码还是5个 。场景二: 成员/群组已经有10个白名单号码，此时不允许再加入新的号码，只能加入曾经删除过的号码)
     * @param [type] $groupId  集团旗下的群组ID
     * @param [type] $operType 【语音白名单配置类型：1：新增  4：删除  3：重新添加】
     * @param [type] $whiteNumber  群组配置的语音白名单号码（MSISDN）
     * @return Void
     */
    function CMIOT_API23M18($groupId, $operType, $whiteNumber) {
        $url = "$this->host/ec/config/group-voice-whitelist";
        $data = [];
        $data['transid'] = $this->transId;
        $data['groupId'] = $groupId;
        $data['operType'] = $operType;
        $data['whiteNumber'] = $whiteNumber;
        return $this->fetchApi($url, $data, 'post');
    }


    /***************************************风险预警类***************************************/
    /**
     * 机卡分离周报查询(按周查询集团下的机卡分离列表)
     * @param [type] $weekDate  按周查询yyyyMMdd格式，查询该日期所在的周（周一到周日），时间为24周前(不包含本周)
     * @param [type] $pageSize  每次查询查询数目，不超过50条
     * @param [type] $startNum  开始页，从1开始
     * @return flag 【是否是最后一页  Y: 是最后一页  N：不是最后一页】
     *      terminalNum 终端变化次数
     *      imeiNum     IMEI变化次数
     *      rsikLevel   风险等级
     *      region      地市信息
     */
    function CMIOT_API23A00($weekDate, $pageSize, $startNum) {
        $url = "$this->host/ec/query/machine-card-separation-situation-weekly";
        $data = [];
        $data['transid'] = $this->transId;
        $data['weekDate'] = $weekDate;
        $data['pageSize'] = $pageSize;
        $data['startNum'] = $startNum;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 机卡分离月报查询(按月查询集团下的机卡分离列表)
     * @param [type] $monthDate  按月份查询，时间为12月内（不包含本月），yyyymm格式
     * @param [type] $pageSize  每次查询查询数目，不超过50条
     * @param [type] $startNum  开始页，从1开始
     * @return flag 【是否是最后一页  Y: 是最后一页  N：不是最后一页】
     *      terminalNum 终端变化次数
     *      imeiNum     IMEI变化次数
     *      rsikLevel   风险等级
     *      region      地市信息
     */
    function CMIOT_API23A01($monthDate, $pageSize, $startNum) {
        $url = "$this->host/ec/query/machine-card-separation-situation-monthly";
        $data = [];
        $data['transid'] = $this->transId;
        $data['monthDate'] = $monthDate;
        $data['pageSize'] = $pageSize;
        $data['startNum'] = $startNum;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 物联卡APN流量告警规则批量设置(集团客户可以通过卡号调用该接口批量设置物联卡针对APN的三级流量阈值，每次不超过100张卡，同一卡号30分钟内不得重复调用该接口（批量办理中若一批有10个卡号，其中有一个卡号是在30分钟内有成功调用的，则这次不允许调用，这一批卡号中其余9个卡号本次调用失败，不记录在30分钟内有调用的记录中）。如需查询办理结果则根据该接口返回的任务流水号调“CMIOT_API23M10-物联卡业务批量办理结果查询”接口查询办理结果。（OneLink-CT的主EC客户查询副EC卡数据时，一次查询的卡号必须归属于同一个EC）)
     * @param [type] $cardNos   多个卡号字符串：xxxx_xxxx_xxxx；最多100个
     * @param [type] $operType  0：新增  1：取消
     * @param [type] $apnName   APN名称
     * @param [type] $apnType   APN类型：0：通用  1：专用
     * @param [type] $level 可选参数：(operType为1时不传)	三级阈值需满足level1<level2<level3
     *                      level1	否	无	流量阈值1，单位：MB
     *                      level2	否	无	流量阈值2，单位：MB	
     *                      level3	否	无	流量阈值3，单位：MB
     * @return jobId  任务流水号
     */
    function CMIOT_API23A03($cardNos, $operType, $apnName, $apnType) {
        $url = "$this->host/ec/limit/sim-gprs/batch";
        $data = [];
        $data['transid'] = $this->transId;
        $data['msisdns'] = $cardNos;
        $data['operType'] = $operType;
        $data['apnName'] = $apnName;
        $data['apnType'] = $apnType;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 物联卡机卡分离状态查询(集团客户可通过物联卡卡号（msisdn，单卡）调用该接口查询已订购话单侧或网络侧机卡绑定的单卡的机卡绑定状态)
     * @param [type] $cardNo   卡号
     * @param [type] $testType  分离检测方式：0：话单侧检测  1：网络侧检测
     * @return result 查询结果状态:0：已分离  1：未分离  2：查询失败
     *      errorCode  错误码，当result=2时，才有该错误码
     *      errorDes   错误描述，当result=2时，才有该错误描述
     *      sepTime    分离时间，目前仅 “话单侧检测”方式，可以提供分离时间
     */
    function CMIOT_API23A04($cardNo, $testType) {
        $url = "$this->host/ec/query/card-bind-status";
        $data = [];
        $data['transid'] = $this->transId;
        $data['msisdn'] = $cardNo;
        $data['testType'] = $testType;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 单卡机卡绑定/解绑（话单侧）(集团客户可以通过卡号（msisdn\iccid\imsi三选一，单卡）实现单张SIM卡在话单侧的机卡绑定、解绑（首话单一对一自动绑定，或与多个连续IMEI组成的IMEI段进行一对多绑定，连续IMEI的端头均相同）。同一卡号30分钟内不能通过此类接口重复办理业务)
     * @param string $cardNo  卡片号码
     * @param [type] $type  cardNo卡号、iccid、imsi
     * @param [type] $operType  机卡操作：1：机卡绑定  2：机卡解绑
     * @param [type] $bindingStyle  机卡绑定方式：
     *          0：首话单自动绑定（一对一绑定）
     *          1：人工录入IMEI段（一对多绑定）operType=1，该字段必填;
     * @param [type] $tac  IMEI段：纯数字，取IMEI段的前8~14位；bindingStyle=1时，该字段为必填
     * @return status 【语音白名单状态:  0:失效白名单  1:生效白名单】
     */
    function CMIOT_API23A05(string $cardNo, $type, $operType, $bindingStyle=NULl, $tac=NULL) {
        $url = "$this->host/ec/operate/card-bind-by-bill";
        $data = [];
        $data['transid'] = $this->transId;
        $data['operType'] = $operType;
        if($operType === 1){
            $data['bindingStyle'] = $bindingStyle;
            if($bindingStyle == null){
                return '参数缺失！';
            }
        }
        if($bindingStyle === 1){
            $data['tac'] = $tac;
            if($tac == null){
                return '参数缺失！';
            }
        }
        switch($type) {
            case 'cardNo':
                $data['msisdn'] = $cardNo;
                break;
            case 'iccid':
                $data['iccid'] = $cardNo;
                break;
            case 'imsi':
                $data['imsi'] = $cardNo;
                break;
            default:
                $data['msisdn'] = $cardNo;
                break;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     *批量机卡绑定/解绑（话单侧）(集团客户可以通过卡号（仅msisdn，最多100张）实现批量SIM卡在话单侧的机卡绑定、解绑（首话单一对一自动绑定，或与多个连续IMEI组成的IMEI段进行多对多绑定，连续IMEI的端头均相同）。同一卡号30分钟内不得重复调用该接口（批量办理中若一批有10个卡号，其中有一个卡号是在30分钟内有成功调用的，则这次不允许调用，这一批卡号中其余9个卡号本次调用失败，不记录在30分钟内有调用的记录中）。如需查询办理结果则根据该接口返回的任务流水号调“CMIOT_API23M10-物联卡业务批量办理结果查询”接口查询办理结果（OneLink-CT的主EC客户查询副EC卡数据时，一次查询的卡号必须归属于同一个EC）)
     * @param string $cardNos  卡片号码字符串 xxxx_xxxx_xxxx；最多100个
     * @param [type] $operType  机卡操作：1：机卡绑定  2：机卡解绑
     * @param [type] $bindingStyle  机卡绑定方式：
     *          0：首话单自动绑定（一对一绑定）
     *          1：人工录入IMEI段（一对多绑定）operType=1，该字段必填;
     * @param [type] $tac  IMEI段：纯数字，取IMEI段的前8~14位；bindingStyle=1时，该字段为必填
     * @return jobId 任务流水号
     */
    function CMIOT_API23A06($cardNos,  $operType, $bindingStyle=NULl, $tac=NULL) {
        $url = "$this->host/ec/operate/card-bind-by-bill/batch";
        $data = [];
        $data['transid'] = $this->transId;
        $data['msisdns'] = $cardNos;
        $data['operType'] = $operType;
        if($operType === 1){
            $data['bindingStyle'] = $bindingStyle;
            if($bindingStyle == null){
                return '参数缺失！';
            }
        }
        if($bindingStyle === 1){
            $data['tac'] = $tac;
            if($tac == null){
                return '参数缺失！';
            }
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 高风险物联卡数量按日查询(集团客户输入查询日期“queryDate”查询最近7天内（不含当天及昨天）某一天的高风险等级物联卡数量)
     * @param string $queryDate  需要查询的日期，格式为yyyymmdd。
    *  仅能查询前天起7天之内的数据，不填默认前天，举例：今天20180908，则只能查询20180831至20180906之间的数据
     * @return riskNum 当日高风险物联卡数量总量，为0也要返回
     */
    function CMIOT_API25A00($queryDate=null) {
        $url = "$this->host/ec/query/highrisk-num-daily";
        $data = [];
        $data['transid'] = $this->transId;
        if(!empty($queryDate)){
            $data['queryDate'] = $queryDate;
        }
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 物联卡风险等级分布按月查询(集团客户输入查询月份“queryDate”查询最近6个月内（不含当月）其中一个月各个风险等级的物联卡数量)
     * @param string $queryDate  需要查询的日期，格式为yyyymm，如201808表示2018年8月。
     * 仅查询之前6个月的数据，举例：现在为9月，则只能查询3-8月的数据，当月2号以后才能查询上个月的数据
     * @return riskLevel 风险等级：risk001-高风险；risk002-中风险；risk003-低风险；risk004-安全；
     *         riskNum   当月对应风险类型的物联卡数量，为0也要返回
     */
    function CMIOT_API25A01($queryDate) {
        $url = "$this->host/ec/query/risk-level-distribution-monthly";
        $data = [];
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        return $this->fetchApi($url, $data, 'post');
    }

    /**
     * 高风险物联卡类型分布查询(集团客户输入查询日期“queryDate”查询最近7天内（不含当天及昨天）某一天高风险物联卡的类型分布信息)
     * @param string $queryDate  需要查询的日期，格式为yyyymmdd。
     * 仅能查询前天起7天之内的数据，不填默认前天，举例：今天20180908，则只能查询20180831至20180906之间的数据
     * @return riskType 风险类型：risk001001：开通非定向语音且在敏感区域漫游使用
     *                           risk001002：开通非定向语音且在手机终端使用
     *                           risk001003：手机类终端上使用过且发生机卡分离
     *         riskNum   当日对应风险类型的物联卡数量，为0也要返回
     */
    function CMIOT_API25A02($queryDate) {
        $url = "$this->host/ec/query/highrisk-type-distribution-daily";
        $data = [];
        $data['transid'] = $this->transId;
        $data['queryDate'] = $queryDate;
        return $this->fetchApi($url, $data, 'post');
    }

    /*****************************发送API请求**********************************/
    /**
     * 发送请求api
     * @param String $url 请求的url
     * @param Array $data 请求的参数数组
     * @param String $method 请求方法 get, post, batchPost 默认post
     */
    protected function fetchApi($url, $data, $method = self::POST_METHOD) {
        //如果token没有，需要获取
        if (empty($this->token)) {
            $this->token = $this->getToken(); //重新赋值token 
        }
        //在请求中加入token参数
        if (($method == self::GET_METHOD || $method == self::POST_METHOD) && !array_has($data, 'token')) {
            $data['token'] = $this->token;
        } else if ($method == self::BATCH_POST_METHOD) {
            foreach ($data as &$reqData) {
                $reqData['token'] = $this->token;
            }
        }
        
        //发送请求
        switch ($method) {
            case self::GET_METHOD:
                $res = parent::getApi($url, $data);
                if ($res->status == '12021') {
                    //TOKEN不存在或已过期，请重新获取
                    $this->token = $this->getToken(); //重新赋值token
        
                    //再次发送请求
                    return $this->fetchApi($url, $data, $method);
                }
                break;
            case self::POST_METHOD:
                $res = parent::postApi($url, $data); 
                if ($res->status == '12021') {
                    //TOKEN不存在或已过期，请重新获取
                    $this->token = $this->getToken(); //重新赋值token
        
                    //再次发送请求
                    return $this->fetchApi($url, $data, $method);
                }   
                break;
            case self::BATCH_POST_METHOD:
            // dd($data);
                $res = parent::https_post_batch_request($url, $data);
                // dd($batchRes);
                if (!empty($res)) {
                    foreach ($res as $batchRes) {
                        $batchRes = json_decode($batchRes);
                        if ($batchRes->status == '12021') {
                            //TOKEN不存在或已过期，请重新获取
                            $this->token = $this->getToken(); //重新赋值token
                
                            //再次发送请求
                            return $this->fetchApi($url, $data, $method);
                        }
                    }
                }
                break;
            default:
                $res = parent::getApi($url, $data);
                if ($res->status == '12021') {
                    //TOKEN不存在或已过期，请重新获取
                    $this->token = $this->getToken(); //重新赋值token
        
                    //再次发送请求
                    return $this->fetchApi($url, $data, $method);
                }
                break;
        }
        
        return $res;
    }

}
