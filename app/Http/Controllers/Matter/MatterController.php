<?php

namespace App\Http\Controllers\Matter;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\RoleUser;
use App\Http\Models\Matter\NodeModel;
use App\Http\Models\Matter\ThreadModel;
use PHPUnit\Framework\Exception;
use App\Http\Models\Matter\ProcessModel;
use App\Exceptions\CommonException;
use App\Http\Utils\Errors;
use App\Http\Models\Matter\DefineModel;

/**
 * 事项处理
 */
class MatterController extends Controller
{

    private $roleUser;
    private $nodeModel;
    private $threadModel;
    private $processModel;
    const NOT_FOUND = 'not_found';
    const DEFAULT_USER_ID = '0';

    protected $rules = [
        'page'=>'required',
        'pageSize'=>'required',
    ];
    protected $messages = [
        'page.required'=>'页码为必填项',
        'pageSize.required'=>'每页数量为必填项',
    ];
    
    function __construct(Request $request, RoleUser $roleUser, NodeModel $nodeModel, ThreadModel $threadModel, ProcessModel $processModel)
    {
        parent::__construct($request);
        $this->roleUser = $roleUser;
        $this->nodeModel = $nodeModel;
        $this->threadModel = $threadModel;
        $this->processModel = $processModel;
    }

    /**
     * 获取所有待办事项
     */
    function getbacklogs(Request $request) {
        try {
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
            $userId = array_get($this->user, 'id', self::DEFAULT_USER_ID);
            //获取角色id
            $roleIds = $this->roleUser->getRoleIdByUser($userId);
            //查询待办事项
            $res = $this->nodeModel->getBacklogMatter($roleIds, intval($pageIndex), intval($pageSize), $search);
            return $this->success($res);
            
        } catch(Exception $e) {
            
        }
    }

    /**
     * 获取办结事项
    */
    function getEnds(Request $request) {
        try {
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
            $userId = array_get($this->user, 'id', self::DEFAULT_USER_ID);
            //获取角色id
            $roleId = $this->roleUser->getRoleIdByUser($userId);
            $res = $this->nodeModel->getEndMatter($roleId, $userId, intval($pageIndex), intval($pageSize), $search);
            return $this->success($res);
            
        } catch(Exception $e) {
            
        }
    }

    /**
     * 获取我创建的
     */
    function getCreatedMatter(Request $request) {
        try {
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
            $userId = array_get($this->user, 'id', self::DEFAULT_USER_ID);
            //获取角色id
            $roleId = $this->roleUser->getRoleIdByUser($userId);
            $res = $this->nodeModel->getCreatedMatter($roleId, $userId, intval($pageIndex), intval($pageSize), $search);
            return $this->success($res);
        } catch(Exception $e) {
            
        }
    }

    /**
     * 获取已办事项
     */
    function getAlreadyMatter(Request $request) {
        try {
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
            $userId = array_get($this->user, 'id', self::DEFAULT_USER_ID);
            //获取角色id
            $roleId = $this->roleUser->getRoleIdByUser($userId);
            $res = $this->nodeModel->getAlreadyMatter($roleId, $userId, intval($pageIndex), intval($pageSize), $search);
            return $this->success($res);
        } catch(Exception $e) {
            
        }
        
    }

    /**
     * 查询进程下的所有线程
     */
    function getThreads(Request $request) {
        try{
            //参数验证
            $this->rules = array_collapse([$this->rules, [
                'businessOrder'=>'required',
            ]]);
            $this->messages = array_collapse([$this->messages, [
                'businessOrder.required' => '订单号为必填项'
            ]]);
            $this->valid($request);

            //参数处理
            $reqs = $request->all();
            
            $pageIndex = array_get($reqs, 'page', 1);
            $pageSize = array_get($reqs, 'pageSize', 10);
            $businessOrder = array_get($reqs, 'businessOrder');
            $res = $this->threadModel->getThreads($businessOrder, intval($pageIndex), intval($pageSize));
            return $this->success($res);
        } catch(Exception $e) {

        }
    }

    /**
     * 处理操作的参数验证
     */
    private function handleValid(Request $request) {
        //参数验证
        $this->rules = [
            'processId'=>'required',
        ];
        $this->messages = [
            'processId.required' => '进程id为必填项'
        ];
        $this->valid($request);
    }

    /**
     * 同意操作
     */
    function agree(Request $request) {
        try{
            //参数验证
            $this->handleValid($request);

            $processId = $request->input('processId');
            $desc = '';
            if ($request->has('desc')) {
                $desc = $request->input('desc');
            }

            //获取角色id
            $userId = array_get($this->user, 'id', self::DEFAULT_USER_ID);
            $roleId = $this->roleUser->getRoleIdByUser($userId);
            $node = $this->nodeModel->whereIn('exec_role_id', $roleId)->first();
            if (empty($node)) {
                throw new CommonException(Errors::MATTER_ROLE_ERROR);
            }


            //同意
            $this->processModel->agree($processId, $desc, $this->user);
            return $this->success();
        } catch(Exception $e) {
            DB::rollBack();
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 驳回操作
     */
    function reject(Request $request) {
        try{
            //参数验证
            $this->handleValid($request);

            $processId = $request->input('processId');
            $desc = '';
            if ($request->has('desc')) {
                $desc = $request->input('desc');
            }
            
            //驳回
            $this->processModel->reject($processId, $desc, $this->user);
            return $this->success();

        }catch(Exception $e) {
            DB::rollBack();
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 作废
     */
    function delete(Request $request) {
        try {
            //参数验证
            $this->handleValid($request);

            $processId = $request->input('processId');
            $desc = '';
            if ($request->has('desc')) {
                $desc = $request->input('desc');
            }
            //作废
            $this->processModel->deleteProcessHandle($processId, $desc, $this->user);
            return $this->success();
        }catch(Exception $e) {
            DB::rollBack();
            throw new CommonException(Errors::DATABASE_ERROR);
        }
    }

    /**
     * 获取事项类型
     */
    function getTaskNames() {
        $defineModel = new DefineModel;
        $taskNames = $defineModel->pluck('task_name');
        return $this->success($taskNames);
    }
}
