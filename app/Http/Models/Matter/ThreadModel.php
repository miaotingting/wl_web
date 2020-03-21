<?php

namespace App\Http\Models\Matter;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;

/**
 * 子进程，具体事项model
 */
class ThreadModel extends BaseModel
{
    
    protected $table = 'wf_thread';
    public $timestamps = false;
    protected $primaryKey = 'thread_id';

    const AWAIT_HANDLE_STATUS = 0;
    const AGREE_STATUS = 1;
    const REJECT_STATUS = 2;
    const DELETE_STATUS = 3;
    
    public $dicArr = [
        'status'=>'thread_status',
    ];

    /**
     * 获取进程下的所有线程
     */
    function getThreads(string $businessOrder, int $pageIndex, int $pageSize) {
        //查询进程
        $processModel = new ProcessModel();
        $process = $processModel->where('business_order', $businessOrder)->first();
        if (empty($process)) {
            throw new CommonException(Errors::MATTER_PROCESS_NOT_FOUND);
        }
        $where = [
            'process_id' => $process->process_id
        ];
        $fields = ['thread_id', 'process_id', 'process_desc', 'node_name', 'exec_user_id', 'exec_user_name', 'start_time', 'end_time', 'status', 'thread_desc'];
        $orderBy = ['times' => 'desc', 'node_index' => 'desc'];
        return $this->queryPage($pageSize, $pageIndex, $where, $fields, $orderBy);
    }
}
