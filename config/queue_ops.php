<?php

return [

    /**
     * When true, heavy ISP tasks (MikroTik poll, network sync) use the queue instead of running inline.
     * Requires a queue worker: php artisan queue:work or Horizon.
     */
    'heavy_jobs_enabled' => (bool) env('QUEUE_HEAVY_JOBS_ENABLED', false),

];
