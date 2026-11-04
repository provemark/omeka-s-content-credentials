<?php

declare(strict_types=1);
// Measurement only (M3.2, notes/step-16-trust-lists.md), never shipped.
// Needs a checkout of provemark/tracefern-image-check next to this
// repository (../C2PA_Verifier_WP) for its trust/ files and fixtures.
// Usage: php expiry.php <output directory>
require '/Users/maurice/Documenten/omeka-s-content-credentials/vendor/autoload.php';
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

$S = $argv[1];
$fx = '/Users/maurice/Documenten/C2PA_Verifier_WP/tests/Fixtures/';
$set = TrustSettings::fromJson(file_get_contents("$S/settings-c2pa+digicert.json"));
$c2pa = TrustSettings::fromJson(file_get_contents("$S/settings-c2pa.json"));
foreach (['google-20250919-pixel10-npld-picnic-table.jpg' => $c2pa, 'openai-20260826-c2pa_2x.png' => $c2pa, 'amazon-20240925-titan-g1.png' => $set, 'adobe-20260425-lightroom-classic-church.jpg' => $set, 'c2pa-rs-ocsp.jpg' => $set] as $f => $s) {
    $line = $f.':';
    foreach (['2026-11-04', '2027-06-01', '2028-01-01', '2030-01-01', '2035-01-01'] as $d) {
        $r = (new Verifier)->verify(fopen($fx.$f, 'rb'), $s, new DateTimeImmutable($d.'T00:00:00Z'));
        $line .= " $d=".$r->result->state->value;
    }
    echo $line, "\n";
}
