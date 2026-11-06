<?php

declare(strict_types=1);

// M7.12 probe, never shipped. Run from the repository root, against the test environment on 8088,
// which it changes. What a run's job row and log say when the run fails, in three ways.

require 'vendor/autoload.php';

use ContentCredentials\Tests\Integration\Support\OmekaInstallation as O;

$o = O::php83();
$o->logIn();
$page = '/admin/module/configure?id=ContentCredentials';
$csrf = function () use ($o, $page): array {
    preg_match('#name="module_ContentCredentials_configure_csrf" value="([^"]+)"#', $o->get($page)->body, $m);

    return ['module_ContentCredentials_configure_csrf' => $m[1] ?? ''];
};
$latest = fn (): int => (int) $o->sql('SELECT MAX(id) FROM job');
$status = fn (int $j): string => $o->sql("SELECT status FROM job WHERE id = {$j}");
$wait = function (int $j, int $seconds = 90) use ($status): string {
    $end = time() + $seconds;
    do {
        usleep(200_000);
        $s = $status($j);
    } while (in_array($s, ['starting', 'in_progress', 'stopping'], true) && time() < $end);

    return $s;
};
$report = function (string $name, int $j, string $s) use ($o): void {
    $log = $o->sql("SELECT log FROM job WHERE id = {$j}");
    $lines = array_values(array_filter(explode("\n", $log), static fn (string $l): bool => $l !== ''));
    echo "== {$name}: status {$s}, ", count($lines), ' log lines, ', strlen($log), " bytes\n";
    foreach (array_slice($lines, -3) as $line) {
        echo '   ', substr($line, 0, 120), "\n";
    }
    echo '   path: ', str_contains($log, '/var/www') ? 'YES' : 'no',
    '; SQLSTATE: ', str_contains($log, 'SQLSTATE') ? 'YES' : 'no',
    '; "Exception": ', str_contains($log, 'Exception') ? 'YES' : 'no',
    '; "#0": ', str_contains($log, '#0') ? 'YES' : 'no', "\n";
};

// 1. The run itself throws: "without a result" joins the renamed table in its first query.
$o->sql('RENAME TABLE content_credentials_verdict TO cc_verdict_aside');
try {
    $o->post($page, $csrf() + ['content_credentials_recheck' => 'without_result']);
    $j = $latest();
    $report('the run throws', $j, $wait($j));

    // 2. Every write fails: "all" does not join it.
    $o->post($page, $csrf() + ['content_credentials_recheck' => 'all']);
    $j = $latest();
    $report('no write is stored', $j, $wait($j));
} finally {
    $o->sql('RENAME TABLE cc_verdict_aside TO content_credentials_verdict');
}

// 3. The database connection of the run is killed.
$o->post($page, $csrf() + ['content_credentials_recheck' => 'all']);
$j = $latest();
$end = time() + 30;
do {
    usleep(100_000);
    $pid = $o->sql("SELECT pid FROM job WHERE id = {$j}");
} while ($pid === '' && time() < $end);
sleep(1);
$ids = $o->sql("SELECT GROUP_CONCAT(id) FROM information_schema.processlist WHERE db = 'omeka' AND command = 'Sleep' AND id <> CONNECTION_ID()");
echo '   killed connections: ', $ids === '' ? 'none' : $ids, "\n";
foreach (array_filter(explode(',', $ids)) as $id) {
    $o->sql("KILL {$id}");
}
$end = time() + 240;
do {
    usleep(500_000);
    $alive = trim($o->rootShell("kill -0 {$pid} 2>/dev/null && echo yes || echo no"));
} while ($alive === 'yes' && time() < $end);
$s = $status($j);
$report('the connection is killed', $j, $s);
echo '   process alive: ', trim($o->rootShell("kill -0 {$pid} 2>/dev/null && echo yes || echo no")), "\n";
$o->sql("UPDATE job SET status = 'error', ended = NOW(), pid = NULL WHERE id = {$j} AND status IN ('in_progress', 'stopping', 'starting')");

// 4. A stopped run stays stopped.
$o->post($page, $csrf() + ['content_credentials_recheck' => 'all']);
$j = $latest();
$end = time() + 30;
do {
    usleep(100_000);
} while ($status($j) !== 'in_progress' && time() < $end);
preg_match('#name="confirmform_csrf" value="([^"]+)"#', $o->get("/admin/job/{$j}")->body, $m);
$o->post("/admin/job/{$j}/stop", ['confirmform_csrf' => $m[1] ?? '', 'submit' => 'Stop']);
$report('stopped', $j, $wait($j));

echo 'application.log, "Exception": ', trim($o->rootShell('grep -c Exception /var/www/html/logs/application.log || true')), "\n";
