<?php

use Illuminate\Http\Request;
// require __DIR__."/Admin/Admin.php";
// require __DIR__."/Login/Login.php";
// require __DIR__."/Admin/Customer.php";
// require __DIR__."/Admin/Operation.php";
// require __DIR__."/Admin/Matter.php";
// require __DIR__."/Admin/Order.php";
// require __DIR__."/Admin/Finance.php";
// require __DIR__."/Admin/Card.php";
// require __DIR__."/Admin/Common.php";
// require __DIR__."/Admin/Type.php";
// require __DIR__."/Admin/Sms.php";
// require __DIR__."/OpenAPI/CardAPI.php";
// require __DIR__."/OpenAPI/SmsAPI.php";
// require __DIR__."/Admin/ReportForm.php";
// require __DIR__."/WeChat/WeChat.php";
// require __DIR__."/OpenAPI/TestAPI.php";
// require __DIR__."/Admin/WorkOrder.php";

// requireRoutes(__DIR__);
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

Route::post('/uuid', function() {
    //生成uuid，传入一个用户名，可选参数
    return getUuid('123456');
});

//获取jwt token
Route::post('/require_token', 'JWT\RequireTokenController@requireToken');
