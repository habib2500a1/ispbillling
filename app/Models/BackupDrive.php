<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupDrive extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'name',
        'mount_path',
        'enabled',
        'mirror_on_backup',
        'max_archives',
        'retention_days',
        'last_mirrored_at',
        'last_mirror_status',
        'last_mirror_error',
        'last_mirror_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'mirror_on_backup' => 'boolean',
            'max_archives' => 'integer',
            'retention_days' => 'integer',
            'last_mirrored_at' => 'datetime',
            'last_mirror_size_bytes' => 'integer',
        ];
    }

    public function effectiveMaxArchives(): int
    {
        return $this->max_archives ?? (int) config('backup.max_archives', 20);
    }

    public function effectiveRetentionDays(): int
    {
        return $this->retention_days ?? (int) config('backup.retention_days', 14);
    }
}
