<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

class CommonTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        dump('测试公共');
        $search = [
            // 'station' => '11',

        ];
        // Redis::setex('123',60,'123');
        $response = $this->json('get','/api/Common/getEnums', [
            'types' => json_encode(['operate_maintain_poolType']),
        ]);
        dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('post','/api/require_token', [
            
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        $response = $this->json('post','/api/WeChat/WeChatLogout', [
            
        ], [
            'Authorization' =>'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImp0aSI6ImFiYyJ9.eyJpc3MiOiJjbXAud2xpb3QuY29tIiwiYXVkIjoiY21wLndsaW90LmNvbSIsImp0aSI6ImFiYyIsImlhdCI6MTU2NTgzNzczNiwiZXhwIjoxNTY3OTk3NzM2LCJ1aWQiOjMwMDYxfQ.rcvWhZjCz2EOSHXfJIgsuI-AUQHRwgeHFAclWNmjmgo'
        ]);
            dd($response->json());
            $response->assertStatus(200);
            $this->assertEquals(0, $response->json()['status']);
            $this->assertEquals('Success', $response->json()['msg']);
        
    }
}
