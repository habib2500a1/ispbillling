<?php

return [

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => env('BACKUP_PATH', 'backups'),

    'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 14)),

    'max_archives' => max(3, (int) env('BACKUP_MAX_ARCHIVES', 20)),

    'include_storage_app' => (bool) env('BACKUP_INCLUDE_STORAGE_APP', true),

    'pg_dump_binary' => env('BACKUP_PG_DUMP', 'pg_dump'),

    'pg_restore_binary' => env('BACKUP_PG_RESTORE', 'pg_restore'),

    /*
    | Comma-separated absolute path prefixes allowed for extra backup drives
    | (USB/NFS mounts). Example: /mnt,/media,/backup,/var/backups
    */
    'allowed_drive_roots' => array_values(array_filter(array_map(
        static fn (string $part): string => rtrim(trim($part), '/'),
        explode(',', (string) env('BACKUP_ALLOWED_DRIVE_ROOTS', '/mnt,/media,/backup,/var/backups,/home')),
    ))),

    /** Subfolder created on each drive when mirroring ZIP archives */
    'drive_subdirectory' => env('BACKUP_DRIVE_SUBDIR', 'isp-platform'),

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'folder_name' => env('GOOGLE_DRIVE_FOLDER_NAME', 'ISP Platform Backups'),
    ],

];
