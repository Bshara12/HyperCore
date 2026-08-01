<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepositoryInterface;
use Illuminate\Http\Request;

class MeController extends Controller
{
    protected $users;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->users = $userRepository;
    }

    /**
     * جلب بيانات أساسية للمستخدم الحالي عبر توكن المستخدم (auth.jwt)
     * نسخة خفيفة بدون roles/permessions
     */
    public function index(Request $request)
    {
        $userId = $this->authUserId($request);
        $user = $userId ? $this->users->findById($userId) : null;

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json($user);
    }

    /**
     * خدمة تصل إلى مستخدم محدد باستخدام التوكن الخاص بالخدمة ورقم المستخدم id
     */
    public function profile($id)
    {
        $user = $this->users->findById((int) $id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->load('roles.permessions');

        return response()->json(['data' => $user]);
    }

    /**
     * جلب بيانات المستخدم الحالي مع أدواره وصلاحياته عبر توكن المستخدم
     */
    public function myProfile(Request $request)
    {
        $userId = $this->authUserId($request);
        $user = $userId ? $this->users->findById($userId) : null;

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->load('roles.permessions');

        return response()->json(['data' => $user]);
    }

    /**
     * جلب بيانات مستخدم بالـ ID لأغراض التواصل بين الخدمات فقط (internal.api)
     */
    public function internalShow(int $id)
    {
        $user = $this->users->findById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
