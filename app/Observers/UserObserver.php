<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Staff\ActivityLogger;

class UserObserver
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function created(User $user): void
    {
        $this->logger->log('user.created', "Staff user {$user->email} created", $user, [
            'roles' => $user->getRoleNames()->all(),
        ]);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged(['email', 'name', 'is_active', 'branch_id', 'tenant_id', 'allowed_ips'])) {
            $this->logger->log('user.updated', "Staff user {$user->email} updated", $user, [
                'changes' => $user->getChanges(),
            ]);
        }
    }

    public function deleted(User $user): void
    {
        $this->logger->log('user.deleted', "Staff user {$user->email} deleted", $user);
    }
}
