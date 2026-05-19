<?php

return [

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => env('BACKUP_PATH', 'backups'),

    'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 14)),

    'max_archives' => max(3, (int) env('BACKUP_MAX_ARCHIVES', 20)),

    'include_storage_app' => (bool) env('BACKUP_INCLUDE_STORAGE_APP', true),

    'pg_dump_binary' => env('BACKUP_PG_DUMP', 'pg_dump'),

    'pg_restore_binary' => env('BACKUP_PG_RESTORE', 'pg_restore'),

];
