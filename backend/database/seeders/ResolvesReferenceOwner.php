<?php

namespace Database\Seeders;

use App\Models\User;
use RuntimeException;

trait ResolvesReferenceOwner
{
    protected function referenceOwner(): User
    {
        $users = User::query()->where('is_active', true)->whereIn('role', ['RND', 'Admin']);
        if ($email = config('seeding.reference_email')) {
            return $users->where('email', $email)->first()
                ?? throw new RuntimeException('SEED_REFERENCE_EMAIL must identify an active RND or Admin account.');
        }

        return (clone $users)->where('role', 'RND')->orderBy('id')->first()
            ?? $users->where('role', 'Admin')->orderBy('id')->first()
            ?? throw new RuntimeException('Reference seeds require an existing active Admin or RND account.');
    }
}
