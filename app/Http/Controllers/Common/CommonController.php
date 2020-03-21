<?php

namespace App\Http\Controllers\Common;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use League\Flysystem\Exception;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Admin\TypeDetailModel;

class CommonController extends Controller
{

    /**
     * 获取类型
     */
    function getEnums(Request $request) {
        $types = $request->input('types');
        $types = json_decode($types);
        $res = [];
        foreach($types as $type) {
            $res[$type] = TypeDetailModel::getDetailsByCodeNumKey(snake_case($type));
        }
        return $this->success($res);
    }


    /**
     * 下载模板
     */
    function downloadTemplete(Request $request) {
        $tempName = $request->input('name');
        $file = public_path("template/{$tempName}");
        return response()->download($file);
    }

}
