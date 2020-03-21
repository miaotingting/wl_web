<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\RoleUser;
use App\Exceptions\CommonException;

class RoleUserController extends Controller
{
    protected $rules = [
            'roleId'=>'required',
            'userId'=>'required',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
        ];
     /**
     * 从指定角色中添加用户
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        try{
            $validator = \Validator::make($request->all(),$this->rules,$this->messages,[
                ]);
            if($validator->fails()){
                return setFResult('100000', $validator->errors()->first());
            }
            $result = (new RoleUser)->addUser($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101004');
            }
        } catch (Exception $ex) {
            throw new CommonException('101004');
        }
        
    }
    


    
        
        
}
