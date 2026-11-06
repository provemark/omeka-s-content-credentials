<?php

declare(strict_types=1);

// M7 review probe (step 35), never shipped. Run from the repository root.
// Against the test environment on 8088: a refused form, a run whose writes fail, a killed run. Changes it.
require 'vendor/autoload.php';
use ContentCredentials\Tests\Integration\Support\OmekaInstallation as O;

$o = O::php83();
$o->logIn();
$M = 'ContentCredentials';
$page = "/admin/module/configure?id=$M";
$csrf = function () use ($o, $page, $M) {
    preg_match('#name="module_'.$M.'_configure_csrf" value="([^"]+)"#', $o->get($page)->body, $m);

    return ["module_{$M}_configure_csrf" => $m[1] ?? ''];
};
$latest = fn () => (int) $o->sql('SELECT MAX(id) FROM job');
$status = fn ($j) => $o->sql("SELECT status FROM job WHERE id=$j");
$waitDone = function ($j) use ($status) {
    $e = time() + 120;
    do {
        usleep(300000);
        $s = $status($j);
    } while (in_array($s, ['starting', 'in_progress', 'stopping'], true) && time() < $e);

    return $s;
};

echo "== D: refused form and the DigiCert box\n";
$r = $o->post($page, $csrf() + ['content_credentials_custom_settings' => '{not json']);   // box unticked: not sent
echo "status {$r->status}; messages: ",implode(' | ', array_map(fn ($x) => substr($x, 0, 70), O::messages($r->body))),"\n";
echo 'box on the re-rendered page: ',preg_match('#name="content_credentials_digicert"[^>]*checked#', $r->body) ? 'CHECKED' : 'unchecked',"\n";

echo "== A: every write fails (verdict table renamed), run over all media\n";
$total = $o->sql("SELECT COUNT(*) FROM media WHERE has_original=1 AND media_type IN ('image/jpeg','image/png','image/webp')");
echo "checkable: $total\n";
$o->sql('RENAME TABLE content_credentials_verdict TO cc_verdict_aside');
try {
    $r = $o->post($page, $csrf() + ['content_credentials_recheck' => 'all']);
    echo "start {$r->status}\n";
    $j = $latest();
    $s = $waitDone($j);
    echo "job $j status: $s\n";
    echo 'log lines not stored: ',$o->sql("SELECT (LENGTH(log)-LENGTH(REPLACE(log,'was not stored','')))/14 FROM job WHERE id=$j"),"\n";
    echo 'log tail: ',substr((string) $o->sql("SELECT RIGHT(log,120) FROM job WHERE id=$j"), -110),"\n";
} finally {
    $o->sql('RENAME TABLE cc_verdict_aside TO content_credentials_verdict');
}

echo "== B: the run's process killed\n";
$r = $o->post($page, $csrf() + ['content_credentials_recheck' => 'all']);
$j = $latest();
$e = time() + 30;
do {
    usleep(100000);
    $s = $status($j);
    $pid = $o->sql("SELECT pid FROM job WHERE id=$j");
} while (($s !== 'in_progress' || $pid === '') && time() < $e);
echo "job $j $s pid $pid\n";
$o->rootShell("kill -9 $pid");
sleep(3);
echo 'alive: ',trim($o->rootShell("kill -0 $pid 2>/dev/null && echo yes || echo no")),'; status after kill: ',$status($j),"\n";
$r = $o->post($page, $csrf() + ['content_credentials_recheck' => 'all']);
$page2 = $o->get('/admin/module');
echo 'second start, messages: ',implode(' | ', array_map(fn ($x) => substr($x, 0, 70), O::messages($page2->body))),"\n";
echo 'jobs after second start: latest id ',$latest(),"\n";
$c = $o->get($page)->body;
echo 'start buttons on page: ',substr_count($c, 'name="content_credentials_recheck"'),"\n";
$o->sql("UPDATE job SET status='error', ended=NOW(), pid=NULL WHERE id=$j");
echo "restored job $j\n";
