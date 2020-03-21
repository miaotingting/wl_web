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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Finance','namespace'=>'Finance'], function () {
    Route::resource('payApply', 'PayApplyController');      #充值申请
    
    #get路由
    Route::get('myWithdraw', 'TSysCustomerWithdrawController@myWithdraw'); #我的提现
    Route::get('applyWithdraw', 'TSysCustomerWithdrawController@applyWithdraw'); #提现申请
    Route::get('withdraw/{id}', 'TSysCustomerWithdrawController@getInfo'); #提现申请
    Route::get('getBalanceAmount/{id}', 'CustomerAccountController@getBalanceAmount'); #提现申请

    #put路由
    Route::put('operatePayApply/{id}','PayApplyController@operatePayApply');
    Route::put('operateWithdraw','TSysCustomerWithdrawController@operateWithdraw');
    Route::put('updateMyWithdraw/{id}','TSysCustomerWithdrawController@updateMyWithdraw');
    
    #post路由
    Route::post('addMyWithdraw', 'TSysCustomerWithdrawController@addMyWithdraw'); #添加提现申请单

    #delete路由
    
});

