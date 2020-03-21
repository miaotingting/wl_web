<?php

namespace App\Http\Models\Matter;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use App\Http\Models\Order\SaleOrderModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Card\CardModel;
use App\Http\Models\Operation\StoreOutModel;

/**
 * 进程model
 */
class ProcessModel extends BaseModel
{
    
    protected $table = 'wf_process';
    protected $primaryKey = 'process_id';
    public $timestamps = false;

    const PROCESS_STATUS_DELETE = 0;
    const PROCESS_STATUS_RUN = 1;
    const PROCESS_STATUS_END = 100;

    // private $orderStatusArr = [
    //     'xsqr' => '1',
    //     'xszjsp' => '1',
    //     'zcqr' => '5',
    //     'cwqr' => '6',
    //     'xzfk' => '7',
    //     'sjtb' => '2',
    // ];


    /**
     * 同意
     */
    function agree(string $processId, $desc, array $user) {
        DB::beginTransaction();
        //同意进程
        $this->agreeProcess($processId, $desc, $user);
        //执行相应的handle处理
        $pro = $this->where('process_id', $processId)->first();
        // dd($pro);
        $factory = new ProcessFactory();
        $handle = $factory->factory($this->define->task_code);
        $handle->agree($this->define->task_id, $this->thread->node_index, $this->process->business_order);
        // $pro = $this->where('process_id', $processId)->first();
        // dd($pro);
        DB::commit();
    }

    /**
     * 驳回
     */
    function reject(string $processId, $desc, array $user) {
        DB::beginTransaction();
        //驳回进程
        $this->rejectProcess($processId, $desc, $user);
        
        //执行相应的handle处理
        $factory = new ProcessFactory();
        $handle = $factory->factory($this->define->task_code);
        $handle->reject($this->define->task_id, $this->thread->node_index, $this->process->business_order);
        DB::commit();
    }

    /**
     * 作废
     */
    function deleteProcessHandle(string $processId, $desc, array $user) {
        
        //查询进程
        DB::beginTransaction();
        $this->deleteProcess($processId, $desc, $user);
        
        //执行相应的handle处理
        $factory = new ProcessFactory();
        $handle = $factory->factory($this->define->task_code);
        $handle->deleteOrder($this->define->task_id, $this->thread->node_index, $this->process->business_order);

        DB::commit();
    }

}
