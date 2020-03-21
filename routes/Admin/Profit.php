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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Profit','namespace'=>'Profit'], function () {
    #get路由
    Route::get('customerMonthIndex', 'TSysCustomerMonthController@customerMonthIndex'); //客户续费月份列表
    Route::get('customerMonthInfo/{id}', 'TSysCustomerMonthController@customerMonthInfo'); //查看某个客户续费月份
    Route::get('ownProfitReport', 'TCProfitSettlementController@ownProfitReport'); //直销分润明细列表
    Route::get('directProfitIndex', 'TCProfitSettlementController@directProfitIndex'); //直销分润明细列表
    Route::get('agentProfitIndex', 'TCProfitSettlementController@agentProfitIndex'); //代理分润明细列表
    Route::get('myProfit', 'TCProfitController@myProfit'); // 我的分润
    Route::get('profitDetails/{id}', 'TCProfitDetailController@profitDetails'); // 分润明细列表
    Route::get('package', 'TCProfitDetailController@getList'); // 分润明细列表

    Route::get('index', 'TCProfitController@getList'); // 分润设置列表

    Route::get('detail/{no}', 'TCProfitController@detail'); // 查看分润


    #post路由
    Route::post('createCustomerMonth', 'TSysCustomerMonthController@createCustomerMonth'); //客户续费月份设置
    Route::post('updateCustomerMonth/{id}', 'TSysCustomerMonthController@updateCustomerMonth'); //修改客户续费月份

    Route::post('set', 'TCProfitController@createInfo'); //设置分润主表
    Route::post('detail', 'TCProfitDetailController@createInfo'); //增加分润子表
    Route::post('submit', 'TCProfitController@matter'); //提交分润主表 进流程

    #put路由
    Route::put('detail/{id}', 'TCProfitDetailController@updateInfo'); //修改分润子表

    #delete路由
    Route::delete('destoryCustomerMonth/{id}', 'TSysCustomerMonthController@destoryCustomerMonth'); //删除客户续费月份
});

