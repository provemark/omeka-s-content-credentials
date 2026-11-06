<?php

declare(strict_types=1);

// M7 review probe (step 35), never shipped. Run from the repository root.
// Text::visible() on invisible characters, and TrustChoice::build() on a damaged list.
require 'vendor/autoload.php';
use ContentCredentials\Display\Text;
use ContentCredentials\Trust\TrustChoice;

foreach (['U+061C' => "\u{061C}", 'U+200B' => "\u{200B}", 'U+200D' => "\u{200D}", 'U+2060' => "\u{2060}", 'U+FEFF' => "\u{FEFF}", 'U+202E (listed, control)' => "\u{202E}"] as $n => $c) {
    echo $n,': ',Text::visible("a{$c}b") === "a{$c}b" ? 'PASSES THROUGH' : 'replaced',"\n";
}
$d = sys_get_temp_dir().'/cc'.uniqid();
mkdir($d);
foreach (['C2PA-TRUST-LIST.pem', 'C2PA-TSA-TRUST-LIST.pem', 'DigiCertTrustedRootG4.crt.pem'] as $f) {
    file_put_contents("$d/$f", "\xFFbad");
}
try {
    $b = TrustChoice::build(null, true, $d);
    echo 'build returned, problem: ',$b->problem,"\n";
} catch (Throwable $e) {
    echo 'build THREW ',get_class($e),': ',$e->getMessage(),"\n";
}
array_map('unlink', glob("$d/*"));
rmdir($d);
