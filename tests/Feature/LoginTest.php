<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Models\API\SmsModel;

class LoginTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        dump('测试登陆');
        $search = [
            // 'station' => '11',

        ];
        // $response = $this->json('post','/api/require_token');
        // dd($response->json());
        $response = $this->json('post','/api/login', [
            'userName' => 'zhoutao',
            'userPwd' => '123456',
            'captcha' => 'nhrt',
            'cKey' => '$2y$10$Ado0AvK2k2UNaXX4kWPY6.0CIQBcch7To48Ez8M6Jo578IfS54vxm'
        ], [
            'Authorization' =>'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImp0aSI6ImFiYyJ9.eyJpc3MiOiJjbXAud2xpb3QuY29tIiwiYXVkIjoiY21wLndsaW90LmNvbSIsImp0aSI6ImFiYyIsImlhdCI6MTU3MDY5NTgyOSwiZXhwIjoxNTcwNzMxODI5LCJ1aWQiOjMwMDYxfQ.FBOtFhvVLQ2iwi57_dBroKWpPKzjSlx4Vrl1KSHV4Ak'
        ]);
        
        dd($response->json());
        $response->assertStatus(200);
    }
}
