<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Models\API\SmsModel;

class SmsAPITest extends TestCase
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
        $model = new SmsModel();
        $model->sendUpSMS('5dedceb8-1131-54be-9754-cb9d0ff82795', '123', ['1440158399049']);
        
    }
}
