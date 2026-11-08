<?php

declare(strict_types=1);
use ContentCredentials\Omeka\EvidenceController;
use ContentCredentials\Omeka\EvidenceControllerFactory;
use ContentCredentials\Omeka\StateColumnFactory;
use Laminas\Router\Http\Segment;

return [
    // SPEC-009: the module's strings are translated from language/<locale>.mo,
    // a file this module does not ship (amendment 1); language/template.pot
    // lists them. Omeka S merges this with its own translator (step 63).
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__).'/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
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
    // SPEC-006: the evidence pages. Each route has its own __NAMESPACE__, or
    // it inherits the admin route's and ends in a silent 404 (step 24).
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'content-credentials-media-evidence' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/content-credentials/media/:media-id/evidence',
                            'constraints' => ['media-id' => '\d+'],
                            'defaults' => ['__NAMESPACE__' => 'ContentCredentials\Omeka', 'controller' => 'EvidenceController', 'action' => 'browse'],
                        ],
                    ],
                    'content-credentials-evidence' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/content-credentials/evidence/:id[/:action]',
                            'constraints' => ['id' => '\d+', 'action' => 'download'],
                            'defaults' => ['__NAMESPACE__' => 'ContentCredentials\Omeka', 'controller' => 'EvidenceController', 'action' => 'show'],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            EvidenceController::class => EvidenceControllerFactory::class,
        ],
    ],
    // SPEC-003: the filter's field in the advanced search. SPEC-006: the pages.
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__).'/view',
        ],
    ],
];
