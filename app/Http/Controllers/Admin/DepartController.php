<?php

namespace App\Http\Controllers\Admin;

use App\Http\Models\Admin\Depart;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\CommonException;

class DepartController extends Controller
{

    protected $rules = [
        'departName'=>'required|min:2|max:20|unique:sys_depart,depart_name,id',
        'parentId' => 'required|exists:sys_depart,id'
    ];

    protected $messages = [
        'required'=>':attribute 为必填项',
        'departName.unique'=>'该用户名已经被注册',
        'min'=>':attribute长度不符合要求',
        'max'=>':attribute长度不符合要求',
        'parentId.exists' => 'parentId不存在！'
    ];

    //get.api/Admin/depart  部门列表
    public function index()
    {
        $data = backTree((new Depart)->getDepartList());
        return setTResult($data);
    }

    //post.api/Admin/depart  添加入库
    public function store(Request $request)
    {
        $rules = $this->rules;
        $validator = Validator::make($request->all(), $this->rules, $this->messages,[
            'departName'=>'部门名称',
            'parentId'=>'父ID',
        ]);

        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
            /* $errors = $validator->errors()->getMessages();
            foreach($errors as $value){
                return setFResult('001', $value[0]);
            }   */
        }
        if ((new Depart)->setDepartInsert($request, $this->user)) {
            return setFResult('0', '添加部门成功！');
        } else {
            throw new CommonException('101054');
        }
    }

    //get.api/Admin/depart/{depart}  查看信息
    public function show(Request $request, $id)
    {
        if(!empty($id)){
            //带指定字段的查询
            $data = Depart::select([
                'id',
                'depart_name',
                'create_user',
                'parent_id',
                'remark',
                'created_at'
            ])->with(['mamagerUser' => function ($q) {
                $q->select('id', 'real_name');}
            ])->find($id);

            return setTResult($data);

            /*//depart不指定字段
            $depart = Depart::find('asdasd');
            $depart->getUserInfo;
            return $depart;*/
        }else{
            throw new CommonException('300003');
        }
    }

    //put.api/Admin/depart/{depart}  修改入库
    public function update(Request $request, $id)
    {
        $rules = [
                'departName'=>'required|min:2|max:20',
                'parentId' => 'required|exists:sys_depart,id'
            ];

        $messages = [
                'required'=>':attribute 为必填项',
                'min'=>':attribute长度不符合要求',
                'max'=>':attribute长度不符合要求',
                'parentId.exists' => 'parentId不存在！'
            ];
        $validator = Validator::make($request->all(),$rules,$this->messages,[
                                        'departName'=>'部门名称',
                                        'parentId'=>'父ID',
                                    ]);

        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
            /* $errors = $validator->errors()->getMessages();
            foreach($errors as $value){
                return setFResult('001', $value[0]);
            }   */
        }
        if ((new Depart)->setDepartUpdate($request, $id)) {
            return setFResult('0', '修改部门成功！');
        } else {
            throw new CommonException('101053');
        }
  
    }

    //delete.api/Admin/depart/{depart}  删除部门
    public function destroy(Request $request, $id)
    {
        $bool = (new Depart())->checkDepartUser($id);
        if($bool[0]){
            return setFResult('0', $bool[1]);
        }else{
            throw new CommonException('101051');
        }
    }

}
