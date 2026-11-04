<?php

declare(strict_types=1);
use ViewProbe\ProbeColumn;

return [
    'column_types' => [
        'invokables' => [
            'view_probe' => ProbeColumn::class,
        ],
    ],
    // Measured: whether a module can add a column to every admin's default.
    'column_defaults' => [
        'admin' => [
            'media' => [
                ['type' => 'view_probe'],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__).'/view',
        ],
    ],
];
