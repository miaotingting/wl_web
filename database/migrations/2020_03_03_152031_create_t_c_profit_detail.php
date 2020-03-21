<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTCProfitDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('c_profit_detail', function (Blueprint $table) {
            $table->string('id', 32);
            $table->string('profit_id', 32)->default('')->comment('分润表id');
            $table->string('package_id', 32)->default('')->comment('套餐id');
            $table->string('package_name', 32)->default('')->comment('套餐名称');
            $table->tinyInteger('is_sale')->default(0)->comment('是否在售');
            $table->decimal('sale_price', 20, 2)->default(0)->comment('销售价');
            $table->decimal('cost_price', 20, 2)->default(0)->comment('成本价');
            $table->decimal('profit_price', 20, 2)->default(0)->comment('分润额');
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
        Schema::drop('t_c_profit_detail');
    }
}
