<?php

namespace App\Support;

final class InternalTaskStatus
{
    public const PENDING = 'pending';

    public const IN_PROGRESS = 'in_progress';

    public const DONE = 'done';

    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function kanbanColumns(): array
    {
        return [
            self::PENDING,
            self::IN_PROGRESS,
            self::DONE,
            self::CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In progress',
            self::DONE => 'Done',
            self::CANCELLED => 'Cancelled',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return [
            self::PENDING => 'slate',
            self::IN_PROGRESS => 'amber',
            self::DONE => 'emerald',
            self::CANCELLED => 'rose',
        ];
    }

    public static function normalize(string $status): string
    {
        return in_array($status, self::kanbanColumns(), true) ? $status : self::PENDING;
    }
}
