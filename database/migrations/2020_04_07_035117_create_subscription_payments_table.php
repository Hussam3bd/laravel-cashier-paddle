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
            $table->unsignedBigInteger('subscription_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('paddle_order_id');
            $table->string('paddle_receipt_url');
            $table->string('paddle_status')->index()->comment('active, trialing, past_due, deleted');
            $table->string('name')->nullable();
            $table->string('payment_method')->index();
            $table->string('coupon')->nullable();
            $table->string('country', 4)->nullable();
            $table->string('currency', 3);
            $table->decimal('total_price');
            $table->decimal('paddle_fee');
            $table->decimal('tax');
            $table->decimal('earnings');

            $table->timestamp('processed_at');
            $table->timestamps();
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
