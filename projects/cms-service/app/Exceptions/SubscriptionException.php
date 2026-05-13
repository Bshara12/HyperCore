<?php

namespace App\Exceptions;

use Exception;

abstract class SubscriptionException extends Exception
{
  abstract public function context(): array;
}
