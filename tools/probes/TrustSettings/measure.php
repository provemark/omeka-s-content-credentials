<?php

declare(strict_types=1);
// Measurement only (M3.2, notes/step-16-trust-lists.md), never shipped.
// Needs a checkout of provemark/tracefern-image-check next to this
// repository (../C2PA_Verifier_WP) for its trust/ files and fixtures.
// Usage: php measure.php <output directory>
require dirname(__DIR__, 3).'/vendor/autoload.php';
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

$out = $argv[1];
$tf = dirname(dirname(__DIR__, 3)).'/C2PA_Verifier_WP/trust/';
$tfx = dirname(dirname(__DIR__, 3)).'/C2PA_Verifier_WP/tests/Fixtures/';
$own = dirname(__DIR__, 3).'/tests/Fixtures/c2pa/';
$e = fn (string $path, string $kind): array => ['trust_anchors' => file_get_contents($path), 'trust_kind' => $kind];
$variants = [
    'none' => null,
    'c2pa' => [$e($tf.'C2PA-TRUST-LIST.pem', 'manifest'), $e($tf.'C2PA-TSA-TRUST-LIST.pem', 'tsa')],
    'c2pa+digicert' => [$e($tf.'C2PA-TRUST-LIST.pem', 'manifest'), $e($tf.'C2PA-TSA-TRUST-LIST.pem', 'tsa'), $e($tf.'DigiCertTrustedRootG4.crt.pem', 'tsa')],
    'custom-test-root' => [$e($tfx.'c2pa-rs-test-trust-anchors.pem', 'manifest')],
];
$files = [];
foreach ($variants as $name => $anchors) {
    if ($anchors !== null) {
        $files[$name] = "$out/settings-$name.json";
        file_put_contents($files[$name], json_encode(['verify' => ['verify_trust' => true], 'trust' => ['anchors' => $anchors]], JSON_UNESCAPED_SLASHES));
    }
}
$fixtures = [];
foreach (['fixture-signed.jpg', 'fixture-signed.png', 'fixture-signed.webp', 'fixture-unsigned.jpg'] as $f) {
    $fixtures[$f] = $own.$f;
}
foreach (['google-20250919-pixel10-npld-picnic-table.jpg', 'openai-20260826-c2pa_2x.png', 'amazon-20240925-titan-g1.png', 'adobe-20260425-lightroom-classic-church.jpg',
    'adobe-20260304-photoshop-remote-manifest.jpg', 'truepic-20230212-camera.jpg', 'c2pa-rs-ocsp.jpg', 'c2pa-rs-no_alg.jpg', 'c2pa-verifier-ai-parent-chain.png'] as $f) {
    $fixtures[$f] = $tfx.$f;
}

$cli = dirname(__DIR__, 3).'/vendor/bin/c2pa-verify';
$rows = [];
foreach ($fixtures as $name => $path) {
    foreach ($variants as $v => $anchors) {
        $settings = isset($files[$v]) ? TrustSettings::fromJson(file_get_contents($files[$v])) : null;
        $t = hrtime(true);
        $r = (new Verifier)->verify(fopen($path, 'rb'), $settings);
        $ms = (hrtime(true) - $t) / 1e6;
        $a = $r->toArray();
        $lib = ($r->hasManifest ? $r->result->state->value : 'none');
        $codes = array_values(array_unique(array_map(fn ($s) => $s['code'], array_filter($a['validation_status'] ?? [], fn ($s) => is_array($s)))));
        $cmd = escapeshellarg($cli).' '.escapeshellarg($path).(isset($files[$v]) ? ' --settings '.escapeshellarg($files[$v]) : '');
        $c = json_decode((string) shell_exec($cmd.' 2>/dev/null'), true);
        $cliState = is_array($c) ? (($c['has_manifest'] ?? false) ? $c['validation_state'] : 'none') : 'ERR';
        $cliCodes = is_array($c) ? array_values(array_unique(array_map(fn ($s) => $s['code'], $c['validation_status'] ?? []))) : [];
        $rows[] = sprintf('%-46s %-17s lib=%-8s cli=%-8s equal=%s ms=%7.1f codes=%s', $name, $v, $lib, $cliState, var_export($lib === $cliState && $codes === $cliCodes, true), $ms, implode(',', $codes));
    }
}
echo implode("\n", $rows), "\n";
foreach (['c2pa+digicert', 'custom-test-root'] as $v) {
    $json = file_get_contents($files[$v]);
    $t = hrtime(true);
    for ($i = 0; $i < 50; $i++) {
        TrustSettings::fromJson($json);
    }
    printf("fromJson %s (%d bytes): %.2f ms each\n", $v, strlen($json), (hrtime(true) - $t) / 1e6 / 50);
}
