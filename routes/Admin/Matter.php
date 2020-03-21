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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Matter','namespace'=>'Matter'], function () {

    Route::get('backlogs', 'MatterController@getBacklogs');      #待办事项

    Route::get('alreadys', 'MatterController@getAlreadyMatter');      #已办事项

    Route::get('myCreateds', 'MatterController@getCreatedMatter');      #我的请求

    Route::get('ends', 'MatterController@getEnds');      #办结事项

    Route::get('threads', 'MatterController@getThreads');  #查询进程下面的线程

    Route::post('agree', 'MatterController@agree');  #同意操作

    Route::post('reject', 'MatterController@reject');  #驳回操作

    Route::post('delete', 'MatterController@delete');  #作废操作

    Route::get('taskNames', 'MatterController@getTaskNames'); #查询所有
    
});



