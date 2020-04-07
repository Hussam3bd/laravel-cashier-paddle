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
        'country',
        'currency',
        'subtotal',
        'tax',
        'fee',
        'total',
        'earnings',
        'processed_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'processed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
