<?php

declare(strict_types=1);

/*
 * M5.1 probe, never shipped: the verifier's evidence document
 * (evidenceToJson(), format version 1) with the module's fixtures and trust
 * settings, outside Omeka.
 *
 * Usage: php tools/probes/EvidenceProbe/measure.php <out-dir>
 * Prints one JSON line per file and settings; writes each document to
 * <out-dir>/<file>.<settings>.json.
 */

use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
require_once $root.'/src/Trust/BundledLists.php';
require_once $root.'/src/Trust/BuiltTrust.php';
require_once $root.'/src/Trust/TrustChoice.php';

use ContentCredentials\Trust\TrustChoice;

$out = $argv[1] ?? throw new RuntimeException('usage: measure.php <out-dir>');
is_dir($out) || mkdir($out, 0777, true);

$fixtures = $root.'/tests/Fixtures/c2pa/';
$trust = $root.'/trust/';
$work = $out.'/inputs';
is_dir($work) || mkdir($work);

// The inputs: the fixtures, the altered JPEG as the tests make it, and
// inputs that may end without a report.
$files = [];
foreach (['google-20250919-pixel10-npld-picnic-table.jpg', 'c2pa-rs-ocsp.jpg', 'fixture-signed.jpg', 'fixture-signed.png', 'fixture-signed.webp', 'fixture-unsigned.jpg', 'fixture-unsigned.png', 'fixture-unsigned.webp', 'auth-1-1.gif'] as $name) {
    $files[$name] = $fixtures.$name;
}
$signed = (string) file_get_contents($fixtures.'fixture-signed.jpg');
$altered = $signed;
$altered[strlen($altered) - 64] = chr(ord($altered[strlen($altered) - 64]) ^ 0xFF);
file_put_contents($files['altered.jpg'] = $work.'/altered.jpg', $altered);
file_put_contents($files['truncated-pixel10.jpg'] = $work.'/truncated-pixel10.jpg', substr((string) file_get_contents($fixtures.'google-20250919-pixel10-npld-picnic-table.jpg'), 0, 65536));
file_put_contents($files['empty.jpg'] = $work.'/empty.jpg', '');
file_put_contents($files['random.jpg'] = $work.'/random.jpg', "\xFF\xD8".hash('sha512', 'm5.1', true).str_repeat("\x00", 100));

$c2pa = (string) file_get_contents($trust.'C2PA-TRUST-LIST.pem');
$tsa = (string) file_get_contents($trust.'C2PA-TSA-TRUST-LIST.pem');
$digicert = (string) file_get_contents($trust.'DigiCertTrustedRootG4.crt.pem');
$settings = [
    'none' => static fn (): ?TrustSettings => null,
    'json' => static fn (): TrustSettings => TrustSettings::fromJson(TrustChoice::bundledJson(false, rtrim($trust, '/'))),
    'json+digicert' => static fn (): TrustSettings => TrustSettings::fromJson(TrustChoice::bundledJson(true, rtrim($trust, '/'))),
    'lists' => static fn (): TrustSettings => TrustSettings::fromTrustLists($c2pa, $tsa),
    'lists+digicert' => static fn (): TrustSettings => TrustSettings::fromTrustLists($c2pa, $tsa."\n".$digicert),
];

$fixed = new DateTimeImmutable('2026-11-05T12:00:00Z');
$verifier = new Verifier;

/** @return array{0: ?object, 1: ?string, 2: float} report, exception class, ms */
$verify = static function (string $path, ?TrustSettings $s, ?DateTimeImmutable $at) use ($verifier): array {
    $stream = fopen($path, 'rb');
    $t = hrtime(true);
    try {
        $report = $verifier->verify($stream, $s, $at);

        return [$report, null, (hrtime(true) - $t) / 1e6];
    } catch (Throwable $e) {
        return [null, $e::class.': '.$e->getMessage(), (hrtime(true) - $t) / 1e6];
    } finally {
        fclose($stream);
    }
};

foreach ($settings as $settingsName => $make) {
    $t = hrtime(true);
    $s = $make();
    $buildMs = (hrtime(true) - $t) / 1e6;
    foreach ($files as $name => $path) {
        [$report, $exception, $verifyMs] = $verify($path, $s, null);
        $row = ['php' => PHP_VERSION, 'verifier' => Verifier::VERSION, 'settings' => $settingsName, 'settings_ms' => round($buildMs, 1), 'file' => $name, 'file_bytes' => filesize($path), 'verify_ms' => round($verifyMs, 1)];
        if ($report === null) {
            echo json_encode($row + ['exception' => $exception], JSON_UNESCAPED_SLASHES), "\n";

            continue;
        }
        // The cost of writing the document: the median of 20 calls.
        $times = [];
        for ($i = 0; $i < 20; $i++) {
            $t = hrtime(true);
            $json = $report->evidenceToJson();
            $times[] = (hrtime(true) - $t) / 1e6;
        }
        sort($times);
        file_put_contents($out.'/'.$name.'.'.$settingsName.'.json', $json);
        $doc = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // Determinism: the same file, settings and fixed time, twice; and
        // the clock (as the module calls verify()), one second apart.
        [$a] = $verify($path, $s, $fixed);
        [$b] = $verify($path, $s, $fixed);
        sleep(1);
        [$c] = $verify($path, $s, null);
        $sameFixed = $a?->evidenceToJson() === $b?->evidenceToJson();
        $sameClock = $c?->evidenceToJson() === $json;

        echo json_encode($row + [
            'state' => $report->result->toArray()['validation_state'],
            'has_manifest' => $report->hasManifest,
            'evidence_bytes' => strlen($json),
            'evidence_sha256' => hash('sha256', $json),
            'to_json_ms' => round($times[10], 2),
            'ascii' => preg_match('/[^\x0A\x20-\x7E]/', $json) === 0,
            'same_with_fixed_time' => $sameFixed,
            'same_by_clock_1s_later' => $sameClock,
            'keys' => array_keys($doc),
            'trust_lists' => $doc['trust_lists'],
            'manifests' => count($doc['manifests']),
            'time_sources' => array_values(array_map(static fn (array $m): string => $m['time_source'], $doc['manifests'])),
            'signer_anchor' => array_values(array_map(static fn (array $m): bool => $m['signer'] !== null, $doc['manifests'])),
            'tsa_anchor' => array_values(array_map(static fn (array $m): bool => $m['tsa'] !== null, $doc['manifests'])),
            'certificates' => array_sum(array_map(static fn (array $m): int => count($m['certificates']), $doc['manifests'])),
            'timestamps' => array_sum(array_map(static fn (array $m): int => count($m['timestamps']), $doc['manifests'])),
            'ocsp' => array_sum(array_map(static fn (array $m): int => count($m['ocsp']), $doc['manifests'])),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ], JSON_UNESCAPED_SLASHES), "\n";
    }
}
