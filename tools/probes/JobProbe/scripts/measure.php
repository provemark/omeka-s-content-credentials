<?php

declare(strict_types=1);
// M4.1 measurement (notes/step-18-jobs.md), never shipped. Usage: php measure.php [all|cli|sync|badpath]
require dirname(__DIR__, 4).'/vendor/autoload.php';
use ContentCredentials\Tests\Integration\Support\OmekaInstallation;

$o = OmekaInstallation::php83();
$o->logIn();
$page = '/admin/module/configure?id=JobProbe';
$dispatch = function (string $strategy, array $args) use ($o, $page): array {
    preg_match('#name="module_JobProbe_configure_csrf" value="([^"]+)"#', $o->get($page)->body, $m);
    $t = microtime(true);
    $r = $o->post($page, ['strategy' => $strategy, 'args' => json_encode($args), 'module_JobProbe_configure_csrf' => $m[1] ?? '']);
    $id = (int) $o->sql('SELECT MAX(id) FROM job');

    return [$id, $r->status, microtime(true) - $t];
};
$row = fn (int $id): string => $o->sql("SELECT CONCAT_WS(' | ', status, IFNULL(pid,'-'), IFNULL(started,'-'), IFNULL(ended,'-'), LENGTH(IFNULL(log,''))) FROM job WHERE id = {$id}");
$watch = function (int $id, float $seconds) use ($row): array {
    $seen = [];
    $end = microtime(true) + $seconds;
    do {
        $s = explode(' | ', $row($id))[0];
        if (end($seen) !== $s) {
            $seen[] = $s;
        } usleep(100000);
    } while (microtime(true) < $end && ! in_array($s, ['completed', 'error', 'stopped'], true));

    return $seen;
};
$case = $argv[1] ?? 'all';
if (in_array($case, ['all', 'cli'], true)) {
    [$id, $http, $t] = $dispatch('cli', ['loops' => 5, 'sleep_ms' => 200]);
    printf("A PhpCli loops: http=%d request=%.2fs statuses=%s final=[%s]\n", $http, $t, implode('>', $watch($id, 15)), $row($id));
    [$id] = $dispatch('cli', ['throw' => true]);
    $watch($id, 10);
    printf("B PhpCli throw: [%s]\n  log: %s\n", $row($id), str_replace("\n", ' / ', substr($o->sql("SELECT log FROM job WHERE id={$id}"), 0, 300)));
    [$id] = $dispatch('cli', ['fatal' => true]);
    $watch($id, 10);
    printf("C PhpCli fatal: [%s]\n  log: %s\n", $row($id), str_replace("\n", ' / ', substr($o->sql("SELECT log FROM job WHERE id={$id}"), 0, 300)));
    [$id] = $dispatch('cli', ['loops' => 300, 'sleep_ms' => 100]);
    usleep(1500000);
    $show = $o->get("/admin/job/{$id}");
    preg_match('#name="confirmform_csrf" value="([^"]+)"#', $show->body, $m);
    $stop = $o->post("/admin/job/{$id}/stop", ['confirmform_csrf' => $m[1] ?? '', 'submit' => 'Stop']);
    printf("D stop: show=%d csrf=%s stop_http=%d messages=%s statuses=%s final=[%s]\n", $show->status, isset($m[1]) ? 'found' : 'MISSING', $stop->status, json_encode(OmekaInstallation::messages($o->get('/admin/job')->body)), implode('>', $watch($id, 15)), $row($id));
    foreach ([false, true] as $clear) {
        [$id] = $dispatch('cli', ['media' => 3, 'clear' => $clear]);
        $watch($id, 120);
        printf("F media clear=%s: [%s]\n", $clear ? 'yes' : 'no', $row($id));
    }
}
if (in_array($case, ['all', 'sync'], true)) {
    [$id, $http, $t] = $dispatch('sync', ['loops' => 5, 'sleep_ms' => 200]);
    printf("E sync loops: http=%d request=%.2fs final=[%s]\n", $http, $t, $row($id));
    [$id, $http, $t] = $dispatch('sync', ['throw' => true]);
    printf("E sync throw: http=%d request=%.2fs final=[%s] messages=%s\n", $http, $t, $row($id), json_encode(OmekaInstallation::messages($o->get('/admin/module')->body)));
}
if ($case === 'badpath') {
    [$id, $http, $t] = $dispatch('cli', ['loops' => 1]);
    usleep(500000);
    printf("G bad PHP path: http=%d final=[%s]\n  log: %s\n", $http, $row($id), str_replace("\n", ' / ', substr($o->sql("SELECT log FROM job WHERE id={$id}"), 0, 300)));
}
