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

//Sms菜单提交
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Sms','namespace'=>'Sms'], function () {
    #resource路由
    Route::resource('smsCommand', 'SmsCommandController');         #短信管理->指令模板
    Route::resource('smsSendLog', 'SmsSendLogController');         #短信管理->短信发送日志
    Route::resource('smsReceiveLog', 'SmsReceiveLogController');   #短信管理->短信接收日志
    Route::resource('smsSend', 'SmsSendingController');               #短信管理->短信发送

    #get路由
    

    
    #post路由
    
    
    #put路由
    

    #delete路由
    


});
