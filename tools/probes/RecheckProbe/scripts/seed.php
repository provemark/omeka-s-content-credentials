<?php

declare(strict_types=1);
use Omeka\Entity\User;
use Omeka\Mvc\Application;

// Measurement only (M4.2, notes/step-20-recheck-at-scale.md), never shipped.
// Run inside the PHP 8.3 container as www-data, with the fixture server up:
//   php seed.php <items> <media per item>
// Adds items whose media come through the URL ingester from the module's
// tests/Fixtures/c2pa/, in a fixed mix of types.

function seed(string $root, int $items, int $perItem): void
{
    require $root.'/bootstrap.php';
    $config = require $root.'/application/config/application.config.php';
    $services = Application::init($config)->getServiceManager();
    $em = $services->get('Omeka\EntityManager');
    $services->get('Omeka\AuthenticationService')->getStorage()->write($em->find(User::class, 1));
    $api = $services->get('Omeka\ApiManager');
    $mix = array_merge(
        array_fill(0, 8, 'fixture-signed.jpg'), array_fill(0, 3, 'fixture-signed.png'), array_fill(0, 3, 'fixture-signed.webp'),
        array_fill(0, 4, 'fixture-unsigned.jpg'), ['c2pa-rs-ocsp.jpg'], ['google-20250919-pixel10-npld-picnic-table.jpg'],
    );
    $n = 0;
    $t = microtime(true);
    for ($i = 0; $i < $items; $i++) {
        $media = [];
        for ($j = 0; $j < $perItem; $j++) {
            $media[] = ['o:ingester' => 'url', 'ingest_url' => 'http://127.0.0.1:9000/'.$mix[$n++ % count($mix)]];
        }
        $api->create('items', ['o:media' => $media]);
        $em->clear();
        // clear() detaches the logged-in user too.
        $services->get('Omeka\AuthenticationService')->getStorage()->write($em->find(User::class, 1));
        if ($i % 25 === 24) {
            printf("%d items, %d media, %.0fs\n", $i + 1, $n, microtime(true) - $t);
        }
    }
    printf("done: %d items, %d media, %.0fs\n", $items, $n, microtime(true) - $t);
}

seed((string) getenv('OMEKA_ROOT'), (int) ($argv[1] ?? 1), (int) ($argv[2] ?? 10));
