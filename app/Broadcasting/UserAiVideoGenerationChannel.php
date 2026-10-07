<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

class UserAiVideoGenerationChannel
{
    public function join(User $user, User $owner, string $generationId): bool
    {
        return $user->is($owner);
    }
}
