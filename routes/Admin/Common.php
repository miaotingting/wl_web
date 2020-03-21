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

//Admin提交组
Route::group(['middleware' => [],'prefix'=>'Common','namespace'=>'Common'], function () {

    Route::get('getEnums', 'CommonController@getEnums');      #获取类型

    Route::get('downloadTemplate', 'CommonController@downloadTemplete');  //下载模板
});



