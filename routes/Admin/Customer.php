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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Customer','namespace'=>'Customer'], function () {
    Route::resource('info', 'CustomerController');      #客户档案
    Route::resource('contact', 'CustomerContactController');   #客户联系人管理
    Route::resource('users', 'CustomerUserController');   #客户管理下的用户管理
    #get路由
    Route::get('getFullNames', 'CustomerController@getCustomers'); //获取客户列表名称
    Route::get('customerTree','CustomerController@customerTree');   #一级客户树形结构
    Route::get('subCustomerTree','CustomerController@subCustomerTree');   #一级客户子客户树形结构
    Route::get('childCustomer','CustomerController@getOneChild');   #得到下级客户的客户名称
    Route::get('getCity', 'CustomerController@getCity');   #获取城市列表
    Route::get('getParentInfo', 'CustomerController@getParentInfo');   #获取城市列表
    Route::get('getFirstCustomer', 'CustomerController@getFirstCustomer');   #获取一级客户列表

    #put路由
    Route::put('changeManager/{id}', 'CustomerController@changeManager');#客户档案->变更经理
    Route::put('setMainContact', 'CustomerContactController@setMain');#客户档案->设置主要联系人
    Route::put('setManager', 'CustomerController@setManager');#客户档案->设置经理
    
    #post路由


    #delete路由
    
});

