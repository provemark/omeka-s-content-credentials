<?php

declare(strict_types=1);

/*
 * M6.2 probe, never shipped. Usage: php deltas.php <root>
 * Every JPEG, PNG and WebP under <root>, without settings: for each ingredient
 * that names a manifest, in every manifest of the store, whether the verifier's
 * ingredientDeltas holds an entry under the URI SPEC-027's gate builds.
 */

use Provemark\C2paVerifier\Verifier\Verifier;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$v = new Verifier;
$multi = 0;
$matched = 0;
$unmatched = 0;
$labels = [];
$rel = [];
$files = 0;
$ex = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1], FilesystemIterator::SKIP_DOTS)) as $f) {
    if (! in_array(strtolower($f->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
        continue;
    }
    $s = fopen($f->getPathname(), 'rb');
    try {
        $a = $v->verify($s, null)->toArray();
    } catch (Throwable $e) {
        continue;
    } finally {
        fclose($s);
    }
    $uris = array_column($a['validation_results']['ingredientDeltas'] ?? [], 'ingredientAssertionURI');
    foreach ($a['manifests'] as $l => $m) {
        $ings = $m['ingredients'] ?? [];
        if (! is_array($ings)) {
            continue;
        } $withTarget = array_filter($ings, fn ($i) => isset($i['active_manifest']));
        if (count($withTarget) > 1) {
            $multi++;
            if (count($ex) < 3) {
                $ex[] = substr($f->getPathname(), strlen($argv[1]) + 1).' '.$a['validation_state'].' '.implode(',', array_column($withTarget, 'label'));
            }
        }
        foreach ($withTarget as $i) {
            $u = 'self#jumbf=/c2pa/'.$l.'/c2pa.assertions/'.($i['label'] ?? '');
            in_array($u, $uris, true) ? $matched++ : $unmatched++;
            $rel[(string) ($i['relationship'] ?? '?')] = ($rel[(string) ($i['relationship'] ?? '?')] ?? 0) + 1;
            if (! in_array($u, $uris, true) && count($labels) < 4) {
                $labels[] = substr($f->getPathname(), strlen($argv[1]) + 1).' '.$a['validation_state'].' '.$u.' uris='.count($uris);
            }
        }
    }
    $files++;
}
echo json_encode(compact('files', 'multi', 'matched', 'unmatched', 'rel', 'ex', 'labels'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),"\n";
