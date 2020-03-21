<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test()
    {
        //调取自定义配置文件
        //echo test();

        //自定义config
        echo config('info.WL_TITLE');
    }

    public function test2()
    {
        echo 123;
    }
}
