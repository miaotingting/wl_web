<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;

class Menu extends BaseModel
{
    protected $table  = 'sys_menu';

    //角色菜单树
    public function getMenuList()
    {
        $data = $this->orderBy('sort')->get(['id','menu_name','menu_type','menu_url','parent_id','remark','sort','create_user_name','front_url','menu_icon'])->toArray();
        $newData = getTree($data);
        return backTree($newData);
    }

    //添加部门
    public function setMenuInsert($request, $user)
    {
        $menu = new Menu();
        $menu->id = getUuid();
        $menu->menu_name = $request->post('menuName');
        $menu->parent_id = $request->post('parentId');
        $menu->menu_url = $request->post('menuUrl');
        $menu->menu_type = $request->post('menuType');
        $menu->create_user_id = $user['id'];
        $menu->create_user_name = $user['real_name'];
        $menu->sort = empty($request->post('sort'))?'100':$request->post('sort');
        $menu->remark = $request->has('remark') ? $request->post('remark') : '';
        $menu->front_url = $request['frontUrl'];
        $menu->menu_icon = array_get($request, 'menuIcon', 'default_icon');
        $re = $menu->save();
        return $re ? true : false;
    }

    //修改菜单
    public function setMenuUpdate($request, $id)
    {
        $oldMenu = $this->find($id);
        $oldMenu->menu_name = $request['menuName'];
        $oldMenu->parent_id = $request['parentId'];
        $oldMenu->menu_url = $request['menuUrl'];
        $oldMenu->menu_type = $request['menuType'];
        $oldMenu->sort = empty($request['sort'])?'100':$request['sort'];
        $oldMenu->remark = !empty($request['remark']) ? $request['remark'] : '';
        $oldMenu->front_url = $request['frontUrl'];
        $oldMenu->menu_icon = array_get($request, 'menuIcon', 'default_icon');
        $re = $oldMenu->save();
        return $re ? true : false;
    }
    
    //检查菜单角色是否可删除
    public function checkMenuRole($id)
    {
        DB::beginTransaction();
        try{
            $bool = false;
            $back;
            $menuObj = self::select(['id', 'menu_name', 'parent_id'])->find($id);
            if ($menuObj->parent_id == '0') {
                //一级
                $temp = array();
                $menuArr = self::where('id', $id)->orWhere('parent_id', $id)->get(['id'])->toArray();
                if(count($menuArr) > 1){
                    //有子菜单不允许删除
                    throw new CommonException('101151');
                }else{
                    //删除角色菜单
                    $roleMenu = RoleMenu::where('menu_id', $id)->get(['role_menu_id']);
                    if(count($roleMenu) > 0){
                        throw new CommonException('101152');
                    }else{
                        //删除菜单
                        self::find($id)->delete();
                        $bool = true;
                    }
                }
            } else {
                // 二级
                $roleMenu = RoleMenu::where('menu_id', $id)->get(['role_menu_id']);
                if(count($roleMenu) > 0){
                    throw new CommonException('101152');
                }else{
                    //删除菜单
                    self::find($id)->delete();
                    $bool = true;
                }
            }

            //提交事务 删除成功
            if($bool){
                DB::commit();
                $back = [$bool, '删除菜单成功！'];
            } else{
                DB::rollBack();
            }
            return $back;  
        }catch (Exception $e){
            DB::rollBack();
            //var_dump($ex->getMessage());exit;//获取异常信息
            throw new CommonException('101153');
        }
    }


}
