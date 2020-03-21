<?php

namespace App\Http\Models\Matter;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;

/**
 * 事项定义model
 */
class DefineModel extends BaseModel
{
    //定义表名
    protected $table = 'wf_define';
    protected $primaryKey = 'task_id';

    const KKSP_MAX_NUM = 6;
    const TKSP_MAX_NUM = 5;
    
    
    public function getWFList($request, $search)
    {
        $where = array();
        if(!empty($search)){
            if(isset($search['taskCode'])){
                $where[] = ['task_code', 'like', '%'.$search['taskCode'].'%'];
            }

            if(isset($search['taskName'])){
                $where[] = ['task_name', 'like', '%'.$search['taskName'].'%'];
            } 
        }

        if($request->has('page') && !empty($request->get('page'))){
            $page = $request->get('page');
        }else{
            $page = 1;
        }

        if($request->has('pageSize') && !empty($request->get('pageSize'))){
            $pageSize = $request->get('pageSize');
        }else{
            $pageSize = 15;
        }

        $count = $this->where($where)->count('task_id');//总条数
        $pageCount = ceil($count/$pageSize); #计算总页面数    
        $wfData = $this->where($where)->offset(($page-1) * $pageSize)->limit($pageSize)->get()->toArray();
        $result = array();
        $result['count'] = $count;
        $result['page'] = (int)$page;
        $result['pageSize'] = $pageSize;
        $result['pageCount'] = $pageCount;
        $result['data'] = $wfData;
        return $result; 
    }

    /**
     * 添加流程(定义流程)
     * @param [type] $request
     * @param [type] $user
     * @return void
     */
    public function setWFInsert($request, $user)
    {
        if(empty($user)){
            throw new CommonException('101354');
        }
        $workFlowMode = new DefineModel();
        $workFlowMode->task_id = getUuid();
        $workFlowMode->task_code = $request->post('taskCode');
        $workFlowMode->task_name = $request->post('taskName');
        $workFlowMode->task_num = $request->post('taskNum');
        $workFlowMode->is_define = '未定义';
        $workFlowMode->desc = $request->post('desc');
        $workFlowMode->create_user_id = $user['id'];
        $workFlowMode->create_user_name = $user['real_name'];

        $re = $workFlowMode->save();
        if(!$re){
            throw new CommonException('300001');
        }
        return $re;
    }

    //设置流程的节点信息（Node）
    public function setNodeInsert($request)
    {
        $workFlow = self::select('task_id', 'is_define')->find($request->post('taskId'));
        if(empty($workFlow)){
            throw new CommonException('101352');
        }
        if($workFlow->is_define == '已定义'){
            throw new CommonException('101353');
        }
        try{
            DB::beginTransaction();
            $taskId = $request->post('taskId'); 
            $nodeName = explode('-', $request->post('nodeName')); //销售确认-销售总监审批-支撑确认-财务确认-行政发卡-数据同步
            $roleId = explode('-', $request->post('roleId'));     //aaa-bbb-ccc-ddd-eee-fff

            $data = array();
            $temp = array();
            $nodeModeObj = new NodeModel();
            //建立节点信息
            foreach($nodeName as $ke => $val){
                $temp['node_id'] = getUuid();
                $temp['task_id'] = $taskId;
                $temp['node_index'] = $ke+1;
                $temp['node_name'] = $val;
                $temp['created_at'] = date('Y-m-d H:i:s');
                $temp['updated_at'] = date('Y-m-d H:i:s');
                $data[] = $temp;
            }
            foreach ($roleId as $k => $id){
                $data[$k]['exec_role_id'] = $id;
            }
            $nodeModeObj::insert($data);
            //更新流程is_define
            $defineModel = self::find($taskId);
            $defineModel->is_define = '已定义';
            $defineModel->save();
            DB::commit();
            return true;
        }catch(Exception $e){
            DB::rollBack();
            throw new CommonException('101352');
        }
    }


}
