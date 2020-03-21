<?php

namespace App\Http\Middleware;

use Closure;
use \Lcobucci\JWT\Parser;
use \Lcobucci\JWT\Signer\Hmac\Sha256;

class jwt_check
{
    const TOKEN = 'devadmin';

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
            //设置token直接过
            //把token放到参数里面
            request()->offsetSet('token', self::TOKEN);
            // request()->offsetSet('token', rand(100000,999999));
            return $next($request);
        }

        $signer  = new Sha256();

        $secret = config('info.JWT_SECRET');

        if($request->hasHeader('Authorization')){
            $token = $request->header('Authorization');
            //解析token
            $parse = (new Parser())->parse($token);
            //验证token合法性
            if (!$parse->verify($signer, $secret)) {
                return response()->json(setFResult('400003','令牌错误'));
            }

            //验证是否已经过期
            if ($parse->isExpired()) {
                return response()->json(setFResult('400003','令牌过期！'));
            }
        }else{
            return response()->json(setFResult('400003','令牌缺失！'));
        }
        //把token放到参数里面
        request()->offsetSet('token', $token);
        return $next($request);
    }
}
