<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function update(User $user, array $data): bool;

    public function updatePassword($userId, $hashedPassword);

    public function getUsersByIds(array $ids): Collection;

    /**
     * Resolve accounts by email. Unknown addresses are absent from the result.
     *
     * @param  array<int, string>  $emails
     */
    public function getUsersByEmails(array $emails): Collection;

    public function delete(User $user): bool;
}
