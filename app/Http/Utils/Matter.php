<?php

namespace App\Http\Utils;

use App\Exceptions\CommonException;
use App\Http\Models\Matter\DefineModel;
use App\Http\Models\Matter\NodeModel;
use App\Http\Models\Matter\ProcessModel;
use App\Http\Models\Matter\ThreadModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Order\SaleOrderModel;

trait Matter{

    /**
     * 开始流程
     */
    function startProcess(string $code,string $no, array $user, string $customerName) {
        $time = date('Y-m-d H:i:s');
        //查询定义
        $matterDefineModel = new DefineModel;
        $define = $matterDefineModel->where('task_code', $code)->first(['task_id', 'task_name']);
        if (empty($define)) {
            DB::rollBack();
            throw new CommonException(Errors::MATTER_DEFINE_NOT_FOUND);
        }

        //查询节点
        $matterNodeModel = new NodeModel;
        $node = $matterNodeModel->where('task_id', $define->task_id)->orderBy('node_index', 'asc')->first();
        if (empty($node)) {
            DB::rollBack();
            throw new CommonException(Errors::MATTER_NODE_NOT_FOUND);
        }

        //创建流程
        $matterProcessModel = new ProcessModel;
        $matterProcessModel->process_id = getUuid('process');
        $matterProcessModel->process_desc = $define->task_name;
        $matterProcessModel->business_order = $no;
        $matterProcessModel->task_id = $define->task_id;
        $matterProcessModel->current_node_index = $node->node_index;
        $matterProcessModel->create_time = $time;
        $matterProcessModel->status = ProcessModel::PROCESS_STATUS_RUN;
        $matterProcessModel->start_user_id = $user['id'];
        $matterProcessModel->start_user_name = $user['real_name'];
        $matterProcessModel->customer_name = $customerName;
        $processRes = $matterProcessModel->save();
        if (!$processRes) {
            DB::rollBack();
            throw new CommonException(Errors::DATABASE_ERROR);
        }

        //创建第一个线程
        $matterThreadModel = new ThreadModel;
        $matterThreadModel->thread_id = getUuid('thread');
        $matterThreadModel->process_id = $matterProcessModel->process_id;
        $matterThreadModel->node_index = $node->node_index;
        $matterThreadModel->node_name = $node->node_name;
        $matterThreadModel->start_time = $time;
        $matterThreadModel->status = ThreadModel::AWAIT_HANDLE_STATUS;
        $threadRes = $matterThreadModel->save();
        if (!$threadRes) {
            DB::rollBack();
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 同意
     */
    function agreeProcess(string $processId, $desc, array $user) {
        //查询进程
        $processModel = new ProcessModel;
        $process = $processModel->where('process_id', $processId)->first();
        if (empty($process)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_PROCESS_NOT_FOUND);
        }

        //判断状态
        if (intval($process->status) !== ProcessModel::PROCESS_STATUS_RUN) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_PROCESS_STATUS_ERROR);
        }

        //查询定义表最大序号
        $defineModel = new DefineModel();
        $define = $defineModel->where('task_id', $process->task_id)->first();
        if ($process->current_node_index > $define->task_num) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_NODE_INDEX_ERROR);
        }
        
        //查询node表 查询出下一个节点的信息
        $nodeModel = new NodeModel;
        $nodeWhere['task_id'] = $process->task_id;
        $nodeWhere['node_index'] = $process->current_node_index + 1;
        $node = $nodeModel->where($nodeWhere)->first();

        $roleUser = new RoleUser;
        $userId = array_get($user, 'id');
        $roleId = $roleUser->getRoleIdByUser(strval($userId));
        $nodes = $nodeModel->whereIn('exec_role_id', $roleId)->get();
        $flag = false;
        foreach ($nodes as $value) {
            if ($value->node_index == $process->current_node_index) {
                $flag = true;
                break;
            }
        }
        if (!$flag) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_ROLE_ERROR);
        }

        //开始同意
        //判断当前节点序号是不是最后一个
        $processUpdate = [];
        if ($process->current_node_index >= $define->task_num) {
            $processUpdate['status'] = ProcessModel::PROCESS_STATUS_END;//状态变成结束
            $processUpdate['end_time'] = date('Y-m-d H:i:s');//结束时间
            $processUpdate['create_time'] = $process->create_time;
            // $process->status = ProcessModel::PROCESS_STATUS_END; 
            // $process->end_time = date('Y-m-d H:i:s');    //结束时间
            // $process->create_time = $process->create_time;
        } else {
            $processUpdate['create_time'] = $process->create_time;
            $processUpdate['end_time'] = $process->end_time;
            $processUpdate['current_node_index'] = $process->current_node_index + 1;
            // $process->current_node_index = $process->current_node_index + 1;
            // $process->end_time = $process->end_time;
            // $process->create_time = $process->create_time;
        }
        // $pro3 = $processModel->where("process_id", $process->process_id)->first();
        // dump($pro3);
        // dump($process);
        // dump($processUpdate);
        // DB::connection()->enableQueryLog();
        $processModel->where("process_id", $process->process_id)->update($processUpdate);
        // $process->save();
        // $pro2 = $processModel->where("process_id", $process->process_id)->first();
        // dd(DB::getQueryLog());
        // dd($pro2);
        //修改这条thread
        $threadModel = new ThreadModel();
        $thread = $threadModel->where('process_id', $processId)->orderBy('times','desc')->orderBy('node_index', 'desc')->first(['thread_id','process_id', 'times', 'node_index', 'start_time']);
        if (empty($thread)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_THREAD_NOT_FOUND);
        }

        $threadUpdate['exec_user_id'] = array_get($user, 'id');
        $threadUpdate['exec_user_name'] = array_get($user, 'real_name');
        $threadUpdate['start_time'] = $thread->start_time;
        $threadUpdate['end_time'] = date('Y-m-d H:i:s');
        $threadUpdate['status'] = ThreadModel::AGREE_STATUS;
        $threadUpdate['thread_desc'] = $desc;
        $threadModel->where('thread_id', $thread->thread_id)->update($threadUpdate);
        //修改完再查询出来返回
        $thread = $threadModel->where('process_id', $processId)->orderBy('times','desc')->orderBy('node_index', 'desc')->first();
        // $thread->exec_user_id = array_get($user, 'id');
        // $thread->exec_user_name = array_get($user, 'real_name');
        // $thread->end_time = date('Y-m-d H:i:s');
        // $thread->status = ThreadModel::AGREE_STATUS;
        // $thread->thread_desc = $desc;
        // $thread->save();

        //新建thread
        if ($thread->node_index != ProcessModel::PROCESS_STATUS_END) {
            $nodeIndex = $thread->node_index + 1;
            $threadModel->thread_id = getUuid('thread');
            $threadModel->process_id = $process->process_id;
            $threadModel->process_desc = $process->process_desc;
            $threadModel->node_index = $nodeIndex == $define->task_num ? ProcessModel::PROCESS_STATUS_END : $nodeIndex;
            $threadModel->node_name = $node->node_name;
            $threadModel->start_time = date('Y-m-d H:i:s');
            $threadModel->status = ThreadModel::AWAIT_HANDLE_STATUS;
            $threadModel->times = $thread->times;
            $threadModel->save();
        }
        // $orderModel = new SaleOrderModel;
        // $order = $orderModel->where("order_no", $process->business_order)->first();
        // dump($process);

        // dump($thread);
        // dump($threadModel);
        $this->process = $processModel->where("process_id", $process->process_id)->first();
        $this->define = $define;
        $this->thread = $thread;
        // return [
        //     'process' => $process,
        //     'define' => $define,
        //     'thread' => $thread,
        // ];
    }

    /**
     * 驳回
     */
    function rejectProcess(string $processId, $desc, array $user) {
        //查询进程
        $processModel = new ProcessModel;
        $process = $processModel->where('process_id', $processId)->first();
        if (empty($process)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_PROCESS_NOT_FOUND);
        }

        //判断状态
        if (intval($process->status) !== ProcessModel::PROCESS_STATUS_RUN) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_PROCESS_STATUS_ERROR);
        }

        //查询定义表最大序号
        $defineModel = new DefineModel();
        $define = $defineModel->where('task_id', $process->task_id)->first();
        if ($process->current_node_index > $define->task_num) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_NODE_INDEX_ERROR);
        }
        
        //开始驳回
        $processUpdate['create_time'] = $process->create_time;
        $processUpdate['current_node_index'] = 1;
        $processUpdate['end_time'] = $process->end_time;
        $processModel->where('process_id', $processId)->update($processUpdate);
        // $process->current_node_index = 1;
        // $process->save();

        //修改这条thread
        $threadModel = new ThreadModel();
        $thread = $threadModel->where('process_id', $processId)->orderBy('times','desc')->orderBy('node_index', 'desc')->first(['thread_id','process_id', 'times', 'node_index', 'start_time']);
        if (empty($thread)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_THREAD_NOT_FOUND);
        }
        $threadUpdate['exec_user_id'] = array_get($user, 'id');
        $threadUpdate['exec_user_name'] = array_get($user, 'real_name');
        $threadUpdate['start_time'] = $thread->start_time;
        $threadUpdate['end_time'] = date('Y-m-d H:i:s');
        $threadUpdate['status'] = ThreadModel::REJECT_STATUS;
        $threadUpdate['thread_desc'] = $desc;
        $threadModel->where('thread_id', $thread->thread_id)->update($threadUpdate);
        //修改完再查询出来返回
        $thread = $threadModel->where('process_id', $processId)->orderBy('times','desc')->orderBy('node_index', 'desc')->first();
        // $thread->exec_user_id = array_get($user, 'id');
        // $thread->exec_user_name = array_get($user, 'real_name');
        // $thread->end_time = date('Y-m-d H:i:s');
        // $thread->status = ThreadModel::REJECT_STATUS;
        // $thread->thread_desc = $desc;
        // $thread->save();

        //查询node_name
        $nodeModel = new NodeModel;
        $nodeWhere['task_id'] = $process->task_id;
        $nodeWhere['node_index'] = 1;
        $node = $nodeModel->where($nodeWhere)->first();

        //新建thread
        $threadModel->thread_id = getUuid('thread');
        $threadModel->process_id = $process->process_id;
        $threadModel->process_desc = $process->process_desc;
        $threadModel->node_index = 1;
        $threadModel->node_name = $node->node_name;
        $threadModel->start_time = date('Y-m-d H:i:s');
        $threadModel->status = ThreadModel::AWAIT_HANDLE_STATUS;
        $threadModel->times = ++$thread->times;
        $threadModel->save();

        $this->process = $processModel->where('process_id', $processId)->first();
        $this->define = $define;
        $this->thread = $thread;
    }

    /**
     * 作废
     */
    function deleteProcess(string $processId, $desc, array $user) {
        
        //查询进程
        $processModel = new ProcessModel;
        $process = $processModel->where('process_id', $processId)->first();
        if (empty($process)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_PROCESS_NOT_FOUND);
        }

        //判断状态
        if (intval($process->status) !== ProcessModel::PROCESS_STATUS_RUN) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_PROCESS_STATUS_ERROR);
        }

        //查询定义表最大序号
        $defineModel = new DefineModel();
        $define = $defineModel->where('task_id', $process->task_id)->first();
        if ($process->current_node_index > $define->task_num) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_NODE_INDEX_ERROR);
        }

        //开始作废
        $processUpdate['current_node_index'] = ++$process->current_node_index;
        $processUpdate['create_time'] = $process->create_time;
        $processUpdate['end_time'] = date('Y-m-d H:i:s');
        $processUpdate['status'] = ProcessModel::PROCESS_STATUS_DELETE;
        $processModel->where('process_id', $processId)->update($processUpdate);
        // $process->current_node_index = ++$process->current_node_index;
        // $process->end_time = date('Y-m-d H:i:s');
        // $process->status = ProcessModel::PROCESS_STATUS_DELETE;
        // $process->save();

        //修改这条thread
        $threadModel = new ThreadModel();
        $thread = $threadModel->where('process_id', $processId)->orderBy('times','desc')->orderBy('node_index', 'desc')->first(['thread_id','process_id', 'times', 'node_index', 'start_time']);
        if (empty($thread)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_THREAD_NOT_FOUND);
        }
        $threadUpdate['exec_user_id'] = array_get($user, 'id');
        $threadUpdate['exec_user_name'] = array_get($user, 'real_name');
        $threadUpdate['start_time'] = $thread->start_time;
        $threadUpdate['end_time'] = date('Y-m-d H:i:s');
        $threadUpdate['status'] = ThreadModel::DELETE_STATUS;
        $threadUpdate['node_index'] = ProcessModel::PROCESS_STATUS_END;
        $threadUpdate['thread_desc'] = $desc;
        $threadModel->where('thread_id', $thread->thread_id)->update($threadUpdate);
        // $thread->exec_user_id = array_get($user, 'id');
        // $thread->exec_user_name = array_get($user, 'real_name');
        // $thread->end_time = date('Y-m-d H:i:s');
        // $thread->status = ThreadModel::DELETE_STATUS;
        // $thread->node_index = ProcessModel::PROCESS_STATUS_END;
        // $thread->thread_desc = $desc;
        // $thread->save();

        $this->process = $processModel->where('process_id', $processId)->first();
        $this->define = $define;
        $this->thread = $threadModel->where('process_id', $processId)->orderBy('times','desc')->orderBy('node_index', 'desc')->first();
    }
}