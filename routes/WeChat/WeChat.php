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

//微信登录单独只走jwt中间件(/api/WeChatLogin)
Route::post('WeChatLogin','WeChat\WeChatLoginController@WeChatLogin')->middleware('jwt');

Route::group(['prefix'=>'WeChat','namespace'=>'WeChat'], function () {
    Route::get('getCardInfo/{id}','WeChatCardController@getCardInfo');   // 卡片信息
    Route::get('getCardPayInfo/{id}','WeChatCardController@getCardPayInfo');   //充值前显示的卡片信息
    Route::post('payment/jssdk','WeChatPaymentController@renewPayment');   //续费，微信支付
    Route::post('payment/callback', 'WeChatPaymentController@paymentCallback'); //续费，微信回调
    Route::post('openid', 'WeChatPaymentController@getOpenid'); //获取openid
    Route::post('payment/delete', 'WeChatPaymentController@deleteOrder'); //删除订单

});
//微信公众号路由(走jwt和login中间件)
Route::group(['middleware' => ['jwt','login'],'prefix'=>'WeChat','namespace'=>'WeChat'], function () {
    Route::post('WeChatLogout','WeChatLoginController@WeChatLogout');   // 退出登录
    
    
    #get路由
    Route::get('cardList','WeChatCardController@cardList');   // 短信日志
    Route::get('mobileSmsList','WeChatSmsController@mobileSmsList');   // 短信详情
    



    

});


