<?php

declare(strict_types=1);
// Measurement only (M3.2, notes/step-16-trust-lists.md), never shipped.
// Needs a checkout of provemark/tracefern-image-check next to this
// repository (../C2PA_Verifier_WP) for its trust/ files and fixtures.
// Usage: php in-omeka.php <output directory>
require '/var/www/html/modules/ContentCredentials/src/Omeka/VerifierLoader.php';
VerifierLoader::register('/var/www/html/modules/ContentCredentials/vendor/provemark/c2pa-verifier/src');
use ContentCredentials\Omeka\VerifierLoader;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

foreach (['settings-c2pa+digicert.json', 'settings-c2pa.json'] as $s) {
    $t = hrtime(true);
    $settings = TrustSettings::fromJson(file_get_contents('/tmp/m32/'.$s));
    $build = (hrtime(true) - $t) / 1e6;
    foreach (['google-20250919-pixel10-npld-picnic-table.jpg', 'amazon-20240925-titan-g1.png', 'adobe-20260304-photoshop-remote-manifest.jpg'] as $f) {
        $t = hrtime(true);
        $r = (new Verifier)->verify(fopen('/tmp/m32/'.$f, 'rb'), $settings);
        printf("%s %s: %s, remote=%s, build %.1f ms, verify %.1f ms\n", $s, $f, $r->hasManifest ? $r->result->state->value : 'none', json_encode($r->toArray()['remote_manifest'] ?? null), $build, (hrtime(true) - $t) / 1e6);
    }
}
printf("peak memory %.1f MB; classes from outside the verifier namespace: %s\n", memory_get_peak_usage(true) / 1048576,
    json_encode(array_values(array_filter(get_declared_classes(), fn ($c) => str_starts_with($c, 'Provemark') === false && str_contains($c, '\\') && ! str_starts_with($c, 'ContentCredentials')))));
