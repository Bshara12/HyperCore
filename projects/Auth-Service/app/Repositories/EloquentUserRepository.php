<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
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

    public function updatePassword($userId, $hashedPassword)
    {
        return User::where('id', $userId)->update(['password' => $hashedPassword]);
    }

    public function getUsersByIds(array $ids): Collection
    {
        return User::query()->whereIn('id', $ids)->select('id', 'name')->get();
    }

    public function getUsersByEmails(array $emails): Collection
    {
        return User::query()
            ->whereIn('email', $emails)
            ->select('id', 'name', 'email')
            ->get();
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }
}
