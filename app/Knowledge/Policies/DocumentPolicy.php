<?php

namespace App\Knowledge\Policies;

use App\Knowledge\Models\Document;
use App\Models\User;

final class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHrAdmin();
    }

    public function view(User $user, Document $document): bool
    {
        return $user->isHrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isHrAdmin();
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isHrAdmin();
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isHrAdmin();
    }
}
