<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\NoticeModel;

class NoticeController extends Controller
{
    protected $rules = [
            'title'=>'required|max:150',
            'content'=>'required|max:500',
            'type'=>'required|integer',
            'level'=>'required|integer',
        ];
    protected $messages = [
            'required'=>':attribute 为必填项',
            'max'=>':attribute 长度不符合要求',
            'integer'=>':attribute 不符合要求',
        ];
    /*
     * get.api/Admin/notice
     * 系统公告列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new NoticeModel)->getNotice($request->all(),$this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /* 
     * post.api/Admin/notice
     * 新增系统公告
     */
    public function store(Request $request)
    {
        try{
            $validate = $this->validateStr($request->all(),'add');
            if($validate != 1){
                return $validate;
            }
            $result = (new NoticeModel)->addNotice($request->all(), $this->user);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101401');
            }
        } catch (Exception $ex) {
            throw new CommonException('101401');
        }
        
    }
    /**
     * get.api/Admin/notice/{$id}
     * 显示指定系统公告
     */
    public function show($id){
        try{
            $result = (new NoticeModel)->getNoticeInfo($id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    /**
     * put.api/Admin/notice/{$id}
     * 编辑系统公告
     */
    public function update(Request $request, $id){
        try{
            $validate = $this->validateStr($request->all(),'edit',$id);
            if($validate != 1){
                return $validate;
            }
            $result = (new NoticeModel)->updateNotice($request->all(),$id);
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101402');
            } 
        } catch (Exception $ex) {
            throw new CommonException('101402');
        }
        
    }
    /*
     * delete.api/Admin/notice
     * 删除系统公告
     */
    public function destroy($id){
        try{
            $result = (new NoticeModel)->destroyNotice($id);
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101403');//删除失败
            }
        } catch (Exception $ex) {
            throw new CommonException('101403');
        }
        
        
    }
    /*
     * 授权
     * post.api/Admin/notices/impowerNotice
     */
    public function impowerNotice(Request $request){
        try{
            $result = (new NoticeModel)->impowerNotice($request->all());
            if($result){
                return $this->success([]);
            }else{
                throw new CommonException('101406');
            }
        } catch (Exception $ex) {
            throw new CommonException('101406');
        }
    }
    /*
     * 删除授权
     * post.api/Admin/notices/deleteImpower
     */
    public function deleteImpower(Request $request){
        try{
            $rules = [
                'noticeId'=>'required',
                'paramId' =>'required'
            ];
            $validator = \Validator::make($request->all(),$rules,$this->messages,[
                                            'noticeId'=>'公告ID',
                                            'paramId'=>'授权信息ID',
                        ]);
            if($validator->fails()){
                return setFResult('100000', $validator->errors()->first());
            }
            $result = (new NoticeModel)->deleteImpower($request->all());
            if($result > 0){
                return $this->success([]);
            }else{
                throw new CommonException('101409');
            }
        } catch (Exception $ex) {
            throw new CommonException('101409');
        }
    }
    /*
     * 授权列表
     * get.api/Admin/notices/getImpowerList/1
     */
    public function getImpowerList(Request $request,$id){
        try{
            $result = (new NoticeModel)->getImpowerList($request->all(),$id);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 登录用户获取通知列表
     * get.api/Admin/notices/getAffiche
     */
    public function getAffiche(Request $request){
        try{
            $result = (new NoticeModel)->getMessage($request->all(), $this->user,0);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 登录用户获取公告列表
     * api/Admin/notices/getMessage
     */
    public function getMessage(Request $request){
        try{
            $result = (new NoticeModel)->getMessage($request->all(), $this->user,1);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
    }
    /*
     * 点击阅读操作
     * 一显示消息详细信息
     * 二把已读信息存入read_user表
     * get.api/Admin/notices/readNotice/05455b8b021458ea8e0afe4805c0be74
     */
    public function readNotice($id){
        try{
            $result = (new NoticeModel)->readNotice($id, $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101413');
        }
    }
    /*
     * 获取登录用户未读公告及通知个数
     * get.api/Admin/notices/getUnread
     */
    public function getUnread(){
        try{
            $result = (new NoticeModel)->getUnread($this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101414');
        }
    }
    /*
     * 验证器
     */
    public function validateStr($input,$name,$id=0){
        
        $validator = \Validator::make($input,$this->rules,$this->messages,[
                'title'=>'标题',
                'content'=>'内容',
                'type'=>'类型',
                'level'=>'授权级别',
            ]);
        if($validator->fails()){
            //$errors = $validator->errors()->getMessages();
            return setFResult('100000', $validator->errors()->first());
        }
        
        return 1;
    }
    
    
    
    
    

}
