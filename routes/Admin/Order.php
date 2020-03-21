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
Route::group(['middleware' => [],'prefix'=>'Order','namespace'=>'Order'], function () {

    Route::get('saleOrders', 'SaleOrderController@getOrders');      #订单列表

    Route::get('saleOrder', 'SaleOrderController@getOrder');   //查询订单详情

    Route::put('saleOrder/{no}', 'SaleOrderController@updateOrder'); //更新订单

    Route::post('saleOrder', 'SaleOrderController@create');  //创建订单
    
    Route::get('cards', 'SaleOrderController@getCards');   //查询这个订单下面的卡片信息

    Route::get('no', 'SaleOrderController@getOrderNo'); //获取订单号
    
    Route::get('saleOrderExport', 'SaleOrderController@saleOrderExport'); //导出订单列表

    Route::post('createRefundCard', 'RefundOrderController@create');      #创建退卡订单

    Route::get('refundCards', 'RefundOrderController@getOrders');  //退卡订单列表

    Route::get('refundCard', 'RefundOrderController@getOrder'); //退卡订单详情

    Route::get('refundCardDetails', 'RefundOrderController@getCards'); //退卡订单下面的卡片

    Route::post('refundOrderExport', 'RefundOrderController@export'); //导出订单列表
    
    Route::resource('renewOrder', 'TCRenewOrderController');  //续费订单列表
    
    Route::get('renewOrderCards/{id}', 'TCRenewOrderController@renewOrderCards');//续费订单/卡片明细
    
    Route::resource('orderTemplate', 'TCOrderTemplateController');  //资费计划
    
    Route::put('setOrderTemplateStatus/{id}', 'TCOrderTemplateController@setStatus');  //设置资费计划生效/失效
    Route::put('updateName', 'TCOrderTemplateController@updateName'); //更新资费计划的名称
    
    Route::post('createPlanRenew', 'TCRenewOrderController@addPlanRenew');      #套餐续费/升级
});



