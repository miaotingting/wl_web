<?php

namespace App\Http\Controllers\JWT;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \Lcobucci\JWT\Builder;
use \Lcobucci\JWT\Signer\Hmac\Sha256;
use Illuminate\Support\Facades\Redis;

class RequireTokenController extends Controller
{
    public function requireToken(Builder $builder, Sha256 $signer) {
        
        $secret = config('info.JWT_SECRET');
        $time = time();
        $expTime = config('info.JWT_EXP_TIME');
        
        do {
            //设置header和payload，以下的字段都可以自定义
            $builder->setIssuer("cmp.wliot.com") //发布者
                    ->setAudience("cmp.wliot.com") //接收者
                    ->setId("abc", true) //对当前token设置的标识
                    ->setIssuedAt($time) //token创建时间
                    ->setExpiration($time + $expTime) //过期时间
                    // ->setNotBefore($time + 5) //当前时间在这个时间前，token不能使用
                    ->set('uid', 30061); //自定义数据
            
            //设置签名
            $builder->sign($signer, $secret);
            //获取加密后的token，转为字符串
            $token = (string)$builder->getToken();
        } while (Redis::exists($token));
        //存入redis
        // Redis::setex($token, $expTime, json_encode([]));
        
        return setTResult($token);
    }
}
