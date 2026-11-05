<?php

declare(strict_types=1);

/*
 * M6.1 probe, never shipped. Usage: php census.php <root>...
 * Every JPEG, PNG and WebP under the roots whose bytes contain an IPTC
 * digitalsourcetype, with the module's default settings: which source types
 * toArray() gives, in which position, and how the two AI rules answer.
 */

use ContentCredentials\Trust\TrustChoice;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
require_once $root.'/src/Trust/BundledLists.php';
require_once $root.'/src/Trust/BuiltTrust.php';
require_once $root.'/src/Trust/TrustChoice.php';
require __DIR__.'/lib.php';

$settings = TrustSettings::fromJson(TrustChoice::bundledJson(true, $root.'/trust'));
$verifier = new Verifier;
$files = $inBytes = $notString = 0;
$exceptions = [];
$types = $states = $byRule = $differ = $notPrefix = [];
foreach (array_slice($argv, 1) as $start) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($start, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }
        $bytes = (string) file_get_contents($file->getPathname());
        if (! str_contains($bytes, 'digitalsourcetype/')) {
            continue;
        }
        $files++;
        $stream = fopen($file->getPathname(), 'rb');
        try {
            $array = $verifier->verify($stream, $settings)->toArray();
        } catch (Throwable $e) {
            $exceptions[$e::class] = ($exceptions[$e::class] ?? 0) + 1;

            continue;
        } finally {
            fclose($stream);
        }
        $state = $array['validation_state'];
        $states[$state] = ($states[$state] ?? 0) + 1;
        $walk = aiWalk($array);
        foreach ($walk as $node) {
            foreach ($node['actions'] as [, $type]) {
                $short = aiShort($type);
                $position = ($node['depth'] === 0 ? 'active' : 'ingredient');
                $types[$short][$position] = ($types[$short][$position] ?? 0) + 1;
                if (! is_string($type)) {
                    $notString++;
                }
            }
        }
        $active = aiRuleActive($walk);
        $history = aiRuleHistory($walk);
        $key = $state.' active='.($active ? 'yes' : 'no').' history='.($history ?? 'no');
        $byRule[$key] = ($byRule[$key] ?? 0) + 1;
        if (str_contains($bytes, 'rainedAlgorithmicMedia') && $walk !== [] && ! $active && $history === null) {
            $differ[] = substr($file->getPathname(), strlen($start) + 1).' '.$state;
        }
    }
}
ksort($types);
ksort($byRule);
echo json_encode(compact('files', 'states', 'types', 'byRule', 'notString', 'exceptions', 'differ'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
