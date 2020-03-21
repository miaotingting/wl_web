<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;

class TypeDetailModel extends BaseModel
{
    protected $table = 'sys_type_detail';
    public $timestamps = false;

    /**
     * 获取字典详细
     */
    function getDetails(int $pageIndex, int $pageSize, string $typeId) {
        $where = [
            'type_id' => $typeId,
        ];
        return $this->queryPage($pageSize, $pageIndex, $where);
    }

    /**
     * 添加字典详细
     */
    function add(array $reqs) {
        $this->id = getUuid();
        $this->name = $reqs['name'];
        $this->code = $reqs['code'];
        $this->type_id = $reqs['type'];
        $res = $this->save();
        if (!$res) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 保存字典详细
     */
    function saveDetail(string $id, array $reqs) {
        $detail = $this->where('id', $id)->first();
        $detail->name = $reqs['name'];
        $detail->code = $reqs['code'];
        $res = $detail->save();
        if (!$res) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 获取字典详细列表
     */
    static function getDetailsByCode(string $typeCode) {
        $typeModel = new TypeModel;
        $type = $typeModel->where('code', $typeCode)->first();
        $details = self::where('type_id', $type->id)->get(['code', 'name']);
        $res = [];
        foreach($details as $detail) {
            $temp = [];
            $temp['code'] = strval($detail->code);
            $temp['name'] = $detail->name;
            $res[strval($detail->code)] = $temp;
        }
        return $res;
    }

    /**
     * 获取字典详细列表
     */
    static function getDetailsByCodeNumKey(string $typeCode) {
        $typeModel = new TypeModel;
        $type = $typeModel->where('code', $typeCode)->first();
        $details = self::where('type_id', $type->id)->get(['code', 'name']);
        $res = [];
        foreach($details as $detail) {
            $temp = [];
            $temp['code'] = $detail->code;
            $temp['name'] = $detail->name;
            $res[] = $temp;
        }
        return $res;
    }
}
