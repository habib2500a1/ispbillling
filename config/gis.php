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
        'bbox_max_customers' => (int) env('GIS_API_BBOX_MAX_CUSTOMERS', 100_000),
    ],

    /** Enterprise NOC layer toggles (Network operations map). */
    'enterprise_layers' => [
        'customers' => ['label' => 'Customers', 'default' => true],
        'onu' => ['label' => 'ONU telemetry', 'default' => true],
        'olt' => ['label' => 'OLT', 'default' => true],
        'pop' => ['label' => 'POP / FAT', 'default' => true],
        'splitter' => ['label' => 'Splitter / plant', 'default' => true],
        'fiber' => ['label' => 'Fiber routes', 'default' => true],
        'incidents' => ['label' => 'Incidents / faults', 'default' => true],
        'tickets' => ['label' => 'Open tickets', 'default' => false],
        'coverage' => ['label' => 'Coverage polygons', 'default' => false],
        'pop_equipment' => ['label' => 'POP equipment', 'default' => false],
        'field_staff' => ['label' => 'Live field GPS', 'default' => false],
        'vector_tiles' => ['label' => 'Vector tiles (PostGIS)', 'default' => false],
        'outage_areas' => ['label' => 'Outage heat map', 'default' => false],
    ],

    'outage_area_min_offline' => (int) env('GIS_OUTAGE_AREA_MIN_OFFLINE', 50),

  /** OSRM / routing for field technician navigation. */
    'routing' => [
        'osrm_base_url' => env('GIS_OSRM_BASE_URL', 'https://router.project-osrm.org'),
        'profile' => env('GIS_OSRM_PROFILE', 'driving'),
    ],

    /** PostGIS vector tiles via pg_tileserv (proxied at /gis/tiles). */
    'vector_tiles' => [
        'enabled' => (bool) env('GIS_VECTOR_TILES_ENABLED', true),
        'base_url' => env('GIS_VECTOR_TILES_BASE_URL', '/gis/tiles'),
        'layers' => [
            'customers' => ['label' => 'Customers MVT', 'table' => 'public.gis_mvt_customers', 'min_zoom' => 10, 'max_zoom' => 18],
            'plant_nodes' => ['label' => 'Plant nodes MVT', 'table' => 'public.gis_mvt_plant_nodes', 'min_zoom' => 12, 'max_zoom' => 20],
            'pop_boxes' => ['label' => 'POP boxes MVT', 'table' => 'public.gis_mvt_pop_boxes', 'min_zoom' => 11, 'max_zoom' => 20],
            'field_staff' => ['label' => 'Field staff MVT', 'table' => 'public.gis_mvt_field_staff', 'min_zoom' => 10, 'max_zoom' => 20],
        ],
    ],

    /** Fiber deployment style for map rendering. */
    'fiber_deployment' => [
        'aerial' => ['label' => 'Aerial', 'dash' => null, 'weight' => 4],
        'underground' => ['label' => 'Underground', 'dash' => '2 6', 'weight' => 5],
        'backbone' => ['label' => 'Backbone', 'dash' => null, 'weight' => 6],
        'distribution' => ['label' => 'Distribution', 'dash' => null, 'weight' => 4],
        'drop' => ['label' => 'Drop', 'dash' => '6 4', 'weight' => 2],
    ],
];
