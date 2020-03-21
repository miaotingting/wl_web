<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\Menu;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\CommonException;

class MenuController extends Controller
{
    //get.api/Admin/menu  菜单列表树
    public function index()
    {
        $menu = new Menu();
        $data = $menu->getMenuList(); 
        return setTResult($data);
    }

    //post.api/Admin/menu  新建菜单
    public function store(Request $request)
    {
        $rules = [
            'menuName'=>'required|min:2|max:20|unique:sys_menu,menu_name,id',
            'menuType'=>'required|in:one_level,two_level,func_level',
            'menuUrl'=>'required',
            'sort'=>'max:3',
            'remark'  =>'max:20',
            'frontUrl' => 'required'
        ];
    
        $messages = [
            'required'=>':attribute为必填项',
            'menuName.unique'=>'菜单已注册',
            'min'=>':attribute长度过小',
            'max'=>':attribute长度过大',
            'menuType.in'=>'菜单类型错误'
        ];
        
        if($request->post('parentId') === '0'){
            $rules['parentId'] = 'required';
        }else{
            $rules['parentId'] = 'required|exists:sys_menu,id';
            $messages['parentId.exists'] = 'parentId不存在！';
        }

        $validator = Validator::make($request->all(), $rules, $messages,[
            'menuName'=>'菜单名称',
            'menuType'=>'菜单类型',
            'menuUrl'=>'菜单Url',
            'sort'=>'菜单排序',
            'remark'=>'备注',
            'parentId'=>'父ID',
            'frontUrl'=>'前端url',
        ]);

        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        if ((new Menu)->setMenuInsert($request, $this->user)) {
            return setFResult('0', '添加菜单成功！');
        } else {
            throw new CommonException('101154');
        } 

    }

    //get.api/Admin/menu/{menu}  查看信息
    public function show(Request $request, $id)
    {
        if(!empty($id)){
            $data = Menu::select([
                'id',
                'menu_name',
                'menu_url',
                'menu_type',
                'parent_id',
                'sort',
                'remark',
                'create_user_name'
            ])->find($id);
            return setTResult($data);
        }else{
            throw new CommonException('300003');
        } 

    }

    //put.api/Admin/menu/{menu}  修改菜单
    public function update(Request $request, $id)
    {
        $req = $request->all();
        $rules = [
            'menuName'=>'required|min:2|max:20',
            'menuType'=>'required|in:one_level,two_level,func_level',
            'menuUrl'=>'required',
            'sort'=>'max:3',
            'remark'  =>'max:20',
            'frontUrl' => 'required'
        ];
    
        $messages = [
            'required'=>':attribute为必填项',
            'min'=>':attribute长度过小',
            'max'=>':attribute长度过大',
            'menuType.in'=>'菜单类型错误'
        ];

        if(!$request->has('parentId')){
            throw new CommonException('300004');
        }
        if($req['parentId'] === '0'){
            $rules['parentId'] = 'required';
        }else{
            $rules['parentId'] = 'required|exists:sys_menu,id';
            $messages['parentId.exists'] = 'parentId不存在！';
        }
        
        $validator = Validator::make($req, $rules, $messages,[
            'menuName'=>'菜单名称',
            'menuType'=>'菜单类型',
            'menuUrl'=>'菜单Url',
            'sort'=>'菜单排序',
            'remark'=>'备注',
            'parentId'=>'父ID',
            'frontUrl'=>'前端url',
        ]);

        if($validator->fails()){
            return setFResult('100000', $validator->errors()->first());
        }
        if ((new Menu)->setMenuUpdate($req, $id)) {
            return setFResult('0', '修改菜单成功！');
        } else {
            throw new CommonException('101155');
        } 

    }

    //delete.api/Admin/menu/{menu}  删除菜单
    public function destroy(Request $request, $id)
    {
        if(empty($id)){
            throw new CommonException('300003');
        }
        $bool = (new Menu())->checkMenuRole($id);
        if($bool[0]){
            return setFResult('0', $bool[1]);
        }else{
            throw new CommonException('101156');
        } 
    }

}
