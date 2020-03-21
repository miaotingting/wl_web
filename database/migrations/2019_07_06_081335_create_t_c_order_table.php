<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTCOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('c_order', function (Blueprint $table) {
            $table->string('id', 32);
            $table->string('order_no', 20)->default('')->comment('订单号');
            $table->string('customer_id', 32)->default('')->comment('采购客户id');
            $table->string('customer_name', 100)->default('')->comment('采购客户名称');
            $table->string('contacts_name', 20)->default('')->comment('联系人');
            $table->string('contacts_mobile', 15)->default('')->comment('联系电话');
            $table->string('operator_type', 10)->default('')->comment('运营商类型');
            $table->string('industry_type', 10)->default('')->comment('行业用途');
            $table->string('card_type', 50)->default('')->comment('卡类型');
            $table->string('standard_type', 10)->default('')->comment('通讯制式');
            $table->string('model_type', 10)->default('')->comment('卡型号');
            $table->tinyInteger('is_flow')->default(0)->comment('运营商类型');
            $table->tinyInteger('is_sms')->default(0)->comment('运营商类型');
            $table->tinyInteger('is_voice')->default(0)->comment('运营商类型');
            $table->string('flow_package_id', 32)->default('')->comment('流量套餐');
            $table->string('sms_package_id', 32)->default('')->comment('短信套餐');
            $table->string('voice_package_id', 32)->default('')->comment('语音套餐');
            $table->integer('flow_expiry_date')->default(0)->comment('开通时效');
            $table->string('expiry_type', 32)->default('')->comment('时效单位');
            $table->string('real_name_type', 32)->default('')->comment('是否实名');
            $table->string('test_type', 32)->default('')->comment('开通测试类型');
            $table->integer('silent_date')->default(0)->comment('沉默期(天)');
            $table->decimal('credit_amount', 20, 2)->default(0)->comment('信用额度');
            $table->string('address_name', 20)->default('')->comment('收件人');
            $table->string('address_phone', 20)->default('')->comment('收件电话');
            $table->string('pay_type', 32)->default('')->comment('付款方式');
            $table->string('address', 500)->default('')->comment('收件地址');
            $table->string('express', 50)->default('')->comment('快递公司');
            $table->string('express_num', 50)->default('')->comment('快递单号');
            $table->decimal('express_amount', 20, 2)->default(0)->comment('快递费用');
            $table->integer('order_num')->default(0)->comment('采购数量');
            $table->decimal('amount', 20, 2)->default(0)->comment('订单总金额');
            $table->integer('status')->default(0)->comment('状态 0:草稿 1:审核中 2:审核通过 3:审核通过 -1::删除');
            $table->dateTime('create_time')->comment('创建时间');
            $table->dateTime('update_time')->comment('更新时间');
            $table->string('describe', 1000)->default('')->comment('描述');
            $table->string('template_id', 32)->default('')->comment('模板id');
            $table->tinyInteger('is_out_flow')->default(0)->comment('超出流量操作类型 0停机 1启用信用额度');
            $table->tinyInteger('is_test')->default(0)->comment('是否测试');
            $table->string('fees_type', 32)->default('')->comment('计费类型  1001：开卡   1002：续费  1003:升级套餐');
            $table->integer('sms_expiry_date')->default(0)->comment('短信过期时间');
            $table->integer('voice_expiry_date')->default(0)->comment('语音过期时间');
            $table->tinyInteger('is_open_card')->default(0)->comment('是否开卡');
            $table->integer('express_arrive_day')->default(0)->comment('快递采购时限');
            $table->decimal('flow_card_price', 20, 2)->default(0)->comment('单卡流量价格');
            $table->decimal('sms_card_price', 20, 2)->default(0)->comment('单卡短信价格');
            $table->decimal('renew_flow_price', 20, 2)->default(0)->comment('流量续费价格');
            $table->decimal('renew_sms_price', 20, 2)->default(0)->comment('短信续费价格');
            $table->tinyInteger('is_special')->default(0)->comment('是否特殊卡 0否 1是');
            $table->string('special_requirements', 1000)->default('')->comment('特殊要求');
            $table->tinyInteger('is_pool')->default(0)->comment('是否流量池');
            $table->tinyInteger('is_overflow_stop')->default(0)->comment('是否超流量停机');
            $table->integer('payment_method')->default(0)->comment('付款方式 0：账户余额抵扣    1：微信支付');
            $table->integer('effect_type')->default(0)->comment('续费套餐生效类型   0：次月生效   1：服务期止后生效');
            $table->tinyInteger('is_imsi')->default(0)->comment('是否支持IMSI 0 不支持 1 支持');
            $table->integer('resubmit')->default(0)->comment('是否可重提');
            $table->string('create_user_id', 32)->default('')->comment('创建者ID');
            $table->tinyInteger('is_test_date')->default(0)->comment('是否开通测试期 0:否  1:是');
            $table->integer('test_flow')->default(0)->comment('测试期总流量');
            $table->dateTime('cust_test_date')->comment('客户测试期');
            $table->dateTime('oper_test_date')->comment('运营商测试期');
            $table->decimal('voice_card_price', 20, 2)->default(0)->comment('单卡语音价格');
            $table->integer('package_type')->default(0)->comment('套餐类型');
            $table->string('assign_user_id', 32)->default('')->comment('指派人id');
            $table->decimal('order_cost', 20, 2)->default(0)->comment('订单成本');
            $table->decimal('gross', 20, 2)->default(0)->comment('预计毛利');
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
        Schema::dropIfExists('t_c_order');
    }
}
