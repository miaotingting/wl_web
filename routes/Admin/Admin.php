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
Route::group(['middleware' => ['jwt','login'],'prefix'=>'Admin','namespace'=>'Admin'], function () {
    Route::resource('depart', 'DepartController');      #部门管理
    Route::resource('menu','MenuController');           #菜单管理
    Route::resource('role','RoleController');           #角色管理
    Route::resource('roleUser','RoleUserController');   #角色用户
    Route::resource('user','UserController');           #用户管理
    Route::resource('gateway','GatewayController');     #网关管理
    Route::resource('rates','StandardRatesController'); #资费管理
    Route::resource('station','StationController');     #落地管理
    Route::resource('notice','NoticeController');     #系统消息
    Route::resource('index','IndexController');     #系统消息
    
    #get路由
    Route::get('users','UserController@getUsers');           #用户列表
    Route::get('getMenuAuth','RoleController@getMenuAuth');  #权限查看
    Route::get('roles','RoleController@getRoles');           #角色列表
    Route::get('showUser/{id}','RoleController@showUser');   #某个角色下的用户列表
    Route::get('gateways','GatewayController@getGateways');  #网关列表
    Route::get('WFList','WorkFlowController@WFList');        #流程定义列表
    Route::get('notices/getImpowerList/{id}','NoticeController@getImpowerList');    #系统消息授权列表
    Route::get('notices/getAffiche','NoticeController@getAffiche');    #公告列表
    Route::get('notices/getMessage','NoticeController@getMessage');    #消息列表
    Route::get('notices/readNotice/{id}','NoticeController@readNotice');    #阅读公告
    Route::get('notices/getUnread','NoticeController@getUnread');    #获取未读的公告及通知个数
    Route::get('companys','TSysCompanyController@getSysCompany');#采购主体列表

    #put路由
    Route::put('users/rePwd','UserController@rePwd');   #重置密码
    Route::put('users/lockUser','UserController@lockUser');   #激活锁定用户
    Route::put('users/updatePwd','UserController@updateUserPwd');   #修改用户密码

    #post路由
    Route::post('setMenuAuth','RoleController@setMenuAuth'); #设置角色权限
    Route::post('setWFDefine','WorkFlowController@setWFDefine');    #流程定义列表
    Route::post('setNodes','WorkFlowController@setNodes');    #设置节点
    Route::post('notices/impowerNotice','NoticeController@impowerNotice');    #系统消息授权
    Route::post('notices/deleteImpower','NoticeController@deleteImpower');   #系统公告->删除授权

    #delete路由
    Route::delete('delRoleUser/{id}','RoleController@delUser');   #把用户从某角色中删除
    
    
});

