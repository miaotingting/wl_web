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

//开放接口路由
Route::group(['prefix'=>'OpenAPI','namespace'=>'OpenAPI'], function () {
    #get路由

    #post路由
    Route::post('getSign','OpenAPIController@getSign'); #内部获取签名
    Route::post('packageRenew','OpenAPIController@packageRenew'); #客户接口单卡续费
    

});
