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

//Card菜单提交
Route::group(['prefix'=>'SmsAPI','namespace'=>'OpenAPI'], function () {
    #resource路由


    #get路由
    Route::get('getSmsStatusAPI','SmsAPIController@getSmsStatus');   #开放API查询短信状态接口
    
    

    
    #post路由
   
    Route::post('smsSendAPI','SmsAPIController@smsSend');   #开放API短信发送接口
    #put路由
    

    #delete路由
    


});
