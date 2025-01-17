<?php

namespace Laravel\Cashier;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

class Cashier
{
    /**
     * The Cashier library version.
     *
     * @var string
     */
    const VERSION = '10.7.1';

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Indicates if Cashier migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Indicates if Cashier routes will be registered.
     *
     * @var bool
     */
    public static $registersRoutes = true;

    /**
     * Indicates if Cashier will mark past due subscriptions as inactive.
     *
     * @var bool
     */
    public static $deactivatePastDue = true;

    /**
     * Get the billable entity instance by Paddle ID.
     *
     * @param string $paddleId
     *
     * @return \Laravel\Cashier\Billable|null
     */
    public static function findBillable($paddleId)
    {
        if ($paddleId === null) {
            return;
        }

        $model = config('cashier.model');

        return (new $model)->where('paddle_id', $paddleId)->first();
    }

    /**
     * Get the default Paddle API options.
     *
     * @param array $options
     *
     * @return array
     */
    public static function paddleOptions(array $options = [])
    {
        return array_merge([
            'vendor_id' => config('cashier.vendor_id'),
            'vendor_auth_code' => config('cashier.vendor_auth_code'),
        ], $options);
    }

    /**
     * @param $plan
     *
     * @return bool|\Illuminate\Config\Repository|int|mixed|string
     */
    public static function getPlanId($plan)
    {
        return is_numeric($plan) ? $plan : config("cashier.plans.{$plan}.id", $plan);
    }

    /**
     * Set the custom currency formatter.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function formatCurrencyUsing(callable $callback)
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param int         $amount
     * @param string|null $currency
     *
     * @return string
     */
    public static function formatAmount($amount, $currency = null)
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency);
        }

        $money = new Money($amount, new Currency(strtoupper($currency ?? config('cashier.currency'))));

        $numberFormatter = new NumberFormatter(config('cashier.currency_locale'), NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    /**
     * Configure Cashier to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }

    /**
     * Configure Cashier to not register its routes.
     *
     * @return static
     */
    public static function ignoreRoutes()
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain past due subscriptions as active.
     *
     * @return static
     */
    public static function keepPastDueSubscriptionsActive()
    {
        static::$deactivatePastDue = false;

        return new static;
    }
}
