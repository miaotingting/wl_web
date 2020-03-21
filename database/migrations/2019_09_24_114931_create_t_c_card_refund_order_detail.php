<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTCCardRefundOrderDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('c_card_refund_order_detail', function (Blueprint $table) {
            $table->string('id', 32);
            $table->string('no', 20)->default('')->comment('退货单号');
            $table->string('card_no', 20)->default('')->comment('卡号');
            // $table->primary('id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::drop('t_c_card_refund_order_detail');
    }
}
