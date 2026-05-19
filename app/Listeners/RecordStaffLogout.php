<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Staff\ActivityLogger;
use Illuminate\Auth\Events\Logout;

final class RecordStaffLogout
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $user->forceFill([
            'last_logout_at' => now(),
            'last_logout_ip' => request()->ip(),
        ])->saveQuietly();

        $this->activityLogger->log(
            'logout',
            'Staff logged out',
            $user,
            [
                'email' => $user->email,
            ],
        );
    }
}
