<?php

declare(strict_types=1);

// Measurement only (M4.2, notes/step-20-recheck-at-scale.md), never shipped.
// Usage: php measure.php <case> ; drives RecheckProbe through its configure
// page and Omeka's job table, in the PHP 8.3 test environment.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;

$o = OmekaInstallation::php83();
$o->logIn();
$page = '/admin/module/configure?id=RecheckProbe';
$dispatch = function (array $args) use ($o, $page): int {
    preg_match('#name="module_RecheckProbe_configure_csrf" value="([^"]+)"#', $o->get($page)->body, $m);
    $o->post($page, ['args' => json_encode($args), 'module_RecheckProbe_configure_csrf' => $m[1] ?? '']);

    return (int) $o->sql('SELECT MAX(id) FROM job');
};
$status = fn (int $id): string => $o->sql("SELECT status FROM job WHERE id = {$id}");
$wait = function (int $id, int $seconds = 900) use ($status): string {
    $end = time() + $seconds;
    while (! in_array($s = $status($id), ['completed', 'error', 'stopped'], true) && time() < $end) {
        usleep(250000);
    }

    return $s;
};
$stop = function (int $id) use ($o): int {
    preg_match('#name="confirmform_csrf" value="([^"]+)"#', $o->get("/admin/job/{$id}")->body, $m);

    return $o->post("/admin/job/{$id}/stop", ['confirmform_csrf' => $m[1] ?? '', 'submit' => 'Stop'])->status;
};
$counts = fn (): string => $o->sql("SELECT CONCAT((SELECT COUNT(*) FROM media WHERE has_original = 1 AND media_type IN ('image/jpeg','image/png','image/webp')), ' checkable media, ', (SELECT COUNT(*) FROM content_credentials_verdict), ' rows, ', (SELECT COUNT(*) FROM content_credentials_verdict WHERE state = 'error'), ' error rows')");

switch ($argv[1] ?? '') {
    case 'full':
        echo 'before: ', $counts(), "\n";
        $probe = (int) $o->sql('SELECT MIN(media_id) FROM content_credentials_verdict');
        $before = $o->sql("SELECT checked_at FROM content_credentials_verdict WHERE media_id = {$probe}");
        $id = $dispatch(['label' => 'full-once', 'trust' => 'once']);
        echo 'job ', $id, ': ', $wait($id), "\n", 'after: ', $counts(), "\n";
        echo "row {$probe}: checked_at {$before} -> ", $o->sql("SELECT checked_at FROM content_credentials_verdict WHERE media_id = {$probe}"), "\n";
        break;
    case 'per_item':
        $id = $dispatch(['label' => 'per-item-500', 'trust' => 'per_item', 'limit' => 500]);
        echo 'job ', $id, ': ', $wait($id), "\n";
        $id = $dispatch(['label' => 'once-500', 'trust' => 'once', 'limit' => 500]);
        echo 'job ', $id, ': ', $wait($id), "\n";
        break;
    case 'stop_resume':
        $o->sql('DELETE FROM content_credentials_verdict');
        $id = $dispatch(['label' => 'stop-me', 'stop_every' => 10]);
        sleep(8);
        echo 'stop: HTTP ', $stop($id), ', job ', $wait($id), "\n";
        $last = (int) $o->sql('SELECT MAX(media_id) FROM content_credentials_verdict');
        echo 'after stop: ', $counts(), ", highest row {$last}\n";
        $id = $dispatch(['label' => 'resume', 'after_id' => $last]);
        echo 'resume job ', $id, ': ', $wait($id), "\n", 'after resume: ', $counts(), "\n";
        break;
    case 'concurrent':
        $a = $dispatch(['label' => 'twin-a', 'limit' => 1000]);
        $b = $dispatch(['label' => 'twin-b', 'limit' => 1000]);
        echo "jobs {$a}, {$b}: ", $wait($a), ', ', $wait($b), "\n", $counts(), "\n";
        break;
    case 'upload_during':
        $id = $dispatch(['label' => 'with-upload']);
        sleep(5);
        $t = microtime(true);
        $r = $o->uploadThroughAdminForm(VerifierCli::fixture('fixture-signed.jpg'), 'during.jpg', 'image/jpeg');
        $media = $o->mediaIdsOf(OmekaInstallation::itemIdFrom($r))[0] ?? 0;
        printf("upload during run: HTTP %d in %.2fs, media %d row %s\n", $r->status, microtime(true) - $t, $media, json_encode($o->verdictRow($media)['state'] ?? null));
        echo 'job ', $id, ': ', $wait($id), "\n";
        break;
    case 'only_unchecked':
        $o->sql('DELETE FROM content_credentials_verdict WHERE media_id % 10 = 0');
        echo 'before: ', $counts(), "\n";
        $id = $dispatch(['label' => 'only-unchecked', 'only_unchecked' => true]);
        echo 'job ', $id, ': ', $wait($id), "\n", 'after: ', $counts(), "\n";
        break;
}
