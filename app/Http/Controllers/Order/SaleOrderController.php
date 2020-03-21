<?php

namespace App\Http\Controllers\Order;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Order\SaleOrderModel;
use PHPUnit\Framework\Exception;
use Illuminate\Support\Facades\Log;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use App\Exceptions\ValidaterException;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Admin\TypeModel;
use App\Exports\SaleOrderExport;
use Maatwebsite\Excel\Facades\Excel;

class SaleOrderController extends Controller
{

    private $saleOrderModel;

    protected $rules = [
        'page'=>'required',
        'pageSize'=>'required',
    ];
    protected $messages = [
        'page.required'=>'页码为必填项',
        'pageSize.required'=>'每页数量为必填项',
    ];

    function __construct(Request $request, SaleOrderModel $saleOrderModel, CardModel $cardModel)
    {
        parent::__construct($request);
        $this->saleOrderModel = $saleOrderModel;
        $this->cardModel = $cardModel;
    }

    /**
     * 获取订单列表
     */
    function getOrders(Request $request) {
        try {
            //参数验证
            $this->valid($request);
            //参数处理
            $reqs = $request->all();
            $search = [];
            if ($request->has('search')) {
                $search = json_decode($reqs['search'], true);
            }
            $pageIndex = array_get($reqs, 'page', 1);
            $pageSize = array_get($reqs, 'pageSize', 10);
            //查询列表
            $res = $this->saleOrderModel->getOrders(intval($pageIndex), intval($pageSize), $search, $this->user);
            return $this->success($res);
            
        } catch(Exception $e) {
            
        }
    }

    /**
     * 获取订单详情
     */
    function getOrder(Request $request) {
        try{
            //参数验证
            $this->rules = [
                'no' => 'required'
            ];
            $this->messages = [
                'no.required' => '单号为必填项'
            ];
            $this->valid($request);
            //参数处理
            $no = $request->input('no');
            $order = $this->saleOrderModel->getOrder($no);
            //获取字典
            // $operatorTypes = TypeDetailModel::getDetailsByCode(TypeModel::OPERATOR_TYPE);
            // $industryTypes = TypeDetailModel::getDetailsByCode(TypeModel::INDUSTRY_TYPE);
            // $cardTypes = TypeDetailModel::getDetailsByCode(TypeModel::CARD_TYPE);
            // $standardTypes = TypeDetailModel::getDetailsByCode(TypeModel::STANDARD_TYPE);
            // $modelTypes = TypeDetailModel::getDetailsByCode(TypeModel::MODEL_TYPE);
            // $status = TypeDetailModel::getDetailsByCode(TypeModel::SALE_ORDER_STATUS);
            // $order->operator_type = $operatorTypes[$order->operator_type];
            // $order->industry_type = $industryTypes[$order->industry_type];
            // $order->card_type = $cardTypes[$order->card_type];
            // $order->standard_type = $standardTypes[$order->standard_type];
            // $order->model_type = $modelTypes[$order->model_type];
            // $order->status = $status[$order->status];
            return $this->success($order);
        }catch(Exception $e) {

        }
    }

    /**
     * 更新订单
     */
    function updateOrder(Request $request, $no) {
        try {
            //更新
            $this->saleOrderModel->saveOrder($no, $request->all());
            return $this->success();
        } catch(Exception $e) {

        }
    }

    /**
     * 创建订单
     */
    function create(Request $request) {
        try {
            //参数验证
            $this->rules = [
                'contactsName'=>'required',
                'contactsMobile'=>'required',
                'operatorType'=>'required',
                'industryType'=>'required',
                'modelType'=>'required',
                'standardType'=>'required',
                'describe'=>'required',
                'silentDate'=>'required',
                'realNameType'=>'required',
                'flowCardPrice'=>'required',
                'smsCardPrice'=>'required',
                'voiceCardPrice' => 'required',
                'payType'=>'required',
                'orderNum'=>'required',
                'addressName'=>'required',
                'addressPhone'=>'required',
                'address'=>'required',
                'expressArriveDay'=>'required',
                'cardType'=>'required',
                'orderNo'=> 'required',
                'customerId' => 'required|exists:sys_customer,id',
                'customerName' => 'required|exists:sys_customer,customer_name',
                'isVoice' => 'required_if:cardType,1002',
                'voicePackageId' => 'required_if:cardType,1002',
                'voiceExpiryDate' => 'required_if:cardType,1002',
                'isFlow' => 'required',
                'flowPackageId' => 'required',
                'flowExpiryDate' => 'required',
                'amount' => 'required',
                'isOverflowStop' => 'required'
            ];
            $this->messages = [
                'contactsName.required' => '联系人名为必填项',
                'contactsMobile.required' => '联系人手机为必填项',
                'operatorType.required' => '运营商类型为必填项',
                'industryType.required' => '行业用途为必填项',
                'modelType.required' => '卡型号为必填项',
                'standardType.required' => '通讯制式为必填项',
                'describe.required' => '描述为必填项',
                'silentDate.required' => '沉默期为必填项',
                'realNameType.required' => '是否实名为必填项',
                'flowCardPrice.required' => '流量卡价格为必填项',
                'smsCardPrice.required' => '短信卡价格为必填项',
                'voiceCardPrice.required' => '语音卡价格为必填项',
                'payType.required' => '支付方式为必填项',
                'orderNum.required' => '订单数量为必填项',
                'addressName.required' => '收件人名称为必填项',
                'addressPhone.required' => '收件人手机为必填项',
                'address.required' => '收件人地址为必填项',
                'expressArriveDay.required' => '快递采购时限为必填项',
                'cardType.required' => '卡类型为必填项',
                'orderNo.required' => '订单号为必填项',
                'customerId.required' => '客户id为必填项',
                'customerName.required' => '客户名称为必填项',
                'customerId.exists' => '客户id不存在',
                'customerName.exists' => '客户名称不存在',
                'isVoice.required_if' => '必须选择语音套餐',
                'voicePackageId.required_if' => '必须选择语音套餐',
                'voiceExpiryDate.required_if' => '必须选择语音套餐',
                'isFlow.required' => '必须选择流量套餐',
                'flowPackageId.required' => '必须选择流量套餐',
                'flowExpiryDate.required' => '必须选择流量套餐',
                'amount.required' => '订单总金额必填',
                'isOverflowStop.required' => '超流量停机必填',
            ];
            $this->valid($request);
            //参数处理
            $reqs = $request->all();
            $reqs['user'] = $this->user;
            $this->saleOrderModel->add($reqs);
            return $this->success();
        } catch(Exception $e) {
            DB::rollBack();
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 查询下面的卡片
     */
    function getCards(Request $request) {
        try {
            //参数验证
            $this->valid($request);
            //参数处理
            $reqs = $request->all();
            $pageIndex = array_get($reqs, 'page', 1);
            $pageSize = array_get($reqs, 'pageSize', 10);
            $orderId = array_get($reqs, 'orderId');
            //查询列表
            $res = $this->cardModel->getCards(intval($pageIndex), intval($pageSize), $orderId);
            return $this->success($res);
        } catch(Exception $e) {

        }
    }

    /**
     * 获取订单号
     */
    function getOrderNo() {
        return $this->success(getOrderNo(SaleOrderModel::PREFIX));
    } 
    /*
     * 导出订单列表
     * get.api/Order/saleOrderExport
     */
    public function saleOrderExport(Request $request){
        try{
            //参数处理
            $reqs = $request->all();
            $search = [];
            if ($request->has('search')) {
                $search = json_decode($reqs['search'], true);
            }
            $export = new SaleOrderExport($search,$this->user);
            return Excel::download($export, 'saleOrder.xls');
        } catch (Exception $ex) {
            throw new CommonException('106020');
        }
    }
    
    
}
