<?php

namespace App\Http\Models\Matter\Handle;

use App\Exceptions\CommonException;
use App\Http\Models\Matter\DefineModel;
use App\Http\Models\Matter\NodeModel;
use App\Http\Models\Matter\ProcessModel;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;

class BaseHandle
{

    protected $orderNo;

    //同意处理方法
    protected $agreeHandle;

    //驳回处理方法
    protected $rejectHandle;

    //作废处理方法
    protected $deleteHandle;

    /**
     * 同意
     */
    function agree($taskId, $nodeIndex, $orderNo) {
        //查询Node
        $this->orderNo = $orderNo;
        $nodeModel = new NodeModel();
        $node = $nodeModel->where('task_id', $taskId)->where('node_index', $nodeIndex == ProcessModel::PROCESS_STATUS_END ? $this->MAX_NUM : $nodeIndex)->first(['node_code']);
        if (empty($node)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_NODE_NOT_FOUND);
        }

        //执行处理方法
        $handleName = $this->agreeHandle[$node->node_code];
        $this->$handleName();
    }

    /**
     * 驳回
     */
    function reject($taskId, $nodeIndex, $orderNo) {
        //查询Node
        $this->orderNo = $orderNo;
        $nodeModel = new NodeModel();
        $node = $nodeModel->where('task_id', $taskId)->where('node_index', $nodeIndex == ProcessModel::PROCESS_STATUS_END ? $this->MAX_NUM : $nodeIndex)->first(['node_code']);
        if (empty($node)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_NODE_NOT_FOUND);
        }

        //执行处理方法
        $handleName = $this->rejectHandle[$node->node_code];
        $this->$handleName();
    }

    /**
     * 作废
     */
    function deleteOrder($taskId, $nodeIndex, $orderNo) {
        //查询Node
        $this->orderNo = $orderNo;
        $nodeModel = new NodeModel();
        $node = $nodeModel->where('task_id', $taskId)->where('node_index', $nodeIndex == ProcessModel::PROCESS_STATUS_END ? $this->MAX_NUM : $nodeIndex)->first(['node_code']);
        if (empty($node)) {
            DB::rollback();
            throw new CommonException(Errors::MATTER_NODE_NOT_FOUND);
        }

        //执行处理方法
        $handleName = $this->deleteHandle[$node->node_code];
        $this->$handleName();
    }
}