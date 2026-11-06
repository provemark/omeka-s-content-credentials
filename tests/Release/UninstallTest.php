<?php

declare(strict_types=1);

/*
 * SPEC-008 AC11 and AC14: uninstalling after use and during a run. In the clean
 * installation, as they compare every row and start thousands of checks.
 */

require_once __DIR__.'/Support.php';

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;

/**
 * Schema and every row but `session` and `api_key`, the helper's own, whose
 * last-used time moves with each call; without AUTO_INCREMENT values.
 */
function releaseDump(): string
{
    return (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', releaseRun([
        'docker', 'compose', 'exec', '-T', 'db', 'mariadb-dump', '-uomeka', '-pomeka', '--skip-extended-insert',
        '--skip-comments', '--skip-dump-date', '--ignore-table=omeka.session', '--ignore-table=omeka.api_key', 'omeka',
    ], releaseRoot()));
}

function releaseWaitForJob(OmekaInstallation $omeka, int $job, int $seconds = 120): string
{
    $end = time() + $seconds;
    do {
        $status = $omeka->sql("SELECT status FROM job WHERE id = {$job}");
        if (in_array($status, ['completed', 'stopped', 'error'], true)) {
            return $status;
        }
        usleep(200_000);
    } while (time() < $end);

    return $status;
}

it('AC11: uninstalling after use leaves nothing of the module', function (): void {
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    if ($omeka->moduleRow(RELEASE_MODULE) !== null) {
        $omeka->moduleAction('uninstall', RELEASE_MODULE);
    }
    $items = static fn (): string => $omeka->sql('SELECT IFNULL(GROUP_CONCAT(id), 0) FROM item');
    $known = $items();
    $before = releaseDump();

    $omeka->moduleAction('install', RELEASE_MODULE);
    releaseUpload($omeka, 'fixture-signed.jpg');
    releaseUpload($omeka, 'fixture-unsigned.png');
    expect(releaseConfigure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => '']))->toBe(302)
        ->and(releaseConfigure($omeka, ['content_credentials_recheck' => 'all']))->toBe(302)
        ->and(releaseWaitForJob($omeka, releaseLatestJob($omeka)))->toBe('completed');

    foreach (explode(',', $omeka->sql("SELECT GROUP_CONCAT(id) FROM item WHERE id NOT IN ({$known})")) as $item) {
        expect($omeka->api('DELETE', '/api/items/'.$item)->status)->toBe(200);
    }
    $omeka->moduleAction('uninstall', RELEASE_MODULE);

    expect(releaseDump())->toBe($before);
})->group('SPEC-008');

it('AC14: uninstalling during a run', function (): void {
    $omeka = releaseOmeka();
    $clones = 5000;
    $originals = '/var/www/html/files/original';
    $source = releaseUpload($omeka, 'fixture-signed.jpg');
    $storage = $omeka->sql("SELECT CONCAT(storage_id, '.', extension) FROM media WHERE id = {$source}");
    $omeka->php(['-r', 'for ($i = 1; $i <= '.$clones.'; $i++) { link("'.$originals.'/'.$storage.'", "'.$originals.'/spec008-clone-$i.jpg"); }']);
    $omeka->sql("INSERT INTO resource (owner_id, is_public, created, resource_type, title) SELECT 1, 1, NOW(), 'Omeka\\\\Entity\\\\Media', CONCAT('spec008-clone-', seq) FROM seq_1_to_{$clones}");
    $omeka->sql('INSERT INTO media (id, item_id, ingester, renderer, data, source, media_type, storage_id, extension, sha256, size, has_original, has_thumbnails, position)'
        ." SELECT r.id, m.item_id, m.ingester, m.renderer, m.data, m.source, m.media_type, r.title, m.extension, m.sha256, m.size, 1, 0, 99 FROM resource r JOIN media m ON m.id = {$source} WHERE r.title LIKE 'spec008-clone-%'");

    try {
        expect(releaseConfigure($omeka, ['content_credentials_recheck' => 'all']))->toBe(302);
        $job = releaseLatestJob($omeka);
        $rows = 'SELECT COUNT(*) FROM content_credentials_verdict v JOIN media m ON m.id = v.media_id WHERE m.storage_id LIKE \'spec008-clone-%\'';
        $end = time() + 60;
        while ((int) $omeka->sql($rows) < 100 && time() < $end) {
            usleep(50_000);
        }
        $pid = $omeka->sql("SELECT pid FROM job WHERE id = {$job}");
        expect($omeka->sql("SELECT status FROM job WHERE id = {$job}"))->toBe('in_progress')->and($pid)->not->toBe('');

        $response = $omeka->moduleAction('uninstall', RELEASE_MODULE);

        expect($response->status)->toBe(302)
            ->and(OmekaInstallation::messages($omeka->get('/admin/module')->body))->toContain('The module was successfully uninstalled');
        $end = time() + 30;
        do {
            $alive = trim($omeka->rootShell("kill -0 {$pid} 2>/dev/null && echo yes || echo no"));
            usleep(100_000);
        } while ($alive === 'yes' && time() < $end);
        expect($alive)->toBe('no')
            ->and($omeka->sql("SELECT COUNT(*) FROM job WHERE id = {$job}"))->toBe('0')
            ->and($omeka->sql("SHOW TABLES LIKE 'content_credentials%'"))->toBe('')
            ->and($omeka->get('/admin')->status)->toBe(200)
            ->and($omeka->get('/admin/job')->status)->toBe(200);

        $omeka->moduleAction('install', RELEASE_MODULE);
        sleep(2);
        expect($omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict'))->toBe('0')
            ->and($omeka->sql('SELECT COUNT(*) FROM content_credentials_evidence'))->toBe('0');
    } finally {
        $omeka->sql("DELETE FROM resource WHERE title LIKE 'spec008-clone-%'");
        $omeka->php(['-r', 'foreach (glob("'.$originals.'/spec008-clone-*") as $f) { unlink($f); }']);
    }
})->group('SPEC-008');
