<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface {
    public function create(array $data): User;
    public function findByEmail(string $email):?User;
    public function findById(int $id):?User;
    public function update(User $user, array $data):bool;

    public function revoke(string $sessionId, $decoded);
    public function updatePassword($userId, $hashedPassword);

    public function getUsersByIds(array $ids): Collection;
}
