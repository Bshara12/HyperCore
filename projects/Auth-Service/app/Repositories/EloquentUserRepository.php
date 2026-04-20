<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentUserRepository implements UserRepositoryInterface {
    public function create(array $data):User {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    public function revoke(string $sessionId, $decoded)
    {
        // Revoke session
        DB::table('my_sessions')
            ->where('id', $sessionId)
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        // Revoke all refresh tokens for this session
        DB::table('refresh_tokens')
            ->where('session_id', $sessionId)
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

            // إضافة access token إلى blacklist
        DB::table('token_blacklist')->insert([
            'token_id'   => $decoded->jti,
            'expires_at' => date('Y-m-d H:i:s', $decoded->exp),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // إلغاء جميع refresh tokens للمستخدم
        DB::table('refresh_tokens')
            ->where('user_id', $decoded->sub)
            ->update(['revoked' => true]);
    }

    public function updatePassword($userId, $hashedPassword) {
        return User::where('id', $userId)->update([
            'password' => $hashedPassword
        ]);
    }


    public function getUsersByIds(array $ids): Collection {
        return User::query()
            ->whereIn('id', $ids)
            ->select('id', 'name')
            ->get();
    }
}
