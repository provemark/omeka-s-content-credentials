<?php

declare(strict_types=1);
require dirname(__DIR__, 4).'/vendor/autoload.php';
use ContentCredentials\Tests\Integration\Support\OmekaInstallation;

$o = OmekaInstallation::php83();
$o->logIn();
$job = (int) $o->sql("SELECT MAX(id) FROM job WHERE log LIKE '%JOBPROBE info%'");
$show = $o->get("/admin/job/{$job}")->body;
$log = $o->get("/admin/job/{$job}/log");
printf("admin show: raw <b>markup</b> %s, escaped %s; log endpoint %d, content-type text/plain %s\n",
    str_contains($show, '<b>markup</b>') ? 'PRESENT' : 'absent', str_contains($show, '&lt;b&gt;markup') ? 'present' : 'absent', $log->status, str_contains($log->body, '<b>markup</b>') ? '(raw text)' : '?');
foreach (['site_admin', 'editor', 'author'] as $role) {
    $email = "m41-{$role}-".getmypid().'@example.com';
    $id = OmekaInstallation::idFrom($o->api('POST', '/api/users', ['o:email' => $email, 'o:name' => $role, 'o:role' => $role, 'o:is_active' => true]));
    $o->sql("UPDATE user SET password_hash = '".password_hash('m41-test-only', PASSWORD_DEFAULT)."' WHERE id = {$id}");
    $u = OmekaInstallation::php83();
    $u->logIn($email, 'm41-test-only');
    $b = $u->get('/admin/job');
    $s = $u->get("/admin/job/{$job}");
    $l = $u->get("/admin/job/{$job}/log");
    printf("%s: /admin/job %d (lists job %s), show %d, log %d\n", $role, $b->status, str_contains($b->body, "/admin/job/{$job}") ? 'yes' : 'no', $s->status, $l->status);
    $o->sql("DELETE FROM user WHERE id = {$id}");
}
