<?php

namespace App\Http\Controllers\Operation;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\CommonException;
use App\Http\Models\Operation\TCWarehouseModel;
use Illuminate\Validation\Rule;
use App\Http\Models\Operation\TCWarehouseOrderDetailModel;

class TCWarehouseController extends Controller
{
    protected $rules = [
        ];
    protected $messages = [
            'required'=>':attribute为必填项',
            'mobile'=>':attribute格式错误',
            'unique'=>'该:attribute已经被注册',
        ];
    
    /*
     * get.api/Operation/warehouse
     * 仓库列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new TCWarehouseModel)->getWarehouse($request->all());
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }

    /* 
     * 新建仓库
     * post.api/Operation/warehouse
     */
    public function store(Request $request)
    {
        try{
            //$this->rules['warehouseId'] = 'required';
            $this->rules['adminId'] = 'required';
            $this->rules['tel'] = 'required|mobile';
            $this->rules['wareName'] = 'required|unique:c_warehouse,ware_name,id';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseModel)->addWarehouse($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('103101');
            } 
        } catch (Exception $ex) {
            throw new CommonException('103101');
        }
        
    }
    /**
     * put.api/Operation/warehouse/{id}
     * 编辑仓库
     */
    public function update(Request $request, $id){
        
        try{
            $this->rules['adminId'] = 'required';
            $this->rules['tel'] = 'required|mobile';
            $this->rules['wareName'] = 'required|unique:c_warehouse,ware_name,'.$id;
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseModel)->updateWarehouse($request->all(),$id, $this->user);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('103103');
            }
        } catch (Exception $ex) {
            throw new CommonException('103103');
        }
        
    }
    /**
     * get.api/Operation/warehouse/{id}
     * 获取某仓库信息
     */
    public function show($id){
        try{
            $result = (new TCWarehouseModel)->getWarehouseInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /*
     * 仓库的卡片信息
     */
    public function inventoryCards(Request $request){
        try{
            $this->rules['warehouseId'] = 'required';
            $validate = $this->validateStr($request->all());
            if($validate != 1){
                return $validate;
            }
            $result = (new TCWarehouseOrderDetailModel)->getWareOrderCards($request->all(),$this->user,'warehouse');
            if($result){
                return $this->success($result);
            }else{
                throw new CommonException('101010');
            } 
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input){
        $validator = \Validator::make($input,$this->rules,$this->messages,[
            'wareName'=>'仓库名称',
            'adminId'=>'负责人',
            'tel'=>'联系电话',
            'warehouseId'=>'仓库ID',
        ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        return 1;
    }
    

}
