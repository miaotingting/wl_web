<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Utils\Response as UserResponse;
use App\Exceptions\ValidaterException;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use UserResponse;
    protected $user = [];

    protected $rules = [];
    protected $messages = [];
    
    const TOKEN = 'devadmin';

    public function __construct(Request $request) {
        if (in_array(config('app.env'), config('info.ENV_ARR'))) {
            request()->offsetSet('token',self::TOKEN);
            if (Redis::exists(self::TOKEN)) {
                $adminUser = Redis::get(self::TOKEN);
                $infos = json_decode($adminUser, true);
                $this->user = array_get($infos, 'user', []);
            };
        }
        if ($request->hasHeader('Authorization')) {
            $token = $request->header('Authorization');
            //获取用户信息
            if (Redis::exists($token)) {
                $adminUser = Redis::get($token);
                $infos = json_decode($adminUser, true);
                $this->user = array_get($infos, 'user', []);
            };
        }
    }

    /**
     * 验证器
     */
    protected function valid(Request $request, array $rules = [], array $messages = []) {
        $rules = count($rules) > 0 ? $rules : $this->rules;
        $messages = count($messages) > 0 ? $messages : $this->messages;
        $validator = Validator::make($request->all(),$rules,$messages);
        if($validator->fails()){
            $arr = [
                'code' => '100000',
                'msg' => $validator->errors()->first(),
            ];
            
            throw new ValidaterException(json_encode($arr));
        }
    }

}
