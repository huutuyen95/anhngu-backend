<?php

namespace App\Repositories;

use App\Models\User;

class ProfileRepository
{
    public function issueToken(User $user): string
    {
        return $user->issueRoleToken()->plainTextToken;
    }

    public function classroom(User $user): mixed
    {
        return $user->classes()->with('teacher:id,name')->first();
    }

    public function activeSessions(User $user): int
    {
        return $user->tokens()->count();
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function updatePassword(User $user, array $data): void
    {
        $user->forceFill($data)->save();
    }

    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function revokeOtherTokens(User $user, ?int $currentId): int
    {
        $query = $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId));
        $count = (clone $query)->count();
        $query->delete();

        return $count;
    }
}
