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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'WorkOrder','namespace'=>'WorkOrder'], function () {
    
    
    
    #get路由
    Route::get('afterOrder', 'TCWorkOrderController@afterOrderList');#售后服务/售后工单：列表
    Route::get('afterOrderShow', 'TCWorkOrderController@afterOrderShow'); #售后服务/售后工单:工单详情
    Route::get('workOrderPoolList', 'TCWorkOrderController@workOrderPoolList');#售后服务/工单池：列表
    Route::get('workOrderManageList', 'TCWorkOrderController@workOrderManageList');#售后服务/工单管理：列表
    Route::get('getAfterSaleRoleUser', 'TCWorkOrderController@getAfterSaleRoleUser');#售后服务：售后角色的用户列表
    Route::get('getUserOperationType', 'TCWorkOrderController@getUserOperationType');#售后服务/工单管理：交接中操作时获取是交接人还是被交接人
    
    #put路由
    Route::put('workOrderPoolClaim', 'TCWorkOrderController@workOrderPoolClaim');#售后服务/工单池：认领工单
    Route::put('workOrderPoolSingleAllot', 'TCWorkOrderController@workOrderPoolSingleAllot');#售后服务/工单池：分配工单
    Route::put('workOrderPoolRandomAllot', 'TCWorkOrderController@workOrderPoolRandomAllot');#售后服务/工单池：随机分配工单
    Route::put('handOverWorkOrder', 'TCWorkOrderController@handOverWorkOrder');#售后服务/工单管理：交接
    Route::put('cancelHandOver', 'TCWorkOrderController@cancelHandOver');#售后服务/工单管理：撤销交接
    Route::put('operationHandOver', 'TCWorkOrderController@operationHandOver');#售后服务/工单管理：同意/驳回交接
    Route::put('closeWorkOrder', 'TCWorkOrderController@closeWorkOrder');#售后服务  ：关闭工单
   
    
    #post路由
    Route::post('addAfterOrder', 'TCWorkOrderController@addAfterOrder');      #售后服务/售后工单:新建工单
    Route::post('addWorkOrderHandleInfo', 'TCWorkOrderController@addWorkOrderHandleInfo');      #售后服务:新建工交流内容

    #delete路由
    Route::delete('deleteWorkOrder/{id}', 'TCWorkOrderController@deleteWorkOrder');#售后服务  ：删除工单
    
});

