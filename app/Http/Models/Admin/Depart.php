<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use Illuminate\Http\Request;
use PhpParser\Node\Stmt\TryCatch;
use Mockery\CountValidator\Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CommonException;

class Depart extends BaseModel
{
    protected $table = 'sys_depart';

    public function getDepartList()
    {
        $data = $this->all()->toArray();
        $newData = array();
        foreach ($data as $key => $val) {
            $newData[$key]['id'] = $val['id'];
            $newData[$key]['depart_name'] = $val['depart_name'];
            $newData[$key]['parent_id'] = $val['parent_id'];
            $newData[$key]['remark'] = $val['remark'];
            $newData[$key]['created_at'] = $val['created_at'];
        }
        return $data = getTree($newData);
    }

    //添加部门
    public function setDepartInsert(Request $request, $adminUser)
    {
        $depart = new Depart();
        $depart->id = getUuid();
        $depart->depart_name = $request->post('departName');
        $depart->parent_id = $request->post('parentId');
        $depart->create_user = $adminUser['id'];
        $depart->remark = $request->has('remark') ? $request->post('remark') : '';
        $re = $depart->save();
        return $re ? true : false;
    }

    //修改部门
    public function setDepartUpdate(Request $request, $id)
    {
        $postData = $request->all();
        $depart = new Depart();
        $oldDepart = $depart::find($id);
        $oldDepart->depart_name = $postData['departName'];
        $oldDepart->parent_id = $postData['parentId'];
        $oldDepart->remark = !empty($postData['remark']) ? $postData['remark'] : '';
        $re = $oldDepart->save();
        return $re ? true : false;
    }

    public function mamagerUser()
    {
        return $this->belongsTo('App\Http\Models\User', 'create_user');
    }

    /**
     * 删除部门
     * @param [type] $id
     * @return void
     */
    public function checkDepartUser($id)
    {
        try{
            $bool = false;
            $back = array();
            $departObj = self::select(['id', 'depart_name', 'parent_id'])->find($id);
            if(empty($departObj)){
                throw new CommonException('300002');
            }
            DB::beginTransaction();
            if ($departObj->parent_id == '0') {
                //一级
                $temp = array();
                $departArr = self::where('id', $id)->orWhere('parent_id', $id)->get(['id'])->toArray();
                foreach($departArr as $va){
                    $temp[] = $va['id'];
                }
                $userArr = User::whereIn('depart_id', $temp)->get(['id'])->toArray();
                // $userArr < 1 说明$id 部门没有用户，可以删除
                if(count($userArr) < 1){
                    foreach($temp as $v){
                        self::find($v)->delete();
                    }
                    $bool = true;
                }
            } else {
                // 二级
                $userArr = User::where('depart_id', $id)->get(['id'])->toArray();
                if(count($userArr) < 1) {
                    self::find($id)->delete();
                    $bool = true;
                }
            }

            //提交事务 删除成功
            if($bool){
                DB::commit();
                $back[] = $bool;
                $back[] = '删除部门成功！';
            } else{
                DB::rollBack();
                throw new CommonException('101052');
            }
            return $back;  
        }catch (Exception $e){
            DB::rollBack();
            //var_dump($ex->getMessage());exit;//获取异常信息
            throw new CommonException('101051');
        }
    }




}
