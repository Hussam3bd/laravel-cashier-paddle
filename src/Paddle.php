<?php

namespace Laravel\Cashier;

use Illuminate\Support\Facades\Http;

class Paddle
{
    const API_ENDPOINT = 'https://vendors.paddle.com/api/2.0/';

    const STATUS_PAST_DUE = 'past_due';
    const STATUS_ACTIVE = 'active';
    const STATUS_TRIALING = 'trialing';
    const STATUS_CANCELLED = 'deleted';

    protected $vendorId = '';
    protected $vendorAuthCode = '';
    protected $client;

    public function __construct()
    {
        $this->vendorId = config('cashier.vendor_id');
        $this->vendorAuthCode = config('cashier.vendor_auth_code');
    }

    /**
     * @param       $subscriptionId
     * @param array $data
     *
     * @return mixed
     */
    public function updateUserSubscription($subscriptionId, array $data)
    {
        return $this->send('subscription/users/update', array_merge([
            'subscription_id' => $subscriptionId,
        ], $data))->successful();
    }

    /**
     * @param $subscriptionId
     * @param $newPlanId
     *
     * @return mixed
     */
    public function changeUserPlan($subscriptionId, $newPlanId)
    {
        return $this->updateUserSubscription($subscriptionId, [
            'plan_id' => $newPlanId
        ])->successful();
    }

    /**
     * @param $subscriptionId
     *
     * @return mixed
     */
    public function cancelUserSubscription($subscriptionId)
    {
        return $this->send('subscription/users_cancel', [
            'subscription_id' => $subscriptionId,
        ])->successful();
    }

    /**
     * @param       $url
     * @param array $data
     *
     * @return mixed
     */
    public function send($url, array $data = [])
    {
        $data = array_merge($this->getPaddleAuthenticationParameters(), $data);

        $response = Http::withHeaders([
            'Content-Type' => 'multipart/form-data'
        ])->post($url, $data);

        return $response;
    }

    /**
     * @return array
     */
    private function getPaddleAuthenticationParameters()
    {
        return [
            'vendor_id' => $this->vendorId,
            'vendor_auth_code' => $this->vendorAuthCode,
        ];
    }
}