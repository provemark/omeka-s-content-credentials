<?php

declare(strict_types=1);

/*
 * M7.2 probe, never shipped. From the repository root, with COMPOSE_FILE and
 * CC_TEST_PORT set to BuildProbe's environment and a built zip unpacked there:
 *   php tools/probes/UninstallProbe/scripts/measure.php before|use|after|jobs|midrun <mode>|hook
 */

require __DIR__.'/../../../../vendor/autoload.php';

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;

const MODULE = 'ContentCredentials';
const PAGE = '/admin/module/configure?id=ContentCredentials';
const CLASS_SQL = "'ContentCredentials\\\\Omeka\\\\RecheckJob'";
const ORIGINALS = '/var/www/html/files/original';

function dir_out(): string
{
    $dir = (string) getenv('M72_OUT');
    is_dir($dir) || mkdir($dir, 0777, true);

    return $dir;
}

function configure(OmekaInstallation $omeka, array $fields): int
{
    preg_match('#name="(module_ContentCredentials_configure_csrf)" value="([^"]+)"#', $omeka->get(PAGE)->body, $m);

    return $omeka->post(PAGE, $fields + [($m[1] ?? 'csrf') => $m[2] ?? ''])->status;
}

function dump(string $name): void
{
    $root = dirname(__DIR__, 4);
    $sql = shell_exec("cd {$root} && docker compose exec -T db mariadb-dump -uomeka -pomeka --skip-extended-insert --skip-comments --skip-dump-date --ignore-table=omeka.session omeka");
    file_put_contents(dir_out()."/{$name}.sql", preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) $sql));
    // Every file of the installation and /tmp, with its size; the volumes included.
    $files = shell_exec("cd {$root} && docker compose exec -T omeka sh -c 'find /var/www/html /tmp -type f -printf \"%p %s\\n\" | sort'");
    file_put_contents(dir_out()."/{$name}.files", (string) $files);
}

function upload(OmekaInstallation $omeka, string $name, string $type): int
{
    return $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm(VerifierCli::fixture($name), $name, $type)))[0];
}

function waitJob(OmekaInstallation $omeka, int $id, int $seconds = 120): string
{
    $end = time() + $seconds;
    do {
        $status = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
        if (in_array($status, ['completed', 'stopped', 'error'], true)) {
            return $status;
        }
        usleep(200_000);
    } while (time() < $end);

    return $status === '' ? '(row gone)' : $status;
}

function latest(OmekaInstallation $omeka): int
{
    return (int) $omeka->sql('SELECT IFNULL(MAX(id), 0) FROM job WHERE class = '.CLASS_SQL);
}

$what = $argv[1] ?? '';
$omeka = OmekaInstallation::php83();
$omeka->logIn();

if ($what === 'before') {
    $omeka->api('GET', '/api/items?per_page=1'); // makes the API key the helper keeps
    dump('before');
    exit;
}

if ($what === 'use') {
    $omeka->moduleAction('install', MODULE);
    echo 'row: ', json_encode($omeka->moduleRow(MODULE)), "\n";
    $a = upload($omeka, 'fixture-signed.jpg', 'image/jpeg');
    $b = upload($omeka, 'google-20250919-pixel10-npld-picnic-table.jpg', 'image/jpeg');
    $c = upload($omeka, 'fixture-unsigned.png', 'image/png');
    echo "media {$a} {$b} {$c}\n";
    echo 'settings saved: ', configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => '']), "\n";
    echo 'recheck: ', configure($omeka, ['content_credentials_recheck' => 'all']), "\n";
    $job = latest($omeka);
    echo "job {$job}: ", waitJob($omeka, $job), "\n";
    echo 'evidence page: ', $omeka->get("/admin/content-credentials/evidence/{$a}")->status, "\n";
    echo 'settings: ', str_replace("\n", ' | ', $omeka->sql("SELECT id FROM setting WHERE id LIKE 'content_credentials%'")), "\n";
    echo 'rows: verdict ', $omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict'), ' evidence ', $omeka->sql('SELECT COUNT(*) FROM content_credentials_evidence'), "\n";
    dump('used');
    $omeka->moduleAction('uninstall', MODULE);
    echo 'module row after uninstall: ', json_encode($omeka->moduleRow(MODULE)), "\n";
    dump('after-uninstall');
    exit;
}

if ($what === 'after') {
    // Omeka's own items go through its API; what is left is what the module left.
    foreach (array_filter(explode("\n", $omeka->sql('SELECT id FROM item'))) as $item) {
        echo "delete item {$item}: ", $omeka->api('DELETE', '/api/items/'.$item)->status, "\n";
    }
    dump('after-items');
    exit;
}

if ($what === 'jobs') {
    $row = $omeka->sql('SELECT id, owner_id, status, class, args, LENGTH(log), started, ended FROM job');
    echo "job rows:\n{$row}\n";
    $id = latest($omeka);
    echo "log:\n", $omeka->sql("SELECT log FROM job WHERE id = {$id}"), "\n";
    echo 'tables referring to job: [', $omeka->sql("SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = 'omeka' AND REFERENCED_TABLE_NAME = 'job'"), "]\n";
    foreach (['/admin/job', "/admin/job/{$id}", "/admin/job/{$id}/log", "/api/jobs/{$id}"] as $path) {
        $response = str_starts_with($path, '/api') ? $omeka->api('GET', $path) : $omeka->get($path);
        echo "{$path}: {$response->status}", str_contains($response->body, 'RecheckJob') ? ' names RecheckJob' : '', "\n";
    }
    echo 'delete rows: ', $omeka->sql('DELETE FROM job WHERE class = '.CLASS_SQL.'; SELECT ROW_COUNT()'), "\n";
    foreach (['/admin/job', "/admin/job/{$id}", "/api/jobs/{$id}"] as $path) {
        $response = str_starts_with($path, '/api') ? $omeka->api('GET', $path) : $omeka->get($path);
        echo "after delete {$path}: {$response->status}\n";
    }
    exit;
}

if ($what === 'midrun') {
    $mode = $argv[2] ?? 'plain';
    $n = 8000;
    if ($omeka->moduleRow(MODULE) === null) {
        $omeka->moduleAction('install', MODULE);
    }
    if ((int) $omeka->sql("SELECT COUNT(*) FROM media WHERE storage_id LIKE 'm72-clone-%'") === 0) {
        $source = upload($omeka, 'fixture-signed.jpg', 'image/jpeg');
        $storage = $omeka->sql("SELECT CONCAT(storage_id, '.', extension) FROM media WHERE id = {$source}");
        $omeka->php(['-r', 'for ($i = 1; $i <= '.$n.'; $i++) { link("'.ORIGINALS.'/'.$storage.'", "'.ORIGINALS.'/m72-clone-$i.jpg"); }']);
        $omeka->sql("INSERT INTO resource (owner_id, is_public, created, resource_type, title) SELECT 1, 1, NOW(), 'Omeka\\\\Entity\\\\Media', CONCAT('m72-clone-', seq) FROM seq_1_to_{$n}");
        $omeka->sql('INSERT INTO media (id, item_id, ingester, renderer, data, source, media_type, storage_id, extension, sha256, size, has_original, has_thumbnails, position)'
            ." SELECT r.id, m.item_id, m.ingester, m.renderer, m.data, m.source, m.media_type, r.title, m.extension, m.sha256, m.size, 1, 0, 99 FROM resource r JOIN media m ON m.id = {$source} WHERE r.title LIKE 'm72-clone-%'");
    }
    echo 'recheck: ', configure($omeka, ['content_credentials_recheck' => 'all']), "\n";
    $job = latest($omeka);
    $end = microtime(true) + 60;
    while ((int) $omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict') < 300 && microtime(true) < $end) {
        usleep(50_000);
    }
    echo "job {$job} status while running: ", $omeka->sql("SELECT status FROM job WHERE id = {$job}"), ' rows ', $omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict'), "\n";
    $pid = $omeka->sql("SELECT pid FROM job WHERE id = {$job}");
    $start = microtime(true);
    $response = $omeka->moduleAction('uninstall', MODULE);
    printf("uninstall: HTTP %d in %.1f s, row %s\n", $response->status, microtime(true) - $start, json_encode($omeka->moduleRow(MODULE)));
    if ($mode === 'delete' || $mode === 'deletereinstall') {
        echo 'job rows deleted: ', $omeka->sql('DELETE FROM job WHERE class = '.CLASS_SQL.'; SELECT ROW_COUNT()'), "\n";
    }
    if ($mode === 'reinstall' || $mode === 'deletereinstall') {
        $omeka->moduleAction('install', MODULE);
        echo 'reinstalled: ', json_encode($omeka->moduleRow(MODULE)), "\n";
    }
    do {
        $alive = trim($omeka->rootShell("kill -0 {$pid} 2>/dev/null && echo yes || echo no"));
        usleep(100_000);
    } while ($alive === 'yes' && microtime(true) - $start < 90);
    printf("process %s gone after %.1f s (alive: %s); job status: [%s], ended: [%s]\n", $pid, microtime(true) - $start, $alive, $omeka->sql("SELECT status FROM job WHERE id = {$job}"), $omeka->sql("SELECT ended FROM job WHERE id = {$job}"));
    $log = $omeka->sql("SELECT log FROM job WHERE id = {$job}");
    $lines = explode("\n", $log);
    echo 'log: ', count($lines), ' lines, "not stored" ', substr_count($log, 'not stored'), "\n  first: ", $lines[0], "\n  last: ", implode("\n  last: ", array_slice($lines, -2)), "\n";
    echo 'tables: [', str_replace("\n", ' ', $omeka->sql("SHOW TABLES LIKE 'content_credentials%'")), "]\n";
    if ($mode === 'reinstall' || $mode === 'deletereinstall') {
        echo 'rows in the new table: ', $omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict'), ', states: ', str_replace("\n", ' | ', $omeka->sql('SELECT state, reason, COUNT(*) FROM content_credentials_verdict GROUP BY state, reason')), '; evidence ', $omeka->sql('SELECT COUNT(*) FROM content_credentials_evidence'), "\n";
    }
    echo 'application.log bytes: ', trim($omeka->rootShell('wc -c < /var/www/html/logs/application.log')), "\n";
    echo 'admin: ', $omeka->get('/admin')->status, ' modules: ', $omeka->get('/admin/module')->status, "\n";
    exit;
}

if ($what === 'hook') {
    $omeka->moduleRow(MODULE) === null && $omeka->moduleAction('install', MODULE);
    $omeka->moduleAction('install', 'UninstallProbe');
    $show = static function (string $id) use ($omeka): void {
        $page = $omeka->get("/admin/module/uninstall-confirm?id={$id}");
        preg_match_all('/PROBE-DETAILS:\w+/', $page->body, $m);
        $form = strpos($page->body, '</form>');
        $marker = strpos($page->body, 'PROBE-DETAILS');
        $description = str_contains($page->body, 'PROBE-DESCRIPTION') ? 'yes' : 'no';
        echo "  confirm {$id}: HTTP {$page->status}, ".strlen($page->body).' bytes, layout: '.(str_contains($page->body, '<html') ? 'yes' : 'no').', markers '.json_encode($m[0]).', marker after form: '.($marker !== false && $marker > $form ? 'yes' : 'no').", probe description: {$description}\n";
    };
    echo "both active\n";
    $show(MODULE);
    $show('UninstallProbe');
    $browse = $omeka->get('/admin/module');
    preg_match_all('/PROBE-DETAILS:\w+/', $browse->body, $m);
    echo '  module list: markers ', count($m[0]), ', description shown: ', str_contains($browse->body, 'PROBE-DESCRIPTION') ? 'yes' : 'no', "\n";
    echo "the listener's module deactivated\n";
    $omeka->moduleAction('deactivate', 'UninstallProbe');
    $show(MODULE);
    $show('UninstallProbe');
    $browse = $omeka->get('/admin/module');
    echo '  module list: description shown: ', str_contains($browse->body, 'PROBE-DESCRIPTION') ? 'yes' : 'no', "\n";
    $omeka->moduleAction('uninstall', 'UninstallProbe');
    echo 'probe row: ', json_encode($omeka->moduleRow('UninstallProbe')), "\n";
    exit;
}
