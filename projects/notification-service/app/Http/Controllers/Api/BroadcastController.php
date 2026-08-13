<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastController extends Controller
{
    /**
     * POST /api/broadcasting/auth
     *
     * عندما يحاول الـ Frontend الاشتراك في قناة Private (مثل private-user.123)
     * يُرسَل طلب تفويض هنا أولاً.
     * نتحقق من المستخدم عبر UserAuthMiddleware ثم نفوّض له الاشتراك في قناته الخاصة فقط.
     */
    public function authenticate(Request $request): mixed
    {
        // المستخدم تم التحقق منه مسبقاً بواسطة UserAuthMiddleware
        $userData = $request->get('authenticated_user');

        /*
         | إنشاء كائن مستخدم مؤقت يحتوي على ID
         | Broadcast::auth يحتاج لكائن يملك getAuthIdentifier()
         | لأننا لا نستخدم Auth Guard محلي، نُنشئ كائن بسيط هنا
         */
        $user = new class($userData['id']) {
            public function __construct(public readonly string $id) {}
            public function getAuthIdentifier(): string { return $this->id; }
        };

        // تعيين المستخدم مؤقتاً على الـ Request لإتمام التفويض
        $request->setUserResolver(fn () => $user);

        // السماح لـ Laravel بإكمال التحقق من القناة (سيُراجع channels.php)
        return Broadcast::auth($request);
    }
}
