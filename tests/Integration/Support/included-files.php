<?php

declare(strict_types=1);
use ContentCredentials\Entity\StoredVerdict;
use Doctrine\ORM\EntityManager;
use Laminas\Authentication\AuthenticationService;
use Omeka\Api\Manager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Entity\User;
use Omeka\Mvc\Application;
use Psr\Log\LoggerInterface;

/*
 * SPEC-002 AC13, run inside the PHP 8.3 container by the integration test:
 * boots Omeka S (the module active), adds an item with a signed JPEG through
 * the URL ingester, and prints as JSON which files of the module's vendor/
 * were included and where Omeka's PSR logger interface comes from; and the
 * new media's state read through the entity mapping (SPEC-002 scope).
 *
 * Usage: php included-files.php <url of the file>
 */

// In a function, with names of its own: Omeka's application.config.php is
// read with require and sets variables of the scope it is read in ($url
// among them, measured).
function spec002IncludedFiles(string $omekaRoot, string $ingestUrl): void
{
    require $omekaRoot.'/bootstrap.php';
    $config = require $omekaRoot.'/application/config/application.config.php';
    if (! is_array($config)) {
        exit(2);
    }
    $services = Application::init($config)->getServiceManager();
    $em = $services->get('Omeka\EntityManager');
    $auth = $services->get('Omeka\AuthenticationService');
    $api = $services->get('Omeka\ApiManager');
    if (! $em instanceof EntityManager || ! $auth instanceof AuthenticationService || ! $api instanceof Manager) {
        exit(2);
    }
    $auth->getStorage()->write($em->find(User::class, 1));

    $response = $api->create('items', ['o:media' => [['o:ingester' => 'url', 'ingest_url' => $ingestUrl]]]);
    $item = $response->getContent();
    $mediaIds = [];
    if ($item instanceof ItemRepresentation) {
        foreach ($item->media() as $media) {
            $mediaIds[] = $media->id();
        }
    }

    $em->clear();
    $stored = isset($mediaIds[0]) ? $em->find(StoredVerdict::class, $mediaIds[0]) : null;

    $vendor = dirname(__DIR__, 3).'/vendor/';
    $verifierSrc = $vendor.'provemark/c2pa-verifier/src/';
    $fromVendor = array_values(array_filter(
        get_included_files(),
        static fn (string $file): bool => str_starts_with($file, $vendor) && ! str_starts_with($file, $verifierSrc),
    ));
    $verifierFiles = count(array_filter(get_included_files(), static fn (string $file): bool => str_starts_with($file, $verifierSrc)));

    echo json_encode([
        'media' => $mediaIds,
        'vendor_outside_verifier' => $fromVendor,
        'verifier_files' => $verifierFiles,
        'entity_state' => $stored instanceof StoredVerdict ? $stored->getState() : null,
        'logger_interface' => (new ReflectionClass(LoggerInterface::class))->getFileName(),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
}

$root = getenv('OMEKA_ROOT');
$url = $argv[1] ?? null;
if (! is_string($root) || ! is_string($url)) {
    fwrite(STDERR, "Usage: OMEKA_ROOT=... php included-files.php <url>\n");
    exit(2);
}
spec002IncludedFiles($root, $url);
