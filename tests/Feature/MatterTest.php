<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MatterTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        dump('测试事项管理');

        // $response = $this->json('get','/api/Matter/taskNames', [
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get','/api/Matter/ends', [
        //     'page' => 1,
        //     'pageSize'=> 10,
        // ],[
        //     'Authorization' =>'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImp0aSI6ImFiYyJ9.eyJpc3MiOiJjbXAud2xpb3QuY29tIiwiYXVkIjoiY21wLndsaW90LmNvbSIsImp0aSI6ImFiYyIsImlhdCI6MTU2NTgzNzczNiwiZXhwIjoxNTY3OTk3NzM2LCJ1aWQiOjMwMDYxfQ.rcvWhZjCz2EOSHXfJIgsuI-AUQHRwgeHFAclWNmjmgo'
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get','/api/Matter/alreadys', [
        //     'page' => 1,
        //     'pageSize'=> 10,
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get','/api/Matter/myCreateds', [
        //     'page' => 1,
        //     'pageSize'=> 10,
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get','/api/Matter/backlogs', [
        //     'page' => 1,
        //     'pageSize'=> 10,
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get','/api/Matter/threads', [
        //     'page' => 1,
        //     'pageSize'=> 10,
        //     'businessOrder' => 'TK20191009138196'
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('post','/api/Matter/agree', [
        //     'processId' => '496d2c905eef5b5e8b27af959ffbf917',
        //     'desc'=> '123',
        // ], [
        //     'Authorization' =>'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImp0aSI6ImFiYyJ9.eyJpc3MiOiJjbXAud2xpb3QuY29tIiwiYXVkIjoiY21wLndsaW90LmNvbSIsImp0aSI6ImFiYyIsImlhdCI6MTU3MDY5NTgyOSwiZXhwIjoxNTcwNzMxODI5LCJ1aWQiOjMwMDYxfQ.FBOtFhvVLQ2iwi57_dBroKWpPKzjSlx4Vrl1KSHV4Ak'
        // ]);
        // dump($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        $response = $this->json('post','/api/Matter/delete', [
            'processId' => '8aa726237c7d5d1aa0e44e6b975b47d1',
            'desc'=> '123',
        ], [
            'Authorization' =>'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImp0aSI6ImFiYyJ9.eyJpc3MiOiJjbXAud2xpb3QuY29tIiwiYXVkIjoiY21wLndsaW90LmNvbSIsImp0aSI6ImFiYyIsImlhdCI6MTU3MDY5NTgyOSwiZXhwIjoxNTcwNzMxODI5LCJ1aWQiOjMwMDYxfQ.FBOtFhvVLQ2iwi57_dBroKWpPKzjSlx4Vrl1KSHV4Ak'
        ]);
        dd($response->json());
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json()['status']);
        $this->assertEquals('Success', $response->json()['msg']);
    }
}
