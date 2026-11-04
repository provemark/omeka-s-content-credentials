<?php

declare(strict_types=1);
use ContentCredentials\Omeka\StateColumnFactory;

return [
    // SPEC-002 / ADR-0003: the verdict table as a Doctrine entity, mapped as
    // the Mapping module maps its own; no proxy_paths (step 10).
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__).'/src/Entity',
        ],
    ],
    // SPEC-003: the admin media browse's column, shown to every
    // administrator who has not chosen their own columns (step 13).
    'column_types' => [
        'factories' => [
            'content_credentials' => StateColumnFactory::class,
        ],
    ],
    'column_defaults' => [
        'admin' => [
            'media' => [
                ['type' => 'content_credentials'],
            ],
        ],
    ],
    // SPEC-003: the filter's field in the advanced search.
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__).'/view',
        ],
    ],
];
