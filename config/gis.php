<?php

return [
    'mapbox_token' => env('GIS_MAPBOX_TOKEN'),
    'clustering' => [
        'enabled' => (bool) env('GIS_CLUSTERING_ENABLED', true),
        'max_zoom' => (int) env('GIS_CLUSTER_MAX_ZOOM', 16),
        'radius' => (int) env('GIS_CLUSTER_RADIUS', 50),
    ],
    'pwa' => [
        'enabled' => (bool) env('GIS_PWA_ENABLED', true),
        'name' => env('GIS_PWA_NAME', 'ISP Network Map'),
        'short_name' => env('GIS_PWA_SHORT_NAME', 'NetMap'),
    ],
    'api' => [
        'bbox_max_nodes' => (int) env('GIS_API_BBOX_MAX_NODES', 5000),
    ],
];
