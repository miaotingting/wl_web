<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTSysTypeDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_sys_type_detail', function (Blueprint $table) {
            $table->string('id', 32);
            $table->string('code', 20)->default('')->comment('编号');
            $table->string('name', 20)->default('')->comment('名称');
            $table->string('type_id', 32)->default('')->comment('type id');
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_sys_type_detail');
    }
}
