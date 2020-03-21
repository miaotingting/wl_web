<?php

namespace App\Http\Controllers\Order;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Order\TCCardRefundOrderModel;
use PHPUnit\Framework\Exception;
use Illuminate\Support\Facades\Log;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use App\Exceptions\ValidaterException;
use App\Exports\RefundOrderExport;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Admin\TypeModel;
use App\Exports\SaleOrderExport;
use App\Http\Models\Operation\TCWarehouseOrderDetailOutModel;
use App\Http\Models\Order\SaleOrderModel;
use App\Http\Models\Order\TCCardRefundOrderDetailModel;
use App\Imports\CardRefundImport;
use Maatwebsite\Excel\Facades\Excel;

class RefundOrderController extends Controller
{

    protected $refundOrderModel;

    protected $rules = [
        'page'=>'required',
        'pageSize'=>'required',
    ];
    protected $messages = [
        'page.required'=>'页码为必填项',
        'pageSize.required'=>'每页数量为必填项',
    ];

    function __construct(Request $request, TCCardRefundOrderModel $refundOrderModel)
    {
        parent::__construct($request);
        $this->refundOrderModel = $refundOrderModel;
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
            $res = $this->refundOrderModel->getOrders(intval($pageIndex), intval($pageSize), $search);
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
            $order = $this->refundOrderModel->getOrder($no);
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
            $this->refundOrderModel->saveOrder($no, $request->all());
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
                'orderNo'=>'required',
                'count'=>'required',
                'desc'=>'required',
                'cardsImport' => 'file',
            ];
            $this->messages = [
                'orderNo.required' => '订单为必填项',
                'count.required' => '退货数量为必填项',
                'desc.required' => '退货原因为必填项',
                'cardsImport.file' => '请上传正确的文件',
            ];
            $this->valid($request);
            //获取参数
            $cardFile = $request->file('cardsImport');//Excel文件
            $orderNo = $request->input('orderNo');
            $count = $request->input('count');
            $desc = $request->input('desc');

            //查询订单
            $order = (new SaleOrderModel)->getOrder($orderNo);
            if (empty($order)) {
                //订单不存在
                throw new CommonException(Errors::ORDER_NOT_FOUND);
            }
            //验证订单状态是否已结束
            if ($order->status != SaleOrderModel::STATUS_END) {
                //订单没有结束
                throw new CommonException(Errors::ORDER_STATUS_ERROR);
            }
            $random_str = random_str();
            $ext = $cardFile->getClientOriginalExtension(); 
            $cardFile->storeAs("upload", "refundCards{$random_str}.{$ext}");
            $filePath = storage_path("app/upload/refundCards{$random_str}.{$ext}");
            if(is_file($filePath)){
                // dd(123);
                // 从第二条读到最后，地1,2列不允许为空,中间有断点就返回数据
                $header = ['0'=>'card_no',"1"=>'iccid'];
                $cards = importExcel($filePath,$header,$ext,2,null,[1,2]); //读取excel数据

                //检查输入的数量和上传的数量是否一样
                $cardCount = count($cards);
                if ($count != $cardCount) {
                    throw new CommonException(Errors::REFUND_COUNT_ERROR);
                }

                //查询这些卡片是否在订单
                $cardNos = array_column($cards, 'card_no');
                $orderCount = CardModel::whereIn('card_no', $cardNos)->where('order_id', $order->id)->count('id');
                if ($orderCount != $cardCount) {
                    throw new CommonException(Errors::REFUND_ORDER_COUNT_ERROR);
                }

                //查询这些卡片是否已出库
                $outCount = TCWarehouseOrderDetailOutModel::whereIn('card_no', $cardNos)->count('id');
                if ($outCount != $cardCount) {
                    throw new CommonException(Errors::REFUND_OUT_COUNT_ERROR);
                }

                //查询这些卡片是否已经退卡
                $refundDetailCount = TCCardRefundOrderDetailModel::whereIn('card_no', $cardNos)->count('id');
                if ($refundDetailCount > 0) {
                    throw new CommonException(Errors::REFUND_CARD_REQUIRED_ERROR);
                }

                $this->refundOrderModel->add($orderNo, $desc, intval($count), $cards, $this->user);
            }else{
                throw new CommonException('106004');
            }
            return $this->success(true);
            
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
            $this->rules = array_collapse([$this->rules, [
                'no'=>'required',
            ]]);
            $this->messages = array_collapse([$this->messages, [
                'no.required'=>'退货单号必填',
            ]]);
            $this->valid($request);
            //参数处理
            $reqs = $request->all();
            $search = [];
            if ($request->has('search')) {
                $search = json_decode($reqs['search'], true);
            }
            $pageIndex = array_get($reqs, 'page', 1);
            $pageSize = array_get($reqs, 'pageSize', 10);
            $no = array_get($reqs, 'no');
            //查询列表
            $detailModel = new TCCardRefundOrderDetailModel;
            $res = $detailModel->getCards(intval($pageIndex), intval($pageSize), $no, $search);
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


    /**
     * 导出
     */
    function export(Request $request) {
        //参数验证
        $this->rules = [
            'no'=>'required',
        ];
        $this->messages = [
            'no.required'=>'退货单号必填',
        ];
        $this->valid($request);
        //参数处理
        $reqs = $request->all();
        $search = [];
        if ($request->has('search')) {
            $search = json_decode($reqs['search'], true);
        }
        $no = array_get($reqs, 'no');
        return Excel::download(new RefundOrderExport($no, $search), 'users.xlsx');
    }
    
    
}
