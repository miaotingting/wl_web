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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Card','namespace'=>'Card'], function () {
    #resource路由


    #get路由
    
    Route::get('cardInfo/{id}', 'CardController@getCardInfo');      #卡片管理/查看某个卡片详细信息
    Route::get('getUsedHistory/{id}', 'CardController@getUsedHistory');      #卡片管理/查看某个卡片用量历史
    Route::get('getUsedHistory/{id}', 'CardController@getUsedHistory');      #卡片管理/查看某个卡片用量历史
    Route::get('expireCard', 'CardController@getExpireCard');      #本月到期卡片
    Route::get('expireCardExcel', 'CardController@expireCardExcel');      #导出本月到期卡片
    Route::get('restartList', 'CardController@restartList');      #卡片管理/停复机管理
    Route::get('restartCardList', 'CardController@restartCardList');      #卡片管理/停复机管理:卡片详情
    Route::get('restartCardExcel', 'CardController@restartCardExcel');      #卡片管理/停复机管理:导出卡片详情
    
    
    #post路由
    Route::post('openCard', 'CardController@openCard');      #卡片管理/客户卡片：批量开卡
    Route::post('recycleCard', 'CardController@recycleCard');      #卡片管理/客户卡片：批量回收
    Route::post('addRestartCard', 'CardController@addRestartCard');      #卡片管理/停复机管理:新建申请
    Route::post('myCard', 'CardController@myCard');      #我的卡片
    Route::post('customerCard', 'CardController@customerCard');      #客户卡片
    Route::post('MyCardExport', 'CardController@MyCardExport');      #卡片管理/我的卡片:导出
    Route::post('customerCardExport', 'CardController@customerCardExport'); #卡片管理/客户卡片:导出
    
    
    
    #put路由
    Route::put('updateMachineStatus/{id}', 'CardController@updateMachineStatus');      #卡片管理：更新卡片的活动状态

    #delete路由
    


});

