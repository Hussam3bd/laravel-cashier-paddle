<?php

namespace Laravel\Cashier;

use GuzzleHttp\Client;

class Paddle
{
    const API_ENDPOINT = 'https://vendors.paddle.com/api/2.0/';

    const STATUS_PAST_DUE = 'past_due';
    const STATUS_ACTIVE = 'active';
    const STATUS_TRIALING = 'trialing';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'deleted';

    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::API_ENDPOINT,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
        ]);
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public static function subscribedUsers(array $data = [])
    {
        return self::post('subscription/users', $data);
    }

    /**
     * @param $planId
     *
     * @return mixed
     */
    public static function subscribedUsersForPlan($planId)
    {
        return self::subscribedUsers([
            'plan_id' => $planId,
        ]);
    }

    /**
     * @param $subscriptionId
     *
     * @return mixed
     */
    public static function getSubscribedUser($subscriptionId)
    {
        return self::subscribedUsers([
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * @param       $subscriptionId
     * @param array $data
     *
     * @return mixed
     */
    public static function updateUserSubscription($subscriptionId, array $data)
    {
        return self::post('subscription/users/update', array_merge([
            'subscription_id' => $subscriptionId,
        ], $data))->successful();
    }

    /**
     * @param $subscriptionId
     * @param $newPlanId
     *
     * @return mixed
     */
    public static function changeUserPlan($subscriptionId, $newPlanId)
    {
        return self::updateUserSubscription($subscriptionId, [
            'plan_id' => $newPlanId
        ])->successful();
    }

    /**
     * @param $subscriptionId
     *
     * @return mixed
     */
    public static function cancelUserSubscription($subscriptionId)
    {
        return self::post('subscription/users_cancel', [
            'subscription_id' => $subscriptionId,
        ])->successful();
    }

    /**
     * @param             $orderId
     * @param array       $options
     *
     * @return mixed
     */
    public static function refundPayment($orderId, array $options = [])
    {
        return self::post('payment/refund', array_merge([
            'order_id' => $orderId,
        ], $options))->successful();
    }

    /**
     * @param       $url
     * @param array $data
     *
     * @return mixed
     */
    public static function post($url, array $data = [])
    {
        return (new Paddle())->client->post($url, [
            'form_params' => Cashier::paddleOptions($data),
        ]);
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
