<?php

namespace App\Exceptions;

use Exception;

class UserNotFoundException extends Exception
{
    /**
     * نُنشئ Exception خاص بحالة عدم وجود المستخدم
     * لتمييزها عن باقي الأخطاء ومعالجتها بـ Response مناسب (404)
     */
    public function __construct(string $userId)
    {
        parent::__construct("User [{$userId}] not found in auth service.");
    }
}
