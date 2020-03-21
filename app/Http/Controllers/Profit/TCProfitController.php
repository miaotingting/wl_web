<?php

namespace App\Http\Controllers\Profit;

use Illuminate\Http\Request;
use App\Http\Controllers\QueryList\QueryController;
use App\Exceptions\CommonException;
use App\Http\Models\Profit\TCProfitModel;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Admin\User;
use App\Http\Controllers\QueryList\Filters\WithFields;
use App\Http\Controllers\QueryList\Filters\WithJoins;
use App\Http\Controllers\QueryList\Filters\WithWheres;
use App\Http\Models\Matter\ProcessModel;
use App\Http\Models\Matter\ThreadModel;
use App\Http\Models\Profit\TCProfitDetailModel;

class TCProfitController extends QueryController implements WithJoins,WithFields,WithWheres
{
    
    protected $shortTableName = 'c_profit as p';

    protected $orderByFiled = 'p.created_at';

    /**
     * 字典数组
     * ['表里的字段名' => '字典code',...]
     */
    protected $dicArr = [
        'profit_type' => 't_c_profit_type',
        'status' => 't_c_profit_status'
    ];

    protected function getModel() {
        return new TCProfitModel;
    }

    function getJoins(): array
    {
        return [['sys_customer as cus','p.customer_id','=','cus.id']];
    }
    
    // 获取字段
    public function getFields():array{
        return ['p.id','p.customer_id','cus.customer_name','p.profit_type','p.status','cus.customer_code', 'p.profit_code', 'p.created_at', 'cus.account_manager_name'];
    }

    function getWheres(array $search): array
    {
        if (!empty($search)) {
            foreach($search as $k => $v) {
                $search[$k] = ['like', $v];
            }
        }
        if ($this->user['customer_level'] != 0) {
            $search['cus.parent_id'] = $this->user['customer_id'];
        }
        $search['cus.level'] =  ++$this->user['customer_level'];
        
        //销售只能看自己的


        $where = parent::getWheres($search);
        return $where;
    }

    /*
     * 查询列表
     * @route get.api/lists
     */
    public function getList(Request $request){
        try{
            //检查页码，搜索条件等
            $this->pageValid();
            
            //返回数据
            $data = $this->pageList();
            return $this->success($data);
        } catch (Exception $ex) {
            
        }
        
    }  

    /**
     * 查询一条记录
     */
    function detail($no) {
        try{
            //查询记录
            $data = TCProfitModel::from($this->shortTableName)
                    ->leftJoin('sys_customer as cus','p.customer_id','=','cus.id')
                    ->where('p.profit_code',$no)
                    ->first(['p.id','p.customer_id','cus.customer_name','p.profit_type','p.status','cus.customer_code', 'p.profit_code', 'p.created_at', 'cus.account_manager_name']);
            if (empty($data)) {
                throw new CommonException('111056');
            }
            
            return $this->success($data);
        }catch(Exception $ex) {
            throw new CommonException('101010');
        }
    }

    /*
     * get.api/Profit/myProfit
     * 我的分润
     */
    public function myProfit(Request $request)
    {
        if(empty($this->user)){
            throw new CommonException('300001');
        }
        try{
            $result = $this->getModel()->myProfit($this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }

    /**
     * 创建
     * @apiRoute post.api/info
     */
    function createInfo(Request $request, Customer $customer, User $userModel) {
        try{
            $rules = [
                'customerId'=>'required|exists:sys_customer,id|unique:c_profit,customer_id',
                'profitType' => 'required',
            ];
            $messages = [
                'customerId.required'=>'客户为必填项',
                'customerId.exists'=>'客户不存在',
                'customerId.unique'=>'客户已经存在分润',
                'profitType.required'=>'分润模式为必填项',
            ];
            $this->valid($request,$rules,$messages);

            $customerId = $request->input('customerId');

            //判断是否登录
            if (!empty($this->user)){
                $levelArr = [0,1,2]; //允许进行创建操作的客户级别
                if (!in_array($this->user['customer_level'], $levelArr)) {
                    throw new CommonException(111052);
                } else {
                    //判断是否是下级客户
                    if (!$customer->checkLevel($customerId, ++$this->user['customer_level'])) {
                        //不是下级客户
                        throw new CommonException(111051);
                    }
                }
                
                //不是网来的给下级客户设置的时候，类型使用他自己的
                if ($this->user['is_owner'] == 0) {
                    //查询当前用户的customerid
                    $userInfo = $userModel->where('id', $this->user['id'])->first(['id', 'customer_id']);
                    $profit = $this->getModel()->where('customer_id', $userInfo->customer_id)->where('status', TCProfitModel::CHECKED_STATUS)->first(['id', 'customer_id', 'profit_type', 'status']);
                    if (empty($profit)) {
                        throw new CommonException(111053);
                    }
                    $data['profitType'] = $profit->profit_type;
                    $data['status'] = $profit->status;
                } else {
                    $data['profitType'] = $request->input('profitType');
                    $data['status'] = TCProfitModel::BEGIN_STATUS;
                }

                //查询客户名称
                $cus = $customer->where('id', $customerId)->first(['id', 'customer_name']);
                
                $data['customerId'] = $customerId;
                $data['customerName'] = $cus->customer_name;
                $data['profitCode'] = getOrderNo(TCProfitModel::PREFIX);
                
                //创建
                $this->create($data);
            }
            
            return $this->success(true);
        }catch(Exception $ex) {

        }
    }
    
    function matter(Request $request) {
        try{
            $rules = [
                'profitId'=>'required|exists:c_profit,id',
            ];
            $messages = [
                'profitId.required'=>'分润为必填项',
                'profitId.exists'=>'分润为必填项',
            ];
            $this->valid($request,$rules,$messages);

            $profitId = $request->input('profitId');

            //判断是否登录
            if (!empty($this->user)){
                //判断主表是否为空
                $profit = $this->getModel()->where('id', $profitId)->first(['id', 'status', 'profit_code', 'customer_name']);
                if (empty($profit)) {
                    throw new CommonException(111056);
                }

                //判断主表状态
                if ($profit->status != TCProfitModel::BEGIN_STATUS) {
                    throw new CommonException(111057);
                }

                //判断子表是否为空
                $detail = TCProfitDetailModel::where('profit_id', $profitId)->first(['profit_id', 'id']);
                if (empty($detail)) {
                    throw new CommonException(111056);
                }

                $process = ProcessModel::where('business_order', $profit->profit_code)->first(['business_order', 'process_id']);
                if (!empty($process)) {
                    ThreadModel::where('process_id', $process->process_id)->delete();
                    ProcessModel::where('business_order', $profit->profit_code)->delete();
                }
                
                //开启流程
                $this->startProcess(TCProfitModel::TASK_CODE, $profit->profit_code, $this->user, $profit->customer_name);
            }
            
            return $this->success(true);
        }catch(Exception $ex) {

        }
    }
}
