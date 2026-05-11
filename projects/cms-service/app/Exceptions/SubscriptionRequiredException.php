<?php

namespace App\Exceptions;

use Exception;

class SubscriptionRequiredException
extends Exception
{
    protected $message =
        'Active subscription required.';
}