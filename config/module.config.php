<?php

declare(strict_types=1);

// SPEC-002 / ADR-0003: the verdict table as a Doctrine entity, mapped as the
// Mapping module maps its own; no proxy_paths (step 10).
return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__).'/src/Entity',
        ],
    ],
];
