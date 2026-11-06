<?php

declare(strict_types=1);

// M7.6 probe, never shipped. From the repository root: php lists.php <folder with the upstream lists>.
// The certificates that differ between trust/ and that folder.

/** @return array<string, list<string>> fingerprint => common name, organisation, end of validity */
function certificates(string $file): array
{
    preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', (string) file_get_contents($file), $m);
    $out = [];
    foreach ($m[0] as $pem) {
        $x = openssl_x509_parse($pem);
        $out[(string) openssl_x509_fingerprint($pem, 'sha256')] = [$x['subject']['CN'] ?? '?', $x['subject']['O'] ?? '?', date('Y-m-d', $x['validTo_time_t'])];
    }

    return $out;
}

foreach (['C2PA-TRUST-LIST.pem', 'C2PA-TSA-TRUST-LIST.pem'] as $file) {
    $bundled = certificates('trust/'.$file);
    $upstream = certificates($argv[1].'/'.$file);
    echo $file, ': bundled ', count($bundled), ', upstream ', count($upstream), "\n";
    foreach (array_diff_key($upstream, $bundled) as $fingerprint => $certificate) {
        echo '  added ', substr($fingerprint, 0, 16), ' ', implode(' | ', $certificate), "\n";
    }
    foreach (array_diff_key($bundled, $upstream) as $fingerprint => $certificate) {
        echo '  removed ', substr($fingerprint, 0, 16), ' ', implode(' | ', $certificate), "\n";
    }
    echo '  same: ', count(array_intersect_key($bundled, $upstream)), "\n";
}
