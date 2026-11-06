<?php

declare(strict_types=1);

// M7.6 probe, never shipped. From the repository root: php verdicts.php <folder with the upstream lists>.
// Every fixture's verdict with the bundled lists and with the upstream ones.
require 'vendor/autoload.php';
use ContentCredentials\Trust\TrustChoice;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

$now = TrustChoice::bundledJson(true, 'trust');
$d = json_decode($now, true);
$hit = 0;
array_walk_recursive($d, function (&$v) use (&$hit, $argv) {
    if (! is_string($v)) {
        return;
    } foreach (['C2PA-TRUST-LIST.pem', 'C2PA-TSA-TRUST-LIST.pem'] as $f) {
        $old = file_get_contents("trust/$f");
        if (str_contains($v, $old)) {
            $v = str_replace($old, file_get_contents("$argv[1]/$f"), $v);
            $hit++;
        }
    }
});
$new = json_encode($d);
echo "entries replaced: $hit; certificates in the new settings: ",substr_count($new, 'BEGIN CERTIFICATE'),' against ',substr_count($now, 'BEGIN CERTIFICATE'),"\n";
echo 'settings changed: ',$now === $new ? 'NO' : 'yes',"\n";
$A = TrustSettings::fromJson($now);
$B = TrustSettings::fromJson($new);
$diff = 0;
$n = 0;
foreach (glob('tests/Fixtures/c2pa/*.{jpg,png,webp}', GLOB_BRACE) as $p) {
    $o = [];
    foreach ([$A, $B] as $t) {
        $s = fopen($p, 'rb');
        $r = (new Verifier)->verify($s, $t)->toArray();
        fclose($s);
        $o[] = $r['validation_state'] ?? '?';
    } $n++;
    if ($o[0] !== $o[1]) {
        $diff++;
    } printf("%-48s %-8s %-8s\n", basename($p), $o[0], $o[1]);
}
echo "$n fixtures, $diff differ\n";
