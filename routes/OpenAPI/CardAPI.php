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
Route::group(['prefix'=>'CardAPI','namespace'=>'OpenAPI'], function () {
    #resource路由


    #get路由
    Route::get('cardInfoAPI','CardAPIController@getCardInfo');   #卡片信息查询
    
    

    
    #post路由
   
    
    #put路由
    

    #delete路由
    


});
