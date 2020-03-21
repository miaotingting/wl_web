<?php

namespace App\Http\Models\Matter;

use App\Http\Models\BaseModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use Illuminate\Support\Facades\DB;

/**
 * 节点model
 */
class NodeModel extends BaseModel
{
    protected $table = 'wf_node';

    const KKSP_FINANCE = 'cwqr';
    const KKSP_ADMIN = 'xzfk';
    const KKSP_SALE_DIRECTOR = 'xszjsp';
    const KKSP_DATA = 'sjtb';
    const KKSP_SUPPORT = 'zcqr';
    const KKSP_SALE = 'xsqr';

    /**
     * 通过搜索条件拼接where
     */
    private function getWhere(array $search) {
        $where = [];
        if (count($search) > 0) {
            //增加搜索条件
            if (array_has($search, 'businessOrder') && !empty($search['businessOrder'])) {
                //如果有业务单号
                $where[] = ['wf_process.business_order', array_get($search, 'businessOrder')];
            }
            if (array_has($search, 'matterType') && !empty($search['matterType'])) {
                //如果有事项类型
                $where[] = ['wf_define.task_name', array_get($search, 'matterType')];
            }
            if (array_has($search, 'beginTime.start') && array_has($search, 'beginTime.end')) {
                //如果有开始时间
                if (!empty(array_get($search, 'beginTime.start'))) {
                    $where[] = ['wf_process.create_time', '>=', array_get($search, 'beginTime.start')];
                }
                if (!empty(array_get($search, 'beginTime.end'))) {
                    $where[] = ['wf_process.create_time', '<=', array_get($search, 'beginTime.end')];
                }
                
            }
        }
        return $where;
    }

    /**
     * 获取所有待办事项
     * 
     */
    function getBacklogMatter(array $roleIds, int $pageIndex, int $pageSize, array $search) {
        $where = $this->getWhere($search);
        $where[] =['wf_process.status', ProcessModel::PROCESS_STATUS_RUN];
        $whereColumn = [
            ['wf_process.current_node_index', '=', 'wf_node.node_index'],
        ];
        // dump($roleId);
        // dd($where);
        return $this->getMatter($roleIds, $where, $whereColumn, $pageIndex, $pageSize);
    }

    /**
     * 获取所有已经办的事项
    */
    function getAlreadyMatter(array $roleIds, string $userId, int $pageIndex, int $pageSize, array $search) {

        $where = $this->getWhere($search);
        $where[] = ['wf_process.status', ProcessModel::PROCESS_STATUS_RUN];
        $where[] = ['wf_thread.exec_user_id', $userId];
        $whereColumn = [
            ['wf_process.current_node_index', '>', 'wf_node.node_index'],
        ];
       
        return $this->getEndMatterHandler($roleIds, $where, $pageIndex, $pageSize, $whereColumn);
    }

    /**
     * 获取办结事项
     */
    function getEndMatter(array $roleIds, string $userId, int $pageIndex, int $pageSize, array $search) {
        $where = $this->getWhere($search);
        $where[] = ['wf_process.status', ProcessModel::PROCESS_STATUS_END];
        $where[] = ['wf_thread.exec_user_id', $userId];
        return $this->getEndMatterHandler($roleIds, $where, $pageIndex, $pageSize);
    }

    /**
     * 获取我创建的事项
     */
    function getCreatedMatter(array $roleIds, string $userId, int $pageIndex, int $pageSize, array $search) {
        $where = $this->getWhere($search);
        $where[] = ['wf_process.start_user_id', $userId];
        
        return $this->getMatterAndHandler($roleIds, $where, $pageIndex, $pageSize);
    }

    /**
     * 根据角色id和状态获取事项
     */
    private function getMatter(array $roleIds, array $where, array $whereColumn, int $pageIndex, int $pageSize) {
        // $defineModel = new DefineModel();
        // $processModel = new ProcessModel();
        // DB::connection()->enableQueryLog();
//        dd($roleIds);
        $offset = ($pageIndex-1) * $pageSize;
        $sql = $this->leftJoin('wf_define', 'wf_node.task_id', '=', 'wf_define.task_id')
            ->rightJoin('wf_process', 'wf_define.task_id', '=', 'wf_process.task_id')
            // ->leftJoin('c_sale_order', 'wf_process.business_order', '=', 'c_sale_order.order_no')
            ->whereIn('wf_node.exec_role_id', $roleIds)
            ->where($where)
            ->whereColumn($whereColumn);
        $count = $sql->count('wf_process.process_id');
        $nodes = $sql->offset($offset)
        ->limit($pageSize)
        ->get(['wf_process.process_id','wf_process.current_node_index','wf_process.business_order','wf_process.process_desc','wf_process.create_time','wf_process.end_time','wf_process.status','wf_process.start_user_id','wf_process.start_user_name',
        'wf_define.task_name','wf_define.task_code',
        'wf_node.task_id','wf_node.node_index','wf_node.node_name',
        'wf_process.customer_name']);
        // dd(DB::getQueryLog());
        // $nodes = $this->where('exec_role_id', $roleId)->get(['task_id','node_index','node_name']);
        $res = [];
        if ($nodes->isEmpty()) {
            return $res;
        }
        
        // foreach($nodes as $node) {
        //     $task = $defineModel->where('task_id', 'dbb6ea5c-fac0-5075-a734-e779d6ce3fcd')->first();
        //     if (empty($task)) {
        //         throw new CommonException(Errors::MATTER_DEFINE_NOT_FOUND);
        //     }
            
        //     $process = $processModel->where('task_id', $node->task_id)->where('current_node_index', '>=', $node->node_index)->where('status', $status)->get(['process_id','task_id','current_node_index','business_order', 'process_desc', 'create_time', 'end_time', 'status', 'start_user_id', 'start_user_name']);  
            foreach ($nodes as $node) {
                // $info->process_id;
                $res[] = [
                    'process_id'        => $node->process_id,
                    'business_order'    => $node->business_order,
                    'matter_type'       => $node->task_name,
                    'matter_code'       => $node->task_code,
                    'node_name'         => $node->node_name,
                    'node_index'        => $node->node_index,
                    'begin_time'        => $node->create_time,
                    'start_user_id'     => $node->start_user_id,
                    'start_user_name'   => $node->start_user_name,
                    'custom_name'       => $node->customer_name,
                ];
            }
            
        // }
        $pageCount = ceil($count/$pageSize); #计算总页面数 
        $result = [];
        $result['data'] = $res;
        $result['count'] = intval($count);
        $result['page'] = intval($pageIndex);
        $result['pageSize'] = intval($pageSize);
        $result['pageCount'] = intval($pageCount);
        return $result;
    }

    /**
     * 根据角色id和状态获取事项,包含事项的处理人
     */
    private function getMatterAndHandler(array $roleIds, array $where, int $pageIndex, int $pageSize, array $whereColumn = [], bool $isEnd = false, string $userId = '') {
        // dd($roleId);
        // DB::connection()->enableQueryLog();
        // dd($where);
        $offset = ($pageIndex-1) * $pageSize;
        $sql = $this->leftJoin('wf_define', 'wf_node.task_id', '=', 'wf_define.task_id')
            ->rightJoin('wf_process', 'wf_define.task_id', '=', 'wf_process.task_id')
            // ->leftJoin('c_sale_order', 'wf_process.business_order', '=', 'c_sale_order.order_no')
            ->whereIn('wf_node.exec_role_id', $roleIds)
            ->where($where);
        //如果有字段条件
        if (count($whereColumn) > 0) {
            $sql = $sql->whereColumn($whereColumn);
        }
        if ($isEnd) {
            $sql = $sql->orWhere('wf_process.start_user_id', $userId);
        }
        $count = $sql->count('wf_process.process_id');
        $nodes = $sql->offset($offset)
            ->limit($pageSize)
            ->get(['wf_process.process_id','wf_process.current_node_index','wf_process.business_order','wf_process.process_desc','wf_process.create_time','wf_process.end_time','wf_process.status','wf_process.start_user_id','wf_process.start_user_name',
            'wf_define.task_name','wf_define.task_code',
            'wf_node.task_id','wf_node.node_index','wf_node.node_name',
            'wf_process.customer_name']);
        // dd(DB::getQueryLog());
        // dd($nodes);
        $res = [];
        if ($nodes->isEmpty()) {
            return $res;
        }
        $threadModel = new ThreadModel();
        // dd($nodes->toArray());
        foreach($nodes as $node) {
            $threadSql = $threadModel->where('process_id', $node->process_id);
        //     $where[] = ['wf_process.start_user_id', $userId];
        // $where[] = ['wf_thread.exec_user_id', $userId];
            if ($isEnd) {
                // $threadSql = $threadSql->orWhere('exec_user_id', $userId);
            }
            $thread = $threadSql->orderBy('times', 'desc')->orderBy('node_index', 'desc')->first();
        //     $task = $defineModel->where('task_id', $node->task_id)->first();
        //     if (empty($task)) {
        //         throw new CommonException(Errors::MATTER_DEFINE_NOT_FOUND);
        //     }
            
        //     $process = $processModel->where('task_id', $node->task_id)->where($where)->get(['process_id','task_id','current_node_index','business_order', 'process_desc', 'create_time', 'end_time', 'status', 'start_user_id', 'start_user_name']);  
        //     foreach ($process as $info) {
        //         $threads = $threadModel->where('process_id', $info->process_id)->get(['process_id', 'exec_user_id', 'exec_user_name']);
        //         if ($threads->isEmpty()) {
        //             throw new CommonException(Errors::MATTER_THREAD_NOT_FOUND);
        //         }
        //         foreach ($threads as $thread) {
                    $res[] = [
                        'process_id'        => $node->process_id,
                        'business_order'    => $node->business_order,
                        'matter_type'       => $node->task_name,
                        'matter_code'       => $node->task_code,
                        'node_name'         => $node->node_name,
                        'begin_time'        => $node->create_time,
                        'end_time'          => $node->end_time,
                        'start_user_id'     => $node->start_user_id,
                        'start_user_name'   => $node->start_user_name,
                        'exec_user_id'      => empty($thread) ? '' : $thread->exec_user_id,
                        'exec_user_name'    => empty($thread) ? '' : $thread->exec_user_name,
                        'custom_name'       => $node->customer_name,
                    ];
            // }
            
                
            }
            
            $pageCount = ceil($count/$pageSize); #计算总页面数 
            $result = [];
            $result['data'] = $res;
            $result['count'] = intval($count);
            $result['page'] = intval($pageIndex);
            $result['pageSize'] = intval($pageSize);
            $result['pageCount'] = intval($pageCount);
            return $result;
        
        // }
        
        
    }

    /**
     * 获取办结事项
     */
    private function getEndMatterHandler(array $roleIds, array $where, int $pageIndex, int $pageSize, array $whereColumn = []) {
        // dd($roleId);
        DB::connection()->enableQueryLog();
        // dd($roleIds);
        $rolesStr = implode("','", $roleIds);
        $offset = ($pageIndex-1) * $pageSize;
        $processModel = new ProcessModel();
        $sql = $processModel->leftJoin('wf_define', 'wf_process.task_id', '=', 'wf_define.task_id')
            // ->leftJoin('c_sale_order', 'wf_process.business_order', '=', 'c_sale_order.order_no')
            ->leftJoin('wf_thread', 'wf_process.process_id', '=', 'wf_thread.process_id')
            ->leftJoin(DB::raw("(SELECT exec_role_id,t_wf_define.task_id FROM t_wf_node LEFT JOIN t_wf_define ON t_wf_node.task_id = t_wf_define.task_id WHERE exec_role_id IN ('$rolesStr') LIMIT 1) AS t_roles"),  "wf_process.task_id", "=","roles.task_id")
            ->where($where)
            ->whereNotNull('roles.exec_role_id')
            ->groupBy('wf_process.process_id');
        $count = $sql->count('wf_process.process_id');
        $nodes = $sql->offset($offset)
            ->limit($pageSize)
            ->get(['wf_process.process_id', 'wf_process.business_order','wf_process.create_time', 'wf_process.end_time', 'wf_process.status', 
            'wf_define.task_name','wf_define.task_code','wf_process.customer_name']);
        // dd(DB::getQueryLog());
        // dd($nodes);
        $res = [];
        if ($nodes->isEmpty()) {
            return $res;
        }
        foreach($nodes as $node) {
            $res[] = [
                'process_id'        => $node->process_id,
                'business_order'    => $node->business_order,
                'matter_type'       => $node->task_name,
                'begin_time'        => $node->create_time,
                'end_time'          => $node->end_time,
                'custom_name'       => $node->customer_name,
                'matter_code'       => $node->task_code,
            ];
                
        }
            
        $pageCount = ceil($count/$pageSize); #计算总页面数 
        $result = [];
        $result['data'] = $res;
        $result['count'] = intval($count);
        $result['page'] = intval($pageIndex);
        $result['pageSize'] = intval($pageSize);
        $result['pageCount'] = intval($pageCount);
        return $result;
    }
}
