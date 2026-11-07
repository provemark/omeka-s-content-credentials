<?php

declare(strict_types=1);

// M7.13 probe, never shipped. Run from the repository root, with folders of images as arguments.
// Does a real file hold an actions assertion with an `__n` label, and would it change the AI label?

require 'vendor/autoload.php';

use ContentCredentials\Check\AiStatement;
use Provemark\C2paVerifier\Verifier\Verifier;

$files = [];
foreach (array_slice($argv, 1) as $folder) {
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
    foreach ($walk as $file) {
        $path = (string) $file;
        if (preg_match('#/(vendor|node_modules|\.git)/#', $path) !== 1 && preg_match('/\.(jpe?g|png|webp)\z/i', $path) === 1) {
            $files[hash_file('sha256', $path)] = $path;
        }
    }
}

$read = 0;
$failed = 0;
$withManifest = 0;
$instanced = 0;
$labels = [];
$differs = [];
foreach ($files as $path) {
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        continue;
    }
    try {
        $report = (new Verifier)->verify($stream, null)->toArray();
    } catch (Throwable) {
        $failed++;

        continue;
    } finally {
        fclose($stream);
    }
    $read++;
    if (($report['manifests'] ?? []) === []) {
        continue;
    }
    $withManifest++;
    $found = false;
    $rewritten = $report;
    foreach ($report['manifests'] as $name => $manifest) {
        foreach ($manifest['assertions'] ?? [] as $index => $assertion) {
            $label = $assertion['label'] ?? '';
            $base = preg_replace('/__\d+\z/', '', (string) $label);
            if ($base === 'c2pa.actions' || $base === 'c2pa.actions.v2') {
                $labels[$label] = ($labels[$label] ?? 0) + 1;
                if ($base !== $label) {
                    $found = true;
                    $rewritten['manifests'][$name]['assertions'][$index]['label'] = $base;
                }
            }
        }
    }
    if ($found) {
        $instanced++;
        $now = AiStatement::read($report);
        $then = AiStatement::read($rewritten);
        echo basename($path), ': ', $report['validation_state'] ?? '?', ', now ', var_export($now, true), ', stripped ', var_export($then, true), "\n";
        if ($now !== $then) {
            $differs[] = basename($path);
        }
    }
}

ksort($labels);
echo count($files), " distinct files, {$read} read, {$failed} threw, {$withManifest} with a manifest\n";
echo "with an instance label on actions: {$instanced}; the answer differs on: ", count($differs), "\n";
foreach ($labels as $label => $count) {
    echo "  {$label}: {$count}\n";
}
