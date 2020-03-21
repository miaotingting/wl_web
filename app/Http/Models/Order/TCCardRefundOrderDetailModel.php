<?php

namespace App\Http\Models\Order;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\TypeModel;
use App\Http\Models\Card\CardModel;

class TCCardRefundOrderDetailModel extends BaseModel
{
    //
    protected $table = 'c_card_refund_order_detail';

    const max_insert = 500;

    public $dicArr = [
        "status" => TypeModel::CARD_STATUS,
        "operator_type" => TypeModel::OPERATOR_TYPE,
    ];

    /**
     * 批量添加
     * @param String $no 退货单号
     * @param Array $cards 卡信息
     */
    function batchAdd(string $no, array $cards) {
        
        $num = count($cards);
        $offset = 0;
        while($num > 0) {
            $cardTemp = array_slice($cards,$offset,self::max_insert);
            //查询卡片信息
            $cardNos = array_column($cardTemp, 'card_no');
            $cardRes = CardModel::whereIn('card_no', $cardNos)->get(['imsi', 'sale_date', 'active_date', 'valid_date', 'status', 'card_account', 'card_no', 'iccid', 'operator_type']);
            $inserts = [];
            foreach ($cardRes as $card) {
                $insert['id'] = getUuid('CCROD');
                $insert['no'] = $no;
                $insert['card_no'] = $card['card_no'];
                $insert['iccid'] = $card['iccid'];
                $insert['imsi'] = $card['imsi'];
                $insert['sale_date'] = $card['sale_date'];
                $insert['active_date'] = $card['active_date'];
                $insert['valid_date'] = $card['valid_date'];
                $insert['status'] = $card['status'];
                $insert['card_account'] = $card['card_account'];
                $insert['operator_type'] = $card['operator_type'];
                $inserts[] = $insert;
            }
            self::insert($inserts);
            $offset += self::max_insert; 
            $num -= self::max_insert;  //减到0就表示全部插入了
        }
        
    }

    private function getWhere(array $search) {
        $where = [];
        if (count($search) > 0) {
            //增加搜索条件
            if (array_has($search, 'cardNo')) {
                //如果有卡号
                $where['c_card_refund_order_detail.card_no'] = ['like', array_get($search, 'cardNo')];
            }
            if (array_has($search, 'iccid')) {
                //如果有iccid
                $where['c_card_refund_order_detail.iccid'] = ['like', array_get($search, 'iccid')];
            }
            if (array_has($search, 'operatorType')) {
                //如果有运营商类型
                $where['c_card_refund_order_detail.operator_type'] = ['like', array_get($search, 'operatorType')];
            }
            if (array_has($search, 'status')) {
                //如果有状态
                $where['c_card_refund_order_detail.status'] = array_get($search, 'status');
            }
        }
        return $where;
    }

    /**
     * 获取下面的卡片
     * @param Int $pageIndex 页码
     * @param Int $pageSize 每页数量
     * @param String $no 退货单号
     */
    function getCards(int $pageIndex, int $pageSize, string $no, array $search) {
        $where = $this->getWhere($search);
        $where['c_card_refund_order_detail.no'] = $no;
        //要查询的字段
        $fields = ['c_card_refund_order_detail.no','c_card_refund_order_detail.card_no', 'c_card_refund_order_detail.iccid','c_card_refund_order_detail.imsi', 'c_card_refund_order_detail.sale_date', 
                    'c_card_refund_order_detail.active_date', 'c_card_refund_order_detail.valid_date', 'c_card_refund_order_detail.operator_type', 'c_card_refund_order_detail.status', 'c_card_refund_order_detail.card_account',
                    'c_card_refund_order.order_no'];
        
        //要连接的表
        $joins = [
            ['c_card_refund_order', 'c_card_refund_order_detail.no', '=', 'c_card_refund_order.no'],
        ];
        $res = $this->joinQueryPage($pageSize, $pageIndex, $fields, $where, $joins);
        return $res;
    }

}
