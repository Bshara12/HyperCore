<?php

use Illuminate\Support\Facades\Broadcast;

/*
|─────────────────────────────────────────────────────────────────────────────
| Broadcast Channels
| تُحدَّد هنا قواعد التفويض لكل قناة
|─────────────────────────────────────────────────────────────────────────────
*/

/*
 | القناة الخاصة بكل مستخدم: private-user.{userId}
 |
 | المستخدم يمكنه الاشتراك في قناته الخاصة فقط
 | $user هو الكائن الذي أنشأناه في BroadcastController
 */
Broadcast::channel('user.{userId}', function ($user, string $userId): bool {
    return (string) $user->getAuthIdentifier() === (string) $userId;
});
