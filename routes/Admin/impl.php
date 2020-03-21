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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Impl','namespace'=>'Impl'], function () {
    #resource路由
    Route::resource('implConfig', 'TSysImplController');         #接口管理->接口配置
    Route::resource('implDetailConfig', 'TSysImplDetailController'); #接口管理->接口配置:配置请求参数

    #get路由
    Route::get('implDocument', 'TSysImplController@implDocument'); #接口管理->接口文档:获取客户ID及编码
    Route::get('downloadImplDoc', 'TSysImplController@downloadImplDoc'); #接口管理->下载接口文档
    
    #post路由
    
    
    #put路由
    

    #delete路由
    


});
