<?php

namespace App\Http\Controllers\Profit;

use Illuminate\Http\Request;
use App\Http\Controllers\QueryList\QueryController;
use App\Exceptions\CommonException;
use App\Http\Models\Profit\TCProfitDetailModel;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Operation\Package;
use App\Http\Models\Profit\TCProfitModel;
use App\Http\Controllers\QueryList\Filters\WithFields;
use App\Http\Controllers\QueryList\Filters\WithJoins;


class TCProfitDetailController extends QueryController implements WithJoins,WithFields
{

    protected $shortTableName = 'c_profit_detail as detail';

    protected $orderByFiled = 'p.created_at';

    protected function getModel() {
        return new TCProfitDetailModel;
    }

    /*
     * get.api/Profit/profitDetails/{$profitCode}
     * 分润规则明细列表（any one）
     */
    public function profitDetails(Request $request,$profitCode)
    {
        if(empty($this->user)){
            throw new CommonException('300001');
        }
        try{
            $result = (new TCProfitDetailModel())->profitDetails($profitCode,$request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
     /**
     * 创建
     * @apiRoute post.api/info
     */
    function createInfo(Request $request, Customer $customer, TCProfitModel $profitModel, Package $packageModel) {
        try{
            $rules = [
                'packageId'=>'required|exists:c_package,id',
                'salePrice' => 'required',
                'isSale' => 'required',
                'profitId' => 'required|exists:c_profit,id',
            ];
            $messages = [
                'packageId.required'=>'套餐id为必填项',
                'salePrice.required'=>'销售价格为必填项',
                'isSale.required'=>'在售状态为必填项',
                'profitId.required'=>'分润为必填项',
                'packageId.exists'=>'套餐id不存在',
                'profitId.exists'=>'分润不存在',
            ];
            $this->valid($request,$rules,$messages);

            //判断是否登录
            if (!empty($this->user)){

                $profitId = $request->input('profitId');
                //判断主表状态是否审核通过
                $profit = $profitModel->where('id', $profitId)->first(['id', 'status', 'customer_id']);
                if ($profit->status == TCProfitModel::CHECKING_STATUS) {
                    //审核中不能新建
                    throw new CommonException(111055);
                }

                $customerId = $profit->customer_id;

                $levelArr = [0,1]; //允许进行创建操作的客户级别
                if (!in_array($this->user['customer_level'], $levelArr)) {
                    throw new CommonException(111052);
                } else {
                    //判断是否是下级客户
                    if (!$customer->checkLevel($customerId, ++$this->user['customer_level'])) {
                        //不是下级客户
                        throw new CommonException(111051);
                    }
                }

                $packageId = $request->input('packageId');

                //判断套餐是否已经存在
                if (!empty($this->getModel()->where('profit_id', $profitId)->where('package_id', $packageId)->first(['id','package_id']))) {
                    //套餐已经存在分润
                    throw new CommonException(111054);
                }

                //查询套餐
                $package = $packageModel->where('id', $packageId)->first(['id', 'package_name']);
                
                $data['packageId'] = $packageId;
                $data['packageName'] = $package->package_name;
                $data['salePrice'] = $request->input('salePrice');
                $data['isSale'] = $request->input('isSale');
                $data['profitId'] = $profitId;

                //判断成本价
                if ($request->has('costPrice') && $request->input('costPrice') > 1) {

                    //当前登录用户的这个套餐的价格
                    $cusProfit = $profitModel->where('customer_id', $this->user['customer_id'])->first(['customer_id', 'id']);
                    $cusDetail = $this->getModel()->where('profit_id', $cusProfit->id)->where('package_id', $packageId)->first(['id','package_id', 'sale_price']);
                    if (empty($cusDetail)) {
                        throw new CommonException(111058);
                    }
                    if ($request->input('costPrice') < $cusDetail->sale_price) {
                        throw new CommonException(111059);
                    }
                    if ($data['salePrice'] < $request->input('costPrice')) {
                        throw new CommonException(111060);
                    }

                    $data['costPrice'] = $request->input('costPrice');
                    $data['profitPrice'] = bcsub(strval($data['salePrice']), strval($data['costPrice']));
                } else {
                    //修改主表状态为未审核
                    $profit->status = TCProfitModel::BEGIN_STATUS;
                    $profit->save();
                }
                
                //创建
                $this->create($data);

                
            }
            
            return $this->success(true);
        }catch(Exception $ex) {

        }
    }
    

    function updateInfo(Request $request, Customer $customer, TCProfitModel $profitModel, $id) {
        try{
            $rules = [
                'salePrice' => 'required',
                'isSale' => 'required',
                'profitId' => 'required',
            ];
            $messages = [
                'salePrice.required'=>'销售价格为必填项',
                'isSale.required'=>'在售状态为必填项',
                'profitId.required'=>'分润为必填项',
            ];
            $this->valid($request,$rules,$messages);

            //判断是否登录
            if (!empty($this->user)){

                $profitId = $request->input('profitId');
                //判断主表状态是否审核通过
                $profit = $profitModel->where('id', $profitId)->first(['id', 'status', 'customer_id']);
                if ($profit->status == TCProfitModel::CHECKING_STATUS) {
                    //审核中不能新建
                    throw new CommonException(111055);
                }

                $customerId = $profit->customer_id;

                $levelArr = [0,1]; //允许进行创建操作的客户级别
                if (!in_array($this->user['customer_level'], $levelArr)) {
                    throw new CommonException(111052);
                } else {
                    //判断是否是下级客户
                    if (!$customer->checkLevel($customerId, ++$this->user['customer_level'])) {
                        //不是下级客户
                        throw new CommonException(111051);
                    }
                }

                if (empty($profitDetail = $this->getModel()->where('id', $id)->first(['id', 'sale_price', 'package_id']))) {
                    //不存在分润，无法修改
                    throw new CommonException(111054);
                }

                
                
                $data['salePrice'] = $request->input('salePrice');
                $data['isSale'] = $request->input('isSale');

                //判断成本价
                if ($request->has('costPrice') && $request->input('costPrice') > 1) {
                    //当前登录用户的这个套餐的价格
                    $cusProfit = $profitModel->where('customer_id', $this->user['customer_id'])->first(['customer_id', 'id']);
                    $cusDetail = $this->getModel()->where('profit_id', $cusProfit->id)->where('package_id', $profitDetail->package_id)->first(['id','package_id', 'sale_price']);
                    if (empty($cusDetail)) {
                        throw new CommonException(111058);
                    }
                    if ($request->input('costPrice') < $cusDetail->sale_price) {
                        throw new CommonException(111059);
                    }
                    if ($data['salePrice'] < $request->input('costPrice')) {
                        throw new CommonException(111060);
                    }
                    $data['costPrice'] = $request->input('costPrice');
                    $data['profitPrice'] = bcsub(strval($data['salePrice']), strval($data['costPrice']));
                } else {
                    if (bcsub(strval($profitDetail->sale_price), strval($request->input('salePrice')), 2) != 0) {
                        //修改了价格需要重新审核
                        //修改主表状态为未审核
                        $profit->status = TCProfitModel::BEGIN_STATUS;
                        $profit->save();
                    }
                }
                
                //创建
                $this->update($id, $data);
            }
            
            return $this->success(true);
        }catch(Exception $ex) {

        }
    }

    function getJoins(): array
    {
        return [['c_profit as pro','pro.id','=','detail.profit_id']];
    }
    
    // 获取字段
    public function getFields():array{
        return ['detail.id','detail.profit_id','detail.package_id','detail.package_name','detail.is_sale','detail.created_at', 'pro.customer_id'];
    }

    /*
     * 查询列表
     * @route get.api/lists
     */
    public function getList(){
        try{
            //检查页码，搜索条件等
            $this->pageValid();
            
            //返回数据
            $data = $this->pageList();
            return $this->success($data);
        } catch (Exception $ex) {
            
        }
        
    }  

}
