<?php

declare(strict_types=1);

// M7 review probe (step 35), never shipped. Run from the repository root.
// Against the test environment on 8088: Omeka's Stop on a live and on a killed run. Changes it.
require 'vendor/autoload.php';
use ContentCredentials\Tests\Integration\Support\OmekaInstallation as O;

$o = O::php83();
$o->logIn();
$M = 'ContentCredentials';
$page = "/admin/module/configure?id=$M";
$csrf = function ($p, $n) use ($o) {
    preg_match('#name="'.$n.'" value="([^"]+)"#', $o->get($p)->body, $m);

    return [$n => $m[1] ?? ''];
};
$cf = "module_{$M}_configure_csrf";
$latest = fn () => (int) $o->sql('SELECT MAX(id) FROM job');
$status = fn ($j) => $o->sql("SELECT status FROM job WHERE id=$j");
// a live run: how long from Stop to stopped
$o->post($page, $csrf($page, $cf) + ['content_credentials_recheck' => 'all']);
$j = $latest();
$e = time() + 30;
do {
    usleep(100000);
    $s = $status($j);
    $pid = $o->sql("SELECT pid FROM job WHERE id=$j");
} while (($s !== 'in_progress' || $pid === '') && time() < $e);
$body = $o->get("/admin/job/$j")->body;
preg_match_all('#<(form|a)[^>]*(stop)[^>]*>#i', $body, $f);
echo 'stop controls: ',implode(' ', array_map(fn ($x) => substr($x, 0, 100), $f[0])),"\n";
preg_match('#name="(confirmform_csrf|[a-z_]*csrf)" value="([^"]+)"#', $body, $m);
echo 'csrf field: ',$m[1] ?? 'none',"\n";
$t = microtime(true);
$r = $o->post("/admin/job/$j/stop", [($m[1] ?? 'x') => $m[2] ?? '']);
echo "live: stop HTTP {$r->status}, status ",$status($j),"\n";
do {
    usleep(50000);
    $s = $status($j);
} while ($s === 'stopping' && microtime(true) - $t < 60);
printf("live: %s after %.2f s\n", $s, microtime(true) - $t);
// a killed run
$o->post($page, $csrf($page, $cf) + ['content_credentials_recheck' => 'all']);
$j = $latest();
$e = time() + 30;
do {
    usleep(100000);
    $s = $status($j);
    $pid = $o->sql("SELECT pid FROM job WHERE id=$j");
} while (($s !== 'in_progress' || $pid === '') && time() < $e);
$o->rootShell("kill -9 $pid");
sleep(1);
echo 'killed: status ',$status($j),"\n";
$body = $o->get("/admin/job/$j")->body;
preg_match('#name="(confirmform_csrf|[a-z_]*csrf)" value="([^"]+)"#', $body, $m);
$r = $o->post("/admin/job/$j/stop", [($m[1] ?? 'x') => $m[2] ?? '']);
echo "killed: stop HTTP {$r->status}, status ",$status($j),"\n";
sleep(4);
echo 'killed: 4 s later ',$status($j),"\n";
// a job stuck at starting
$o->sql("UPDATE job SET status='starting' WHERE id=$j");
$body = $o->get("/admin/job/$j")->body;
echo 'starting: stop control on job page: ',preg_match('#/stop#', $body) ? 'yes' : 'no',"\n";
$o->sql("UPDATE job SET status='stopping' WHERE id=$j");
$body = $o->get("/admin/job/$j")->body;
echo 'stopping: stop control on job page: ',preg_match('#/stop#', $body) ? 'yes' : 'no',"\n";
$o->sql("UPDATE job SET status='error', ended=NOW(), pid=NULL WHERE id=$j");
echo "restored $j\n";
