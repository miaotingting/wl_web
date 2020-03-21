<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTCCardRefundOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('c_card_refund_order', function (Blueprint $table) {
            $table->string('id', 32);
            $table->string('no', 20)->default('')->comment('退货单号');
            $table->string('order_no', 20)->default('')->comment('开卡单号');
            $table->integer('count', 11)->comment('退货数量');
            $table->decimal('amount', 11,2)->comment('退货金额');
            $table->string('desc', 255)->default('')->comment('退货原因');
            $table->dateTime('create_time')->comment('退货时间');
            $table->dateTime('in_store_time')->comment('入库时间');
            $table->enum('status', ['start','checking', 'wait_refund', 'wait_in_store', 'end', 'reject', 'delete'])->default('start')->comment('退货状态 start:待审核，checking:审核中,wait_refund:待退款，wait_in_store:待入库，end:订单完成,reject:驳回，delete:作废');
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
        Schema::drop('t_c_card_refund_order');
    }
}
