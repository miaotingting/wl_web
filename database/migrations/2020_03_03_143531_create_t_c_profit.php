<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTCProfit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('c_profit', function (Blueprint $table) {
            $table->string('id', 32);
            $table->string('profit_code', 32)->default('')->comment('分润编号');
            $table->string('customer_id', 32)->default('')->comment('客户id');
            $table->string('customer_name', 255)->default('')->comment('客户名称');
            $table->string('profit_type', 20)->default('')->comment('类型');
            $table->string('status', 20)->default('')->comment('状态');
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
        Schema::drop('t_c_profit');
    }
}
