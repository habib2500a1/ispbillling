<?php

return [

    /**
     * When true, heavy ISP tasks (MikroTik poll, network suspend/reconnect) use Redis workers
     * instead of running inline or afterResponse in the web process.
     * Requires: php artisan queue:work --queue=network,default (or Horizon).
     */
    'heavy_jobs_enabled' => (bool) env('QUEUE_HEAVY_JOBS_ENABLED', false),

];
