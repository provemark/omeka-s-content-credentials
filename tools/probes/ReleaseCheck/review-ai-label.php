<?php

declare(strict_types=1);

// M7 review probe (step 35), never shipped. Run from the repository root.
// AiStatement::read() on actions assertions with an instance label.
require 'vendor/autoload.php';
use ContentCredentials\Check\AiStatement;

$t = 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';
foreach (['c2pa.actions.v2', 'c2pa.actions.v2__1', 'c2pa.actions__1'] as $l) {
    $r = ['validation_state' => 'Valid', 'active_manifest' => 'm', 'manifests' => ['m' => ['assertions' => [['label' => $l, 'data' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => $t]]]]]]]];
    echo $l,' => ',var_export(AiStatement::read($r), true),"\n";
}
