<?php

declare(strict_types=1);

/*
 * M6.1 probe, never shipped. Usage: php measure.php <inputs-dir>
 * Every file in <inputs-dir> with the module's three settings: the verdict,
 * the manifests and both AI rules; for the default settings also the verdict
 * by time, and what toArray() costs. One JSON line per file.
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

$dir = $argv[1] ?? throw new RuntimeException('usage: measure.php <inputs-dir>');
$settings = [
    'none' => null,
    'c2pa' => TrustSettings::fromJson(TrustChoice::bundledJson(false, $root.'/trust')),
    'default' => TrustSettings::fromJson(TrustChoice::bundledJson(true, $root.'/trust')),
];
$verifier = new Verifier;
$check = static function (string $path, ?TrustSettings $s, ?DateTimeImmutable $at = null) use ($verifier) {
    $stream = fopen($path, 'rb');
    try {
        return $verifier->verify($stream, $s, $at);
    } finally {
        fclose($stream);
    }
};
$times = ['2026-11-05', '2027-04-24', '2028-01-01', '2030-08-27', '2031-01-01', '2035-01-01', '2045-01-01'];
$stateAt = static fn (string $path, string $date): string => $check($path, $settings['default'], new DateTimeImmutable($date.'T12:00:00Z'))->result->toArray()['validation_state'];

foreach (glob($dir.'/*') as $path) {
    $row = ['php' => PHP_VERSION, 'verifier' => Verifier::VERSION, 'file' => basename($path), 'bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)];
    foreach ($settings as $name => $s) {
        $report = $check($path, $s);
        $array = $report->toArray();
        $walk = aiWalk($array);
        $row[$name] = [
            'has_manifest' => $report->hasManifest,
            'state' => $array['validation_state'],
            'codes' => array_values(array_unique(array_column($array['validation_status'] ?? [], 'code'))),
            'active_rule' => aiRuleActive($walk),
            'history_rule' => aiRuleHistory($walk),
        ];
    }
    $report = $check($path, $settings['default']);
    $samples = [];
    for ($i = 0; $i < 20; $i++) {
        $t = hrtime(true);
        $report->toArray();
        $samples[] = (hrtime(true) - $t) / 1e6;
    }
    sort($samples);
    $t = hrtime(true);
    $check($path, $settings['default']);
    $row['verify_ms'] = round((hrtime(true) - $t) / 1e6, 1);
    $row['to_array_ms'] = round($samples[10], 2);
    $row['walk'] = array_map(static fn (array $n): array => [
        'depth' => $n['depth'], 'path' => $n['path'], 'gated' => $n['gated'],
        'actions' => array_map(static fn (array $a): string => $a[0].'='.aiShort($a[1]), $n['actions']),
        'edges' => $n['edges'],
    ], aiWalk($report->toArray()));
    $row['by_time'] = [];
    $previous = null;
    foreach ($times as $date) {
        $state = $stateAt($path, $date);
        $row['by_time'][$date] = $state;
        if ($previous !== null && $state !== $previous[1]) {
            // the day it changes, by binary search
            $lo = (new DateTimeImmutable($previous[0]))->getTimestamp();
            $hi = (new DateTimeImmutable($date))->getTimestamp();
            while ($hi - $lo > 86400) {
                $mid = intdiv($lo + $hi, 2);
                $check($path, $settings['default'], new DateTimeImmutable('@'.$mid))->result->toArray()['validation_state'] === $previous[1] ? $lo = $mid : $hi = $mid;
            }
            $row['changes'][] = gmdate('Y-m-d', $hi).' '.$previous[1].'->'.$state;
        }
        $previous = [$date, $state];
    }
    echo json_encode($row, JSON_UNESCAPED_SLASHES), "\n";
}
