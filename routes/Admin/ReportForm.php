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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'ReportForm','namespace'=>'ReportForm'], function () {
    #resource路由

    #get路由
    Route::get('monthUsedReport', 'MonthUsedReportController@getMonthUsed');  #报表管理->月用量报表
    Route::get('monthUsedExcel', 'MonthUsedReportController@monthUsedExcel');  # 月用量报表 -> 导出
    
    #post路由
    
    
    #put路由
    

    #delete路由
    


});
