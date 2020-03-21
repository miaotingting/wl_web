<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;
use App\Http\Models\Admin\User;
use App\Http\Utils\Errors;

class AdminLogin
{

    private $request;
    private $flag = false;

    private $filterUrl = [
        'api/Login/logout',    //退出登录
        'api/Admin/index',  //首页信息
        'api/Admin/users/updatePwd',   //修改密码
        'api/Card/updateMachineStatus',   //刷新卡片列表活动状态
        'api/WeChat/WeChatLogout',  //微信退出登录
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //判断环境
        if (in_array(config('app.env'), config('info.ENV_ARR'))) {
            //直接过
            return $next($request);
        }
        $user = Redis::get($request->header('Authorization'));
        if($request->hasHeader('Authorization')){
            $bool = empty($request->header('Authorization'))?true:false;
            if($bool){
                return response()->json(setFResult('400003', '令牌缺失！'));
            }else{
                if(empty($user)){
                    return response()->json(setFResult('400003', '令牌失效！'));
                }
            }
        }else{
            return response()->json(setFResult('400003', '令牌缺失！'));
        }

        
        //如果是在排除的url里面或者是get请求，则不用校验
        if (!in_array($request->path(), $this->filterUrl) && strtoupper($request->method()) != 'GET' && !str_contains($request->path(), 'WeChat')) {
            //判断权限
            $user = json_decode($user);
            if (empty($user->auth)) {
                return response()->json(setFResult(Errors::PERMISSION_ERROR, '权限错误'));
            }
            $this->request = $request;
            $this->flag = false;
            $this->checkPermission($user->auth);
            if (!$this->flag) {
                //没有权限
                return response()->json(setFResult(Errors::PERMISSION_ERROR, '权限错误'));
            }
        }
        
        return $next($request);
    }

    function checkPermission($permissions) {
        foreach ($permissions as $permission) {
            // dd($permission);
            if (property_exists($permission, 'children') && count($permission->children) > 0) {
                //如果没有.认为不是一个正确的url
                if (strpos($permission->menu_url, ".") === false) {
                    //如果是一级菜单，直接递归
                    $this->checkPermission($permission->children);
                } else {
                    //如果是二级菜单进行判断
                    $url = explode(".", $permission->menu_url);
                    $method = $url[0];
                    $urlArr = explode("/", $url[1]);
                    $str = $method . '.';
                    foreach ($urlArr as $key => $url) {
                        if (strpos($url, "{") == 0 && strpos($url, "}")) {
                            $str .= $url;
                        }
                        $str .= $this->request->segment($key + 1) . '/';
                    }
                    $str = rtrim($str, '/');
                    if ($str === $permission->menu_url && strtoupper($method) == strtoupper($this->request->method())) {
                        $this->flag = true;
                        return;
                    }
                    //如果这个二级菜单不是，再次递归
                    if ($this->flag == false) {
                        $this->checkPermission($permission->children);
                    }
                }
            } else {
                if (strpos($permission->menu_url, ".")) {
                    $url = explode(".", $permission->menu_url);
                    $method = $url[0];
                    $urlArr = explode("/", $url[1]);
                    $str = $method . '.';
                    foreach ($urlArr as $key => $url) {
                        if (strpos($url, "{") == 0 && strpos($url, "}")) {
                            $str .= $url;
                        }
                        $str .= $this->request->segment($key + 1) . '/';
                    }
                    $str = rtrim($str, '/');
                    if ($str === $permission->menu_url && strtoupper($method) == strtoupper($this->request->method())) {
                        $this->flag = true;
                        return;
                    }
                }
                
            }
        }
    }
}
