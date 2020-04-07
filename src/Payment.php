<?php

namespace Laravel\Cashier;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'subscription_id',
        'user_id',
        'paddle_order_id',
        'paddle_receipt_url',
        'paddle_status',
        'name',
        'payment_method',
        'coupon',
        'country_code',
        'currency_code',
        'total_price',
        'paddle_fee',
        'tax',
        'earnings',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
