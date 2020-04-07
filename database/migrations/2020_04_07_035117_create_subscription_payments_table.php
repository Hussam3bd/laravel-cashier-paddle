<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('user_id');
            $table->string('paddle_order_id');
            $table->string('paddle_receipt_url');
            $table->string('paddle_status')->comment('active, trialing, past_due, deleted');
            $table->string('name')->nullable();
            $table->string('payment_method');
            $table->string('coupon')->nullable();
            $table->string('country_code', 4)->nullable();
            $table->string('currency_code', 4);
            $table->decimal('total_price');
            $table->decimal('paddle_fee');
            $table->decimal('tax');
            $table->decimal('earnings');
            $table->timestamps();

            $table->index(['user_id', 'subscription_id', 'paddle_status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_payments');
    }
}
