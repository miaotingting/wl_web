<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TypeTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        dump('测试字典');
        $search = [
            'code' => 'operate_maintain_poolType',

        ];
        // $response = $this->json('post','/api/Type/type', [
        //     'name' => '卡型号',
        //     'code' => 'model_type',
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('post','/api/Type/detail', [
        //     'name' => '陶瓷卡大卡',
        //     'code' => 'ceramic_large_card',
        //     'type' => 'c85a9dff7aeb5211b595529c8205af98',
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        $response = $this->json('get','/api/Type/types', [
            'page' => '1',
            'pageSize' => '10',
            'search' => json_encode($search),
        ]);
        dd($response->json());
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json()['status']);
        $this->assertEquals('Success', $response->json()['msg']);
        
    }
}
