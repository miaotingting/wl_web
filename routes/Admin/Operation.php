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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Operation','namespace'=>'Operation'], function () {
    Route::resource('package', 'PackageController');      #套餐管理
    Route::resource('warehouse', 'TCWarehouseController');      #仓库管理
    Route::resource('warehouseOrder', 'TCWarehouseOrderController');      #入库管理
    
    

    #get路由
    Route::get('exportTemplate','TCOperateMaintainController@exportTemplate');#运营管理/运营维护：批量导卡excel模板
    Route::get('getMaintainCards','TCOperateMaintainController@getMaintainCards');#运营管理/运营维护：维护单卡片详情
    Route::get('maintainOrderShow','TCOperateMaintainController@maintainOrderShow');#运营管理/运营维护：查看维护订单
    Route::get('index','TCOperateMaintainController@index');   #运营管理/运营维护列表
    Route::get('storeOutList','StoreOutController@storeOutList');   #出库管理列表
    Route::get('storeOutShow/{storeOutId}','StoreOutController@storeOutShow');   #出库订单查看
    Route::get('outCardsInfo','StoreOutController@outCardsInfo');   #出库订单卡片详情
    Route::get('orderCardExportExcel','StoreOutController@orderCardExportExcel');   #订单卡片列表导出
    Route::get('maintainOrderCardsExportExcel','TCOperateMaintainController@maintainOrderCardsExportExcel');#运营维护订单卡片列表导出
    Route::get('warehouseTemplate','TCWarehouseOrderDetailController@exportTemplate');#入库订单:导入excel模板
    Route::get('wareOrderCards','TCWarehouseOrderDetailController@getWareOrderCards');#入库订单：（操作）卡片详情
    Route::get('warehouseCards','TCWarehouseOrderDetailController@getWarehouseCards');#库存卡片
    Route::get('inventoryCards','TCWarehouseController@inventoryCards');#仓库管理:库存详情
    Route::get('wareCardExport','TCWarehouseOrderDetailController@wareCardExport');#入库订单卡片详情导出
    
    
    
    #put路由
    Route::put('dataInit','TCWarehouseOrderDetailController@dataInit');#入库订单:数据初始化
    Route::put('operationCheck','TCWarehouseOrderController@operationCheck');#入库订单:运营审核

    #post路由
    Route::post('importCards','TCOperateMaintainController@importCards');    #运营管理/运营维护：卡片维护
    Route::post('checkOutOrder/{storeOutId}','StoreOutController@checkOutOrder');   #审核出库订单

    Route::post('importWarehouseCards', 'TCWarehouseOrderDetailController@importWarehouseCards');#入库管理->导入仓库卡片
    
    
    Route::post('resetMaintainData','TCOperateMaintainController@resetMaintainData');    #运营管理/运营维护：数据初始化
    Route::post('updateMaintainOrder','TCOperateMaintainController@updateMaintainOrder');    #运营管理/运营维护：修改维护单

    #delete路由
    
});

