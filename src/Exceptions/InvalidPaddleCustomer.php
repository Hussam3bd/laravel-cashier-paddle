<?php

namespace Laravel\Cashier\Exceptions;

use Exception;

class InvalidPaddleCustomer extends Exception
{
    /**
     * Create a new InvalidPaddleCustomer instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     *
     * @return static
     */
    public static function nonCustomer($owner)
    {
        return new static(class_basename($owner) . ' is not a Paddle customer. See the createAsPaddleCustomer method.');
    }

    /**
     * Create a new InvalidPaddleCustomer instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     *
     * @return static
     */
    public static function exists($owner)
    {
        return new static(class_basename($owner) . " is already a Paddle customer with ID {$owner->paddle_id}.");
    }
}
