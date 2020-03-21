<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use PHPUnit\Framework\Constraint\Exception;
use Illuminate\Http\Request;
use App\Http\Models\Admin\TypeModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use App\Http\Models\Admin\TypeDetailModel;

class TypeController extends Controller
{

    protected $rules = [
        'page'=>'required',
        'pageSize'=>'required',
    ];
    protected $messages = [
        'page.required'=>'页码为必填项',
        'pageSize.required'=>'每页数量为必填项',
    ];

    private $typeModel;
    private $typeDetailModel;

    function __construct(Request $request, TypeModel $typeModel, TypeDetailModel $typeDetailModel)
    {   
        parent::__construct($request);
        $this->typeModel = $typeModel;
        $this->typeDetailModel = $typeDetailModel;
    }

    function getTypes(Request $request)
    {
        try{
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
            $res = $this->typeModel->getTypes(intval($pageIndex), intval($pageSize), $search);
            return $this->success($res);
        }catch(Exception $e) {

        }
    }

    function create(Request $request) {
        try {
            //参数验证
            $this->rules = [
                'name' => 'required',
                'code' => 'required'
            ];
            $this->messages = [
                'name.required' => '名称为必填项',
                'code.required' => '编号为必填项'
            ];
            $this->valid($request);
            //增加
            $this->typeModel->add($request->all());
            return $this->success();
        }catch(Exception $e) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    function update(Request $request, $id) {
        try{
            //参数验证
            $this->rules = [
                'name' => 'required',
                'code' => 'required'
            ];
            $this->messages = [
                'name.required' => '名称为必填项',
                'code.required' => '编号为必填项'
            ];
            $this->valid($request);
            $this->typeModel->saveType($id, $request->all());
            return $this->success();
        }catch(Exception $e) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    function delete(Request $request, $id) {
        try{
            //如果下面有详细，不许删除
            $details = $this->typeDetailModel->where('type_id', $id)->get();
            if (!$details->isEmpty()) {
                throw new CommonException(Errors::DIC_DELETE_ERROR);
            }
            $this->typeModel->where('id', $id)->delete();
            return $this->success();
        }catch(Exception $e) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    function getDetails(Request $request) {
        try{
            //参数验证
            $this->rules = array_collapse([$this->rules, [
                'typeId'=>'required',
            ]]);
            $this->messages = array_collapse([$this->messages, [
                'typeId.required' => '字典id为必填项'
            ]]);
            $this->valid($request);
            //参数处理
            $reqs = $request->all();
            $pageIndex = array_get($reqs, 'page', 1);
            $pageSize = array_get($reqs, 'pageSize', 10);
            $typeId = array_get($reqs, 'typeId');
            $res = $this->typeDetailModel->getDetails(intval($pageIndex), intval($pageSize), $typeId);
            return $this->success($res);
        }catch(Exception $e) {

        }
    }

    function createDetail(Request $request) {
        try{
            //参数验证
            $this->rules = [
                'name' => 'required',
                'code' => 'required',
                'type' => 'required|exists:sys_type,id'
            ];
            $this->messages = [
                'name.required' => '名称为必填项',
                'code.required' => '编号为必填项',
                'type.required' => '类型为必填项',
                'type.exists' => '类型不存在',
            ];
            $this->valid($request);
            $this->typeDetailModel->add($request->all());
            return $this->success();
        }catch(Exception $e) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    function updateDetail(Request $request, $id) {
        try{
            //参数验证
            $this->rules = [
                'name' => 'required',
                'code' => 'required',
            ];
            $this->messages = [
                'name.required' => '名称为必填项',
                'code.required' => '编号为必填项',
            ];
            $this->valid($request);
            $this->typeDetailModel->saveDetail($id, $request->all());
            return $this->success();
        }catch(Exception $e) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }


    function deleteDetail(Request $request, $id)
    {
        try{
            $this->typeDetailModel->where('id', $id)->delete();
            return $this->success();
        }catch(Exception $e) {
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

}
