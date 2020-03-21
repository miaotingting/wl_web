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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Type','namespace'=>'Admin'], function () {

    Route::get('types', 'TypeController@getTypes');      //类型列表

    Route::post('type', 'TypeController@create');      //创建类型

    Route::delete('type/{id}', 'TypeController@delete');   //删除类型

    Route::put('type/{id}', 'TypeController@update'); //更新类型

    Route::get('details', 'TypeController@getDetails');      //类型详细列表

    Route::post('detail', 'TypeController@createDetail');      //创建类型详细

    Route::delete('detail/{id}', 'TypeController@deleteDetail');   //删除类型详细

    Route::put('detail/{id}', 'TypeController@updateDetail'); //更新类型详细
});



