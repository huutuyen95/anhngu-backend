<?php

namespace App\Repositories;

use App\Models\User;

class AuthRepository
{
    public function issueToken(User $user): string
    {
        return $user->issueRoleToken()->plainTextToken;
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function updatePassword(User $user, array $data): void
    {
        $user->forceFill($data)->save();
    }

    public function deleteCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
