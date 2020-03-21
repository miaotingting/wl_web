<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RefundCardTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        dump('测试订单管理');
        $search = [
            // 'status' => '0',
        ];

        // $response = $this->json('put','/api/Order/saleOrder/123', [
        //     'orderNum' => '123',
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get','/api/Order/saleOrder', [
        //     'no' => '123',
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get','/api/Order/refundCards', [
        //     'page' => 1,
        //     'pageSize'=> 10,
        //     'search' => json_encode($search),
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        $response = $this->json('get','/api/Order/refundCardDetails', [
            'page' => 1,
            'pageSize'=> 10,
            'no' => 'TK20191009138196',
            'search' => json_encode($search),
        ]);
        dd($response->json());
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json()['status']);
        $this->assertEquals('Success', $response->json()['msg']);
        
        // $response = $this->json('get','/api/Order/cards', [
        //     'page' => 1,
        //     'pageSize'=> 10,
        //     'orderId' => '123',
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // $response = $this->json('get', '/api/Order/no', []);
        // // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);
        // $orderNo = $response->json()['data'];

        // $search = [
        //     'fullName' => '05'
        // ];
        // $response = $this->json('get', '/api/Customer/getFullNames', [
        //     // 'search' => json_encode($search)
        // ]);
        // dd($response->json());
        // $response->assertStatus(200);
        // $this->assertEquals(0, $response->json()['status']);
        // $this->assertEquals('Success', $response->json()['msg']);

        // // dd($response->json()['data']);
        // $random = random_int(0, count($response->json()['data']) -1 );
        // dump($random);
        // $customer = $response->json()['data'][$random];

        $response = $this->json('post','/api/Order/createRefundCard', [
            'contactsName' => '周涛',
            'contactsMobile'=> '13263463442',
            'operatorType' => '中国联通',
            'industryType' => '车载物联',
            'modelType' => '双切卡',
            'standardType' => '1',
            'describe' => '2G ',
            'silentDate' => '90',
            'realNameType' => '个人实名',
            'flowCardPrice' => '10',
            'smsCardPrice' => '10',
            'voiceCardPrice' => '10',
            'payType' => '预付年付',
            'orderNum' => '1',
            'addressName' => '周涛',
            'addressPhone' => '13263463442',
            'address' => '北京亦庄鹿海园五里',
            'expressArriveDay' => '7',
            'cardType' => '1001',
            // 'customerId' => $customer['id'],
            // 'customerName' => $customer['customerName'],
            // 'orderNo' => $orderNo,
            'isFlow' => '1',
            'flowPackageId' => '1',
            'flowExpiryDate' => '1',
        ]);
        dd($response->json());
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json()['status']);
        $this->assertEquals('Success', $response->json()['msg']);

        
    }
}
