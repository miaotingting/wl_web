<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;

class TypeModel extends BaseModel
{
    protected $table = 'sys_type';
    public $timestamps = false;

    const OPERATOR_TYPE = 'operator_type';
    const INDUSTRY_TYPE = 'industry_type';
    const CARD_TYPE = 'card_type';
    const CARD_STATUS = 'card_status'; //卡状态
    const STANDARD_TYPE = 'standard_type';
    const MODEL_TYPE = 'model_type';
    const SALE_ORDER_STATUS = 'sale_order_status';
    const REFUND_CARD_STATUS = 'refund_card_status';

    /**
     * 获取条件
     */
    function getWhere(array $search) {
        $where = [];
        foreach($search as $col => $val) {
            if (array_has($search, $col) && !empty($search[$col])) {
                $where[$col] = ['like', $search[$col]];
            }
        }
        return $where;
    }

    /**
     * 获取字典列表
     * 
     */
    function getTypes(int $pageIndex, int $pageSize, array $search) {
        $where = $this->getWhere($search);
        return $this->queryPage($pageSize, $pageIndex, $where);
    }

    /**
     * 添加字典
     */
    function add(array $reqs) {
        $this->id = getUuid($this->table);
        $this->name = $reqs['name'];
        $this->code = $reqs['code'];
        $res = $this->save();
        if (!$res) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 保存字典
     */
    function saveType(string $id, array $reqs) {
        $type = $this->where('id', $id)->first();
        $type->name = $reqs['name'];
        $type->code = $reqs['code'];
        $res = $type->save();
        if (!$res) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }


}
