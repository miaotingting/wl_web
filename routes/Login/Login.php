<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/code', function() {
    // 生成验证码 直接返回给前端
    $code = app('captcha')->create('flat', true);
    //return Captcha::create();
    return setTResult($code);
});

//Login提交
Route::post('login','Login\LoginController@login')->middleware('jwt');


//Login提交组
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Login','namespace'=>'Login'], function () {
    Route::post('logout','LoginController@logout');
    
});