<?php

return [
    /*
    | TIA-598-C fiber jacket colors (12-fiber standard cycle).
    | Used on map polylines and admin pickers.
    */
    'cable_colors' => [
        'blue' => ['label' => 'Blue', 'hex' => '#2563eb', 'tube' => 1],
        'orange' => ['label' => 'Orange', 'hex' => '#ea580c', 'tube' => 2],
        'green' => ['label' => 'Green', 'hex' => '#16a34a', 'tube' => 3],
        'brown' => ['label' => 'Brown', 'hex' => '#92400e', 'tube' => 4],
        'slate' => ['label' => 'Slate', 'hex' => '#64748b', 'tube' => 5],
        'white' => ['label' => 'White', 'hex' => '#f8fafc', 'tube' => 6],
        'red' => ['label' => 'Red', 'hex' => '#dc2626', 'tube' => 7],
        'black' => ['label' => 'Black', 'hex' => '#171717', 'tube' => 8],
        'yellow' => ['label' => 'Yellow', 'hex' => '#ca8a04', 'tube' => 9],
        'violet' => ['label' => 'Violet', 'hex' => '#7c3aed', 'tube' => 10],
        'rose' => ['label' => 'Rose', 'hex' => '#e11d48', 'tube' => 11],
        'aqua' => ['label' => 'Aqua', 'hex' => '#06b6d4', 'tube' => 12],
    ],

    'cable_types' => [
        'backbone' => 'Backbone (OLT → POP)',
        'distribution' => 'Distribution (POP → splitter)',
        'drop' => 'Drop (splitter → customer)',
        'patch' => 'Patch / indoor',
    ],

    'node_types' => [
        'olt' => ['label' => 'OLT', 'icon' => 'olt', 'color' => '#7c3aed'],
        'pop' => ['label' => 'POP / FAT box', 'icon' => 'pop', 'color' => '#0891b2'],
        'splitter' => ['label' => 'Splitter', 'icon' => 'splitter', 'color' => '#ea580c'],
        'pole' => ['label' => 'Pole', 'icon' => 'pole', 'color' => '#78716c'],
        'junction' => ['label' => 'Junction / splice', 'icon' => 'junction', 'color' => '#ca8a04'],
        'closure' => ['label' => 'Closure', 'icon' => 'closure', 'color' => '#64748b'],
        'customer' => ['label' => 'Customer / ONU', 'icon' => 'customer', 'color' => '#16a34a'],
        'other' => ['label' => 'Other', 'icon' => 'other', 'color' => '#94a3b8'],
    ],

    'directions' => [
        'N' => 'North ↑',
        'NE' => 'North-East ↗',
        'E' => 'East →',
        'SE' => 'South-East ↘',
        'S' => 'South ↓',
        'SW' => 'South-West ↙',
        'W' => 'West ←',
        'NW' => 'North-West ↖',
    ],

    'splitter_ratios' => [2, 4, 8, 16, 32],
];
