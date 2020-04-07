<?php

namespace Laravel\Cashier;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Cashier\Exceptions\InvalidInvoice;
use Laravel\Cashier\Exceptions\InvalidPaddleCustomer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait Billable
{
    /**
     * Refund a customer for a charge.
     *
     * @param       $orderId
     * @param array $options
     *
     * @return \Laravel\Cashier\refundPayment
     */
    public function refund($orderId, array $options = [])
    {
        return Paddle::refundPayment($orderId, $options);
    }

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param string $description
     * @param int    $amount
     * @param array  $options
     *
     * @return \Stripe\InvoiceItem
     */
    public function tab($description, $amount, array $options = [])
    {
        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->paddle_id,
            'amount' => $amount,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        return StripeInvoiceItem::create($options, $this->stripeOptions());
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param string $description
     * @param int    $amount
     * @param array  $tabOptions
     * @param array  $invoiceOptions
     *
     * @return \Laravel\Cashier\Invoice|bool
     *
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function invoiceFor($description, $amount, array $tabOptions = [], array $invoiceOptions = [])
    {
        $this->tab($description, $amount, $tabOptions);

        return $this->invoice($invoiceOptions);
    }

    /**
     * Begin creating a new subscription.
     *
     * @param array  $data
     * @param string $name
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function newSubscription(array $data = [], string $name = 'default')
    {
        $trialEndsAt = null;
        if ($data['status'] === Paddle::STATUS_TRIALING) {
            $time = Carbon::parse($data['event_time'])->format('H:i:s');
            $trialEndsAt = Carbon::parse("{$data['next_bill_date']} {$time}");
        }

        return $this->subscriptions()->create([
            'user_id' => $this->id,
            'name' => $name,
            'paddle_id' => $data['subscription_id'],
            'paddle_plan_id' => $data['subscription_plan_id'],
            'paddle_cancel_url' => $data['cancel_url'],
            'paddle_update_url' => $data['update_url'],
            'paddle_status' => $data['status'],
            'quantity' => $data['quantity'],
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null
        ]);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param string      $subscription
     * @param string|null $plan
     *
     * @return bool
     */
    public function onTrial($subscription = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($subscription);

        if (is_null($plan)) {
            return $subscription && $subscription->onTrial();
        }

        return $subscription && $subscription->onTrial() &&
            $subscription->paddle_plan_id === Cashier::getPlanId($plan);
    }

    /**
     * Determine if the Stripe model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param string      $subscription
     * @param string|null $plan
     *
     * @return bool
     */
    public function subscribed($subscription = 'default', $plan = null)
    {
        $subscription = $this->subscription($subscription);

        if (is_null($subscription)) {
            return false;
        }

        if (is_null($plan)) {
            return $subscription->valid();
        }

        return $subscription->valid() && $subscription->paddle_plan_id === Cashier::getPlanId($plan);
    }

    /**
     * Get a subscription instance by name.
     *
     * @param string $subscription
     *
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($subscription = 'default')
    {
        return $this->subscriptions->sortByDesc(function ($value) {
            return $value->created_at->getTimestamp();
        })->first(function ($value) use ($subscription) {
            return $value->name === $subscription;
        });
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the customer's subscription has an incomplete payment.
     *
     * @param string $subscription
     *
     * @return bool
     */
    public function hasIncompletePayment($subscription = 'default')
    {
        if ($subscription = $this->subscription($subscription)) {
            return $subscription->hasIncompletePayment();
        }

        return false;
    }

    /**
     * Invoice the billable entity outside of the regular billing cycle.
     *
     * @param array $options
     *
     * @return \Laravel\Cashier\Invoice|bool
     *
     * @throws \Laravel\Cashier\Exceptions\PaymentActionRequired
     * @throws \Laravel\Cashier\Exceptions\PaymentFailure
     */
    public function invoice(array $options = [])
    {
        $this->assertCustomerExists();

        $parameters = array_merge($options, ['customer' => $this->paddle_id]);

        try {
            /** @var \Stripe\Invoice $invoice */
            $stripeInvoice = StripeInvoice::create($parameters, $this->stripeOptions());

            $stripeInvoice = $stripeInvoice->pay();

            return new Invoice($this, $stripeInvoice);
        } catch (StripeInvalidRequestException $exception) {
            return false;
        } catch (StripeCardException $exception) {
            $payment = new Payment(
                StripePaymentIntent::retrieve(
                    ['id' => $stripeInvoice->refresh()->payment_intent, 'expand' => ['invoice.subscription']],
                    $this->stripeOptions()
                )
            );

            $payment->validate();
        }
    }

    /**
     * Get the entity's upcoming invoice.
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function upcomingInvoice()
    {
        $this->assertCustomerExists();

        try {
            $stripeInvoice = StripeInvoice::upcoming(['customer' => $this->paddle_id], $this->stripeOptions());

            return new Invoice($this, $stripeInvoice);
        } catch (StripeInvalidRequestException $exception) {
            //
        }
    }

    /**
     * Find an invoice by ID.
     *
     * @param string $id
     *
     * @return \Laravel\Cashier\Invoice|null
     */
    public function findInvoice($id)
    {
        $stripeInvoice = null;

        try {
            $stripeInvoice = StripeInvoice::retrieve(
                $id, $this->stripeOptions()
            );
        } catch (Exception $exception) {
            //
        }

        return $stripeInvoice ? new Invoice($this, $stripeInvoice) : null;
    }

    /**
     * Find an invoice or throw a 404 or 403 error.
     *
     * @param string $id
     *
     * @return \Laravel\Cashier\Invoice
     */
    public function findInvoiceOrFail($id)
    {
        try {
            $invoice = $this->findInvoice($id);
        } catch (InvalidInvoice $exception) {
            throw new AccessDeniedHttpException;
        }

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }

    /**
     * Create an invoice download Response.
     *
     * @param string $id
     * @param array  $data
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data)
    {
        return $this->findInvoiceOrFail($id)->download($data);
    }

    /**
     * Get a collection of the entity's invoices.
     *
     * @param bool  $includePending
     * @param array $parameters
     *
     * @return \Illuminate\Support\Collection
     */
    public function invoices($includePending = false, $parameters = [])
    {
        $this->assertCustomerExists();

        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeInvoices = StripeInvoice::all(
            ['customer' => $this->paddle_id] + $parameters,
            $this->stripeOptions()
        );

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if ( ! is_null($stripeInvoices)) {
            foreach ($stripeInvoices->data as $invoice) {
                if ($invoice->paid || $includePending) {
                    $invoices[] = new Invoice($this, $invoice);
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Get an array of the entity's invoices.
     *
     * @param array $parameters
     *
     * @return \Illuminate\Support\Collection
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Create a new SetupIntent instance.
     *
     * @param array $options
     *
     * @return \Stripe\SetupIntent
     */
    public function createSetupIntent(array $options = [])
    {
        return StripeSetupIntent::create(
            $options, $this->stripeOptions()
        );
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param string $coupon
     *
     * @return void
     */
    public function applyCoupon($coupon)
    {
        $this->assertCustomerExists();

        $customer = $this->asStripeCustomer();

        $customer->coupon = $coupon;

        $customer->save();
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given plans.
     *
     * @param array|string $plans
     * @param string       $subscription
     *
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default')
    {
        $subscription = $this->subscription($subscription);

        if ( ! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array)$plans as $plan) {
            if ($subscription->paddle_plan_id === Cashier::getPlanId($plan)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity is on the given plan.
     *
     * @param string $plan
     *
     * @return bool
     */
    public function onPlan($plan)
    {
        return ! is_null($this->subscriptions->first(function ($value) use ($plan) {
            return $value->paddle_plan_id === Cashier::getPlanId($plan) && $value->valid();
        }));
    }

    /**
     * Determine if the entity has a Paddle customer ID.
     *
     * @return bool
     */
    public function hasPaddleId()
    {
        return ! is_null($this->paddle_id);
    }

    /**
     * Determine if the entity has a Paddle customer ID and throw an exception if not.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function assertCustomerExists()
    {
        if ( ! $this->paddle_id) {
            throw InvalidPaddleCustomer::nonCustomer($this);
        }
    }

    /**
     * Get the email address used to create the customer in Paddle.
     *
     * @return string|null
     */
    public function paddleEmail()
    {
        return $this->email;
    }

    /**
     * Get the Stripe supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return config('cashier.currency');
    }

    /**
     * Get the default Stripe API options for the current Billable model.
     *
     * @param array $options
     *
     * @return array
     */
    public function paddleOptions(array $options = [])
    {
        return Cashier::paddleOptions($options);
    }
}
