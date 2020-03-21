<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Matter\DefineModel;
use Illuminate\Support\Facades\Validator;

class WorkFlowController extends Controller
{
    //get.api/Admin/WFList  流程定义列表
    public function WFList(Request $request)
    {
        if($request->has('search') && !empty($request->get('search'))){
            $search = json_decode($request->get('search'),true);
        }else{
            $search = [];
        }
        $defineList = (new DefineModel())->getWFList($request, $search);
        return setTResult($defineList);
    }

    //post.api/Admin/setWFDefine    定义流程
    public function setWFDefine(Request $request)
    {
        $rules = [
            'taskCode'=>'required|min:2|max:20|unique:wf_define,task_code',
            'taskName'=>'required|min:3|max:30|unique:wf_define,task_name',
            'taskNum'=>'required|min:2|integer',
            'desc'=>'required|min:5|max:200'
        ];
    
        $messages = [
            'required'=>':attribute为必填项',
            'taskCode.unique'=>'流程已编码定义',
            'taskName.unique'=>'流程名称已定义',
            'taskNum.integer'=>'节点个数应为大于2的整数',
            'min'=>':attribute长度过小',
            'max'=>':attribute长度过大'
        ];

        $validator = Validator::make($request->all(), $rules, $messages,[
            'taskCode'=>'流程编码',
            'taskName'=>'流程名称',
            'taskNum'=>'节点个数',
            'desc'=>'流程描述'
        ]);

        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }

        try{
            if ((new DefineModel())->setWFInsert($request, $this->user)) {
                return setFResult('0', '流程定义成功！');
            }
        }catch(Exception $e){

        }
    }

    //post.api/Admin/setNodes   设置节点
    public function setNodes(Request $request)
    {
        $rules = [
            'taskId'=>'required',
            'nodeName'=>'required',
            'roleId'=>'required',
        ];
    
        $messages = [
            'required'=>':attribute为必填项'
        ];

        $validator = Validator::make($request->all(), $rules, $messages,[
            'taskId'=>'流程ID',
            'nodeName'=>'节点名称',
            'roleId'=>'角色ID',
        ]);

        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        try{
            $data = (new DefineModel())->setNodeInsert($request);
            return setFResult('0', '节点定义成功！');
        }catch(Exception $e){

        }
    }



}
