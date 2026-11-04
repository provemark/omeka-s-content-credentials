<?php

declare(strict_types=1);

// The mapping as the Mapping module registers its own; no proxy_paths, to
// measure whether an entity without proxies works (step 10).
return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__).'/src/Entity',
        ],
    ],
];
