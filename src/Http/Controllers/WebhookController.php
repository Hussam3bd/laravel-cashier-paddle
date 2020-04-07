<?php

namespace Laravel\Cashier\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a Stripe webhook call.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        $method = 'handle' . Str::studly($payload['alert_name']);

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            $payload['passthrough'] = json_decode($payload['passthrough'], true);

            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->missingMethod();
    }

    /**
     * Handle customer subscription created.
     *
     * @param array $payload
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleSubscriptionCreated(array $payload)
    {
        if (count($payload['passthrough']) && isset($payload['passthrough']['user_id'])) {
            $user = config('cashier.model');
            if ($user = (new $user)->find($payload['passthrough']['user_id'])) {
                /** @var User $user */

                # update customer
                $user->paddle_id = $payload['user_id'];
                $user->save();

                # create subscriptions
                $user->newSubscription($payload);
            }
        }

        return $this->successMethod();
    }

    /**
     * @param array $payload
     *
     * @return Response
     */
    public function handleSubscriptionCancelled(array $payload)
    {
        if ($user = $this->getUserByPaddleId($payload['user_id'])) {
            $cancellationEffectiveAt = null;
            if ($payload['cancellation_effective_date']) {
                $time = Carbon::parse($payload['event_time'])->format('H:i:s');
                $cancellationEffectiveAt = Carbon::parse("{$payload['cancellation_effective_date']} {$time}");
            }

            $user->subscription($payload['subscription_id'])->cancel($cancellationEffectiveAt);
        }

        return $this->successMethod();
    }

    /**
     * Handle customer subscription updated.
     *
     * @param array $payload
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionUpdated(array $payload)
    {
        if ($user = $this->getUserByPaddleId($payload['user_id'])) {
            $user->subscriptions->filter(function (Subscription $subscription) use ($payload) {
                return $subscription->paddle_id === $payload['old_subscription_id'];
            })->each(function (Subscription $subscription) use ($payload) {
                // Subscription...
                if (isset($payload['subscription_id'])) {
                    $subscription->paddle_id = $payload['subscription_id'];
                }

                // Plan...
                if (isset($payload['subscription_plan_id'])) {
                    $subscription->paddle_plan_id = $payload['subscription_plan_id'];
                }

                // Quantity...
                if (isset($payload['quantity'])) {
                    $subscription->quantity = $payload['quantity'];
                }

                // Cancel url...
                if (isset($payload['cancel_url'])) {
                    $subscription->paddle_cancel_url = $payload['cancel_url'];
                }

                // Update url...
                if (isset($payload['update_url'])) {
                    $subscription->paddle_update_url = $payload['update_url'];
                }

                // Paused at date...
                if (isset($payload['paused_at'])) {
                }

                // Status...
                if (isset($payload['status'])) {
                    $subscription->paddle_status = $payload['status'];
                }

                $subscription->save();
            });
        }

        return $this->successMethod();
    }

    /**
     * Handle subscription payment succeeded.
     *
     * @param array $payload
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionPaymentSucceeded(array $payload)
    {
        if ($user = $this->getUserByPaddleId($payload['user_id'])) {
            if ($subscription = $user->subscription($payload['subscription_id'])) {
                $subscription->payments()
                             ->create([
                                 'paddle_order_id' => $payload['order_id'],
                                 'paddle_receipt_url' => $payload['receipt_url'],
                                 'paddle_status' => $payload['status'],
                                 'name' => $payload['name'],
                                 'payment_method' => $payload['coupon'],
                                 'coupon' => $payload['coupon'],
                                 'country' => $payload['country'],
                                 'currency' => $payload['currency'],
                                 'subtotal' => $payload['unit_price'],
                                 'tax' => $payload['balance_tax'],
                                 'fee' => $payload['balance_fee'],
                                 'total' => $payload['balance_earnings'],
                                 'processed_at' => $payload['event_time'],
                             ]);
            }
        }

        return $this->successMethod();
    }

    /**
     * @param array $payload
     *
     * @return Response
     */
    protected function handleSubscriptionPaymentFailed(array $payload)
    {
        return $this->successMethod();
    }

    /**
     * Get the billable entity instance by Paddle ID.
     *
     * @param string|null $paddleId
     *
     * @return \Laravel\Cashier\Billable
     */
    protected function getUserByPaddleId($paddleId)
    {
        return Cashier::findBillable($paddleId);
    }

    /**
     * Handle successful calls on the controller.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function successMethod()
    {
        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param array $parameters
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function missingMethod($parameters = [])
    {
        return new Response;
    }
}
