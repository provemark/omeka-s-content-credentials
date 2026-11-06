<?php

declare(strict_types=1);

/*
 * SPEC-005: checking existing media again, in the pinned Omeka S of
 * tools/env/ (PHP 8.3), with the job dispatched through Omeka's default
 * strategy (PhpCli). The expected row is the one an upload of the same
 * file gets (SPEC-002), whose oracle is vendor/bin/c2pa-verify.
 *
 * Runs over many media use clones: media rows made with SQL whose
 * originals are hard links to one uploaded fixture, removed afterwards.
 */

use ContentCredentials\Tests\Integration\Support\HttpResponse;
use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;

const SPEC005_PAGE = '/admin/module/configure?id=ContentCredentials';
const SPEC005_CSRF = 'module_ContentCredentials_configure_csrf';
const SPEC005_START = 'content_credentials_recheck';
const SPEC005_END = 'content_credentials_end_run';
const SPEC005_CLASS = 'ContentCredentials\\\\Omeka\\\\RecheckJob'; // as an SQL literal
const SPEC005_ORIGINALS = '/var/www/html/files/original';
const SPEC005_CHECKABLE = "has_original = 1 AND media_type IN ('image/jpeg', 'image/png', 'image/webp')";

/**
 * The PHP 8.3 installation, logged in, the module active with the table of
 * SPEC-002 amendment 1, no trust setting stored, no clones, and no run of
 * this module going on.
 */
function spec005Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow('ContentCredentials');
    if ($row !== null && $omeka->sql("SHOW COLUMNS FROM content_credentials_verdict LIKE 'trust'") === '') {
        $omeka->moduleAction('uninstall', 'ContentCredentials');
        $row = null;
    }
    if ($row === null) {
        $omeka->moduleAction('install', 'ContentCredentials');
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', 'ContentCredentials');
    }
    $omeka->sql("DELETE FROM setting WHERE id LIKE 'content\\_credentials\\_%'");
    $omeka->sql("UPDATE job SET status = 'stopping' WHERE class = '".SPEC005_CLASS."' AND status IN ('starting', 'in_progress')");
    $running = $omeka->sql("SELECT IFNULL(MAX(id), 0) FROM job WHERE class = '".SPEC005_CLASS."' AND status = 'stopping'");
    if ($running !== '0') {
        spec005Wait($omeka, (int) $running);
        $omeka->sql("UPDATE job SET status = 'stopped' WHERE class = '".SPEC005_CLASS."' AND status = 'stopping'");
    }
    spec005RemoveClones($omeka);

    return $omeka;
}

/**
 * Submits the configure page with its CSRF token.
 *
 * @param  array<string, string>  $fields
 */
function spec005Configure(OmekaInstallation $omeka, array $fields): HttpResponse
{
    preg_match('#name="'.SPEC005_CSRF.'" value="([^"]+)"#', $omeka->get(SPEC005_PAGE)->body, $m);

    return $omeka->post(SPEC005_PAGE, $fields + [SPEC005_CSRF => $m[1] ?? '']);
}

/** The newest job of this module's run, or 0. */
function spec005Latest(OmekaInstallation $omeka): int
{
    return (int) $omeka->sql("SELECT IFNULL(MAX(id), 0) FROM job WHERE class = '".SPEC005_CLASS."'");
}

/** Starts a run through the configure page; returns its job id. */
function spec005Start(OmekaInstallation $omeka, string $media = 'all'): int
{
    $before = spec005Latest($omeka);
    $response = spec005Configure($omeka, [SPEC005_START => $media]);
    expect($response->status)->toBe(302);
    $id = spec005Latest($omeka);
    expect($id)->toBeGreaterThan($before);

    return $id;
}

/** Waits for a job to end; returns its status. */
function spec005Wait(OmekaInstallation $omeka, int $id, int $seconds = 300): string
{
    $end = time() + $seconds;
    do {
        $status = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
        if (in_array($status, ['completed', 'stopped', 'error'], true)) {
            return $status;
        }
        usleep(200_000);
    } while (time() < $end);

    return $status;
}

function spec005Run(OmekaInstallation $omeka, string $media = 'all'): int
{
    $id = spec005Start($omeka, $media);
    expect(spec005Wait($omeka, $id))->toBe('completed');

    return $id;
}

/** Presses Stop on Omeka's job page. */
function spec005Stop(OmekaInstallation $omeka, int $id): HttpResponse
{
    preg_match('#name="confirmform_csrf" value="([^"]+)"#', $omeka->get("/admin/job/{$id}")->body, $m);

    return $omeka->post("/admin/job/{$id}/stop", ['confirmform_csrf' => $m[1] ?? '', 'submit' => 'Stop']);
}

function spec005Upload(OmekaInstallation $omeka, string $fixture): int
{
    $type = str_ends_with($fixture, '.png') ? 'image/png' : (str_ends_with($fixture, '.webp') ? 'image/webp' : 'image/jpeg');
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm(VerifierCli::fixture($fixture), $fixture, $type)));

    return $media[0] ?? throw new RuntimeException("No media for {$fixture}");
}

/** Every verdict row without `checked_at`, one line per media item. */
function spec005Rows(OmekaInstallation $omeka, string $where = '1 = 1'): string
{
    return $omeka->sql('SELECT media_id, state, IFNULL(format, "-"), IFNULL(signer_issuer, "-"), IFNULL(signer_common_name, "-"), IFNULL(signed_at, "-"), codes, IFNULL(remote_manifest_url, "-"), IFNULL(reason, "-"), IFNULL(verifier, "-"), IFNULL(trust, "-"), IFNULL(checked_sha256, "-") FROM content_credentials_verdict WHERE '.$where.' ORDER BY media_id');
}

/** One media item's row without `checked_at`. */
function spec005Row(OmekaInstallation $omeka, int $media): string
{
    return spec005Rows($omeka, "media_id = {$media}");
}

/** Makes $n clones of a freshly uploaded signed JPEG, without rows. */
function spec005Clones(OmekaInstallation $omeka, int $n): void
{
    $source = spec005Upload($omeka, 'fixture-signed.jpg');
    $storage = $omeka->sql("SELECT CONCAT(storage_id, '.', extension) FROM media WHERE id = {$source}");
    $omeka->php(['-r', 'for ($i = 1; $i <= '.$n.'; $i++) { link("'.SPEC005_ORIGINALS.'/'.$storage.'", "'.SPEC005_ORIGINALS.'/spec005-clone-$i.jpg"); }']);
    $omeka->sql("INSERT INTO resource (owner_id, is_public, created, resource_type, title) SELECT 1, 1, NOW(), 'Omeka\\\\Entity\\\\Media', CONCAT('spec005-clone-', seq) FROM seq_1_to_{$n}");
    $omeka->sql('INSERT INTO media (id, item_id, ingester, renderer, data, source, media_type, storage_id, extension, sha256, size, has_original, has_thumbnails, position)'
        ." SELECT r.id, m.item_id, m.ingester, m.renderer, m.data, m.source, m.media_type, r.title, m.extension, m.sha256, m.size, 1, 0, 99 FROM resource r JOIN media m ON m.id = {$source} WHERE r.title LIKE 'spec005-clone-%'");
}

function spec005RemoveClones(OmekaInstallation $omeka): void
{
    $omeka->sql("DELETE FROM resource WHERE title LIKE 'spec005-clone-%'");
    $omeka->php(['-r', 'foreach (glob("'.SPEC005_ORIGINALS.'/spec005-clone-*") as $f) { unlink($f); }']);
}

/** Waits until at least $rows clones have a row. */
function spec005WaitForRows(OmekaInstallation $omeka, int $rows): void
{
    $end = time() + 60;
    while ((int) $omeka->sql("SELECT COUNT(*) FROM content_credentials_verdict v JOIN media m ON m.id = v.media_id WHERE m.storage_id LIKE 'spec005-clone-%'") < $rows && time() < $end) {
        usleep(100_000);
    }
}

/** The module's section of the configure page. */
function spec005Section(string $html): string
{
    $start = strpos($html, '<div class="content-credentials-recheck">');

    return $start === false ? '' : substr($html, $start, (int) strpos($html, '<!-- /content-credentials-recheck -->', $start) - $start);
}

/**
 * A user of a role, logged in, and a function that deletes it.
 *
 * @return array{OmekaInstallation, Closure(): string}
 */
function spec005User(OmekaInstallation $omeka, string $role): array
{
    $email = "spec005-{$role}-".getmypid().'-'.random_int(0, PHP_INT_MAX).'@example.com';
    $id = OmekaInstallation::idFrom($omeka->api('POST', '/api/users', ['o:email' => $email, 'o:name' => $role, 'o:role' => $role, 'o:is_active' => true]));
    $omeka->sql("UPDATE user SET password_hash = '".password_hash('spec005-test-only', PASSWORD_DEFAULT)."' WHERE id = {$id}");
    $user = OmekaInstallation::php83();
    $user->logIn($email, 'spec005-test-only');

    return [$user, static fn () => $omeka->sql("DELETE FROM user WHERE id = {$id}")];
}

it('AC1: a run checks every checkable media item', function (): void {
    $omeka = spec005Omeka();
    $fresh = spec005Upload($omeka, 'fixture-signed.png');
    $kept = spec005Upload($omeka, 'fixture-signed.webp');
    $changed = spec005Upload($omeka, 'google-20250919-pixel10-npld-picnic-table.jpg');
    $upload = [$fresh => spec005Row($omeka, $fresh), $kept => spec005Row($omeka, $kept), $changed => spec005Row($omeka, $changed)];
    $omeka->sql("DELETE FROM content_credentials_verdict WHERE media_id = {$fresh}");
    $omeka->sql("UPDATE content_credentials_verdict SET state = 'Invalid', checked_at = '2001-01-01 00:00:00' WHERE media_id = {$changed}");
    $gif = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm(VerifierCli::fixture('auth-1-1.gif'), 'auth-1-1.gif', 'image/gif')))[0] ?? 0;
    $started = $omeka->sql('SELECT MAX(checked_at) FROM content_credentials_verdict');
    sleep(1);

    spec005Run($omeka);

    expect(spec005Row($omeka, $fresh))->toBe($upload[$fresh])
        ->and(spec005Row($omeka, $kept))->toBe($upload[$kept])
        ->and(spec005Row($omeka, $changed))->toBe($upload[$changed])
        ->and($omeka->sql('SELECT COUNT(*) FROM media WHERE '.SPEC005_CHECKABLE.' AND id NOT IN (SELECT media_id FROM content_credentials_verdict)'))->toBe('0')
        ->and($omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict v JOIN media m ON m.id = v.media_id WHERE m.'.str_replace(' AND media_type', ' AND m.media_type', SPEC005_CHECKABLE)." AND v.checked_at <= '{$started}'"))->toBe('0')
        ->and($omeka->sql("SELECT COUNT(*) FROM content_credentials_verdict v JOIN media m ON m.id = v.media_id WHERE m.id = {$gif} OR m.media_type NOT IN ('image/jpeg', 'image/png', 'image/webp')"))->toBe('0');
})->group('SPEC-005');

it('AC2: only media without a verdict', function (): void {
    $omeka = spec005Omeka();
    $with = spec005Upload($omeka, 'fixture-signed.jpg');
    $without = spec005Upload($omeka, 'fixture-unsigned.jpg');
    $omeka->sql("DELETE FROM content_credentials_verdict WHERE media_id = {$without}");
    $kept = $omeka->sql("SELECT CONCAT_WS(' ', state, checked_at) FROM content_credentials_verdict WHERE media_id = {$with}");
    $allBefore = $omeka->sql('SELECT media_id, checked_at FROM content_credentials_verdict ORDER BY media_id');

    spec005Run($omeka, 'without_result');

    expect($omeka->sql("SELECT state FROM content_credentials_verdict WHERE media_id = {$without}"))->toBe('none')
        ->and($omeka->sql("SELECT CONCAT_WS(' ', state, checked_at) FROM content_credentials_verdict WHERE media_id = {$with}"))->toBe($kept)
        ->and($omeka->sql("SELECT media_id, checked_at FROM content_credentials_verdict WHERE media_id <> {$without} ORDER BY media_id"))->toBe($allBefore)
        ->and($omeka->sql('SELECT COUNT(*) FROM media WHERE '.SPEC005_CHECKABLE.' AND id NOT IN (SELECT media_id FROM content_credentials_verdict)'))->toBe('0');
})->group('SPEC-005');

it('AC3: the trust settings of the moment the run starts', function (): void {
    $omeka = spec005Omeka();
    $media = spec005Upload($omeka, 'c2pa-rs-ocsp.jpg');
    $atUpload = $omeka->sql("SELECT CONCAT_WS(' ', state, trust) FROM content_credentials_verdict WHERE media_id = {$media}");

    $omeka->sql("INSERT INTO setting (id, value) VALUES ('content_credentials_digicert', 'false')");
    spec005Run($omeka);
    $without = $omeka->sql("SELECT CONCAT_WS(' ', state, trust) FROM content_credentials_verdict WHERE media_id = {$media}");
    $omeka->sql("DELETE FROM setting WHERE id = 'content_credentials_digicert'");
    spec005Run($omeka);
    $with = $omeka->sql("SELECT CONCAT_WS(' ', state, trust) FROM content_credentials_verdict WHERE media_id = {$media}");

    expect($atUpload)->toBe('Valid c2pa-2026-11-04+digicert')
        ->and($without)->toBe('Invalid c2pa-2026-11-04')
        ->and($with)->toBe('Valid c2pa-2026-11-04+digicert');
})->group('SPEC-005');

it('AC4: running it again changes nothing but the time', function (): void {
    $omeka = spec005Omeka();
    spec005Upload($omeka, 'fixture-signed.jpg');
    spec005Run($omeka);
    $first = spec005Rows($omeka);
    $times = $omeka->sql('SELECT MAX(checked_at) FROM content_credentials_verdict');
    sleep(1);

    spec005Run($omeka);

    expect(spec005Rows($omeka))->toBe($first)
        ->and($omeka->sql("SELECT COUNT(*) FROM content_credentials_verdict WHERE checked_at <= '{$times}'"))->toBe('0');
})->group('SPEC-005');

it('AC5: stopping a run', function (): void {
    $omeka = spec005Omeka();
    spec005Clones($omeka, 3000);
    $id = spec005Start($omeka);
    spec005WaitForRows($omeka, 100);

    $stop = spec005Stop($omeka, $id);
    $status = spec005Wait($omeka, $id);
    $clones = "media_id IN (SELECT id FROM media WHERE storage_id LIKE 'spec005-clone-%')";
    $stored = (int) $omeka->sql("SELECT COUNT(*) FROM content_credentials_verdict WHERE {$clones}");
    $incomplete = $omeka->sql("SELECT COUNT(*) FROM content_credentials_verdict WHERE {$clones} AND state <> 'Valid'");
    $page = spec005Section($omeka->get(SPEC005_PAGE)->body);
    spec005Run($omeka);
    $after = (int) $omeka->sql("SELECT COUNT(*) FROM content_credentials_verdict WHERE {$clones} AND state = 'Valid'");
    spec005RemoveClones($omeka);

    expect($stop->status)->toBe(302)
        ->and($status)->toBe('stopped')
        ->and($stored)->toBeGreaterThanOrEqual(100)->toBeLessThan(3000)
        ->and($incomplete)->toBe('0')
        ->and($page)->toContain('Latest run: Stopped')
        ->and($page)->toContain('name="'.SPEC005_START.'"')
        ->and($after)->toBe(3000);
})->group('SPEC-005');

it('AC6: an unreadable original: error, the run goes on', function (): void {
    $omeka = spec005Omeka();
    $gone = spec005Upload($omeka, 'fixture-signed.jpg');
    $other = spec005Upload($omeka, 'fixture-signed.png');
    $expected = spec005Row($omeka, $other);
    $file = $omeka->sql("SELECT CONCAT(storage_id, '.', extension) FROM media WHERE id = {$gone}");
    $omeka->php(['-r', 'unlink("'.SPEC005_ORIGINALS.'/'.$file.'");']);

    spec005Run($omeka);

    expect($omeka->sql("SELECT CONCAT_WS(' ', state, reason) FROM content_credentials_verdict WHERE media_id = {$gone}"))->toBe('error unreadable')
        ->and(spec005Row($omeka, $other))->toBe($expected);
    $omeka->api('DELETE', "/api/media/{$gone}");
})->group('SPEC-005');

it('AC7: nothing from a file or an exception in the job log', function (): void {
    $omeka = spec005Omeka();
    $gone = spec005Upload($omeka, 'fixture-signed.jpg');
    $file = $omeka->sql("SELECT CONCAT(storage_id, '.', extension) FROM media WHERE id = {$gone}");
    $omeka->php(['-r', 'unlink("'.SPEC005_ORIGINALS.'/'.$file.'");']);
    $omeka->sql("INSERT INTO setting (id, value) VALUES ('content_credentials_custom_settings', '\"not json <b>secret</b>\"')");

    $id = spec005Run($omeka);
    $log = $omeka->sql("SELECT log FROM job WHERE id = {$id}");
    $omeka->api('DELETE', "/api/media/{$gone}");
    $own = '#^\S+ INFO \(6\): (Checking \d+ media\.|Checked \d+ of \d+ media\.|Checked \d+ media: \d+ Trusted, \d+ Valid, \d+ Invalid, \d+ none, \d+ error\.)$#';
    $lines = array_values(array_filter(explode("\n", $log), static fn (string $line): bool => $line !== ''));

    expect($lines)->not->toBe([])
        ->and(array_values(array_filter($lines, static fn (string $line): bool => preg_match($own, $line) !== 1)))->toBe([])
        ->and($log)->not->toContain('/var/www')
        ->and($log)->not->toContain($file)
        ->and($log)->not->toContain('secret')
        ->and($log)->not->toContain('Exception');
})->group('SPEC-005');

it('AC8: a run that cannot start is shown as failed', function (): void {
    $omeka = spec005Omeka();
    $config = '/var/www/html/config/local.config.php';
    $before = spec005Rows($omeka);
    $omeka->rootShell("cp {$config} /tmp/spec005-local.config.php && sed -i \"s#'phpcli_path' => null#'phpcli_path' => '/nonexistent/php'#\" {$config}");
    // Apache's opcache sees a changed file only after revalidate_freq (2 s).
    sleep(3);
    try {
        $id = spec005Start($omeka);
        $status = spec005Wait($omeka, $id, 10);
        $page = spec005Section($omeka->get(SPEC005_PAGE)->body);
    } finally {
        $omeka->rootShell("cp /tmp/spec005-local.config.php {$config} && rm /tmp/spec005-local.config.php");
        sleep(3);
    }
    $rows = spec005Rows($omeka);
    $upload = spec005Upload($omeka, 'fixture-signed.jpg');

    expect($status)->toBe('error')
        ->and($page)->toContain('Latest run: Failed')
        ->and($page)->toContain('href="/admin/job/'.$id.'"')
        ->and($page)->toContain('name="'.SPEC005_START.'"')
        ->and($rows)->toBe($before)
        ->and($omeka->sql("SELECT state FROM content_credentials_verdict WHERE media_id = {$upload}"))->toBe('Valid');
})->group('SPEC-005');

it('AC9: one run at a time', function (): void {
    $omeka = spec005Omeka();
    spec005Clones($omeka, 3000);
    $id = spec005Start($omeka);
    spec005WaitForRows($omeka, 10);

    $page = spec005Section($omeka->get(SPEC005_PAGE)->body);
    $second = spec005Configure($omeka, [SPEC005_START => 'all']);
    $messages = OmekaInstallation::messages($omeka->get('/admin/module')->body);
    $latest = spec005Latest($omeka);
    spec005Stop($omeka, $id);
    spec005Wait($omeka, $id);
    spec005RemoveClones($omeka);

    expect($page)->toMatch('#Latest run: (Starting|In progress)#')
        ->and($page)->not->toContain('name="'.SPEC005_START.'"')
        ->and($second->status)->toBe(302)
        ->and($latest)->toBe($id)
        ->and($messages)->toContain('A run is already in progress; no new run was started.');
})->group('SPEC-005');

it('AC10: global administrators only', function (string $role): void {
    $omeka = spec005Omeka();
    [$user, $delete] = spec005User($omeka, $role);
    $before = spec005Latest($omeka);
    try {
        $page = $user->get(SPEC005_PAGE);
        $post = $user->post(SPEC005_PAGE, [SPEC005_START => 'all', SPEC005_CSRF => 'x']);
    } finally {
        $delete();
    }

    expect($page->status)->not->toBe(200)
        ->and($page->body)->not->toContain('name="'.SPEC005_START.'"')
        ->and($post->status)->not->toBe(302)
        ->and(spec005Latest($omeka))->toBe($before);
})->with(['site_admin', 'editor', 'author'])->group('SPEC-005');

it('AC11: starting a run does not save the trust settings', function (): void {
    $omeka = spec005Omeka();
    $omeka->sql("INSERT INTO setting (id, value) VALUES ('content_credentials_digicert', 'false')");
    $media = spec005Upload($omeka, 'c2pa-rs-ocsp.jpg');
    $settings = "SELECT GROUP_CONCAT(CONCAT(id, '=', value) ORDER BY id) FROM setting WHERE id LIKE 'content\\_credentials\\_%'";
    $before = $omeka->sql($settings);
    $omeka->sql("UPDATE content_credentials_verdict SET state = 'Trusted' WHERE media_id = {$media}");
    $latest = spec005Latest($omeka);

    // The form with its settings fields changed, sent by the start button.
    $response = spec005Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => '{"verify":{}}', SPEC005_START => 'all']);
    $id = spec005Latest($omeka);
    $status = spec005Wait($omeka, $id);

    expect($response->status)->toBe(302)
        ->and($id)->toBeGreaterThan($latest)
        ->and($status)->toBe('completed')
        ->and($before)->toBe('content_credentials_digicert=false')
        ->and($omeka->sql($settings))->toBe($before)
        ->and($omeka->sql("SELECT CONCAT_WS(' ', state, trust) FROM content_credentials_verdict WHERE media_id = {$media}"))->toBe('Invalid c2pa-2026-11-04');
})->group('SPEC-005');

it('AC12: uploads during a run', function (): void {
    $omeka = spec005Omeka();
    spec005Clones($omeka, 2000);
    $id = spec005Start($omeka);
    spec005WaitForRows($omeka, 10);

    $upload = $omeka->uploadThroughAdminForm(VerifierCli::fixture('fixture-signed.jpg'), 'fixture-signed.jpg', 'image/jpeg');
    $running = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($upload))[0] ?? 0;
    $status = spec005Wait($omeka, $id);
    spec005RemoveClones($omeka);

    expect($upload->status)->toBe(302)
        ->and($running)->toBe('in_progress')
        ->and($omeka->sql("SELECT state FROM content_credentials_verdict WHERE media_id = {$media}"))->toBe('Valid')
        ->and($status)->toBe('completed');
})->group('SPEC-005');

/** Everything AC14 must leave alone: verdicts, evidence and settings. */
function spec005Untouched(OmekaInstallation $omeka): string
{
    return spec005Rows($omeka)
        .'|'.$omeka->sql('SELECT COUNT(*), IFNULL(MAX(id), 0) FROM content_credentials_evidence')
        .'|'.$omeka->sql("SELECT COUNT(*) FROM setting WHERE id LIKE 'content\\_credentials\\_%'");
}

/** A finished run of this module, its row set to $status. */
function spec005RunIn(OmekaInstallation $omeka, string $status): int
{
    $id = spec005Run($omeka);
    $omeka->sql("UPDATE job SET status = '{$status}', ended = NULL WHERE id = {$id}");

    return $id;
}

it('AC14: a run whose process has gone can be marked as ended', function (): void {
    $omeka = spec005Omeka();
    spec005Clones($omeka, 3000);
    $id = spec005Start($omeka);
    spec005WaitForRows($omeka, 10);
    $pid = $omeka->sql("SELECT pid FROM job WHERE id = {$id}");
    expect($pid)->not->toBe('');
    $omeka->rootShell("kill -9 {$pid}");

    try {
        $killed = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
        $whileInProgress = spec005Section($omeka->get(SPEC005_PAGE)->body);
        spec005Stop($omeka, $id);
        sleep(1);
        $stopping = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
        $whileStopping = spec005Section($omeka->get(SPEC005_PAGE)->body);
        $before = spec005Untouched($omeka);

        $response = spec005Configure($omeka, [SPEC005_END => (string) $id]);

        $after = spec005Untouched($omeka);
        $row = $omeka->sql("SELECT status, ended IS NOT NULL, pid IS NULL FROM job WHERE id = {$id}");
        $log = $omeka->sql("SELECT log FROM job WHERE id = {$id}");
        $page = spec005Section($omeka->get(SPEC005_PAGE)->body);
    } finally {
        $omeka->sql("UPDATE job SET status = 'error', ended = NOW(), pid = NULL WHERE id = {$id} AND status IN ('in_progress', 'stopping')");
    }
    $next = spec005Latest($omeka);
    spec005RemoveClones($omeka);

    expect($killed)->toBe('in_progress')
        ->and($whileInProgress)->not->toContain('name="'.SPEC005_END.'"')
        ->and($whileInProgress)->toContain('use Stop on its job page')
        ->and($stopping)->toBe('stopping')
        ->and($whileStopping)->toContain('Mark this run as ended')
        ->and($whileStopping)->toContain('no longer has a process')
        ->and($whileStopping)->toMatch('#name="'.SPEC005_END.'" value="'.$id.'"#')
        ->and($response->status)->toBe(302)
        ->and($row)->toBe("error\t1\t1")
        ->and($log)->toContain('Marked as ended by an administrator.')
        ->and($after)->toBe($before)
        ->and($page)->toContain('Latest run: Failed')
        ->and($page)->toContain('name="'.SPEC005_START.'"')
        ->and($page)->not->toContain('name="'.SPEC005_END.'"')
        ->and($next)->toBe($id);
})->group('SPEC-005');

it('AC14: a run stuck at starting too, and a new run can start afterwards', function (): void {
    $omeka = spec005Omeka();
    $id = spec005RunIn($omeka, 'starting');
    $page = spec005Section($omeka->get(SPEC005_PAGE)->body);

    spec005Configure($omeka, [SPEC005_END => (string) $id]);
    $status = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
    // A row left at `starting` would hold up the next test's set-up.
    $omeka->sql("UPDATE job SET status = 'stopped', ended = NOW() WHERE id = {$id} AND status = 'starting'");

    expect($page)->toMatch('#name="'.SPEC005_END.'" value="'.$id.'"#')
        ->and($page)->not->toContain('name="'.SPEC005_START.'"')
        ->and($status)->toBe('error')
        ->and(spec005Run($omeka))->toBeGreaterThan($id);
})->group('SPEC-005');

it('AC14: a run in any other status stays as it is, and the page says so', function (string $status): void {
    $omeka = spec005Omeka();
    $id = spec005RunIn($omeka, $status);
    $ended = $omeka->sql("SELECT IFNULL(ended, '-'), IFNULL(log, '-') FROM job WHERE id = {$id}");
    $before = spec005Untouched($omeka);

    $response = spec005Configure($omeka, [SPEC005_END => (string) $id]);
    $messages = OmekaInstallation::messages($omeka->get('/admin/module')->body);
    $row = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
    $sameEnd = $omeka->sql("SELECT IFNULL(ended, '-'), IFNULL(log, '-') FROM job WHERE id = {$id}");
    $omeka->sql("UPDATE job SET status = 'stopped', ended = NOW() WHERE id = {$id}");

    expect($response->status)->toBe(302)
        ->and($messages)->toContain('No run was marked as ended.')
        ->and($row)->toBe($status)
        ->and($sameEnd)->toBe($ended)
        ->and(spec005Untouched($omeka))->toBe($before);
})->with(['in_progress', 'completed', 'stopped', 'error'])->group('SPEC-005');

it('AC14: a job of another class, or a value that is no job, is refused', function (): void {
    $omeka = spec005Omeka();
    $omeka->sql("INSERT INTO job (status, class, started) VALUES ('stopping', 'Some\\\\Other\\\\Job', NOW())");
    $other = (int) $omeka->sql('SELECT MAX(id) FROM job');

    try {
        $messages = [];
        foreach ([(string) $other, 'not-a-number', '0', '999999999', ''] as $value) {
            spec005Configure($omeka, [SPEC005_END => $value]);
            $messages[] = OmekaInstallation::messages($omeka->get('/admin/module')->body);
        }
        $row = $omeka->sql("SELECT status, ended IS NULL FROM job WHERE id = {$other}");
    } finally {
        $omeka->sql("DELETE FROM job WHERE id = {$other}");
    }

    expect($row)->toBe("stopping\t1");
    foreach ($messages as $seen) {
        expect($seen)->toContain('No run was marked as ended.');
    }
})->group('SPEC-005');

it('AC14: global administrators only', function (string $role): void {
    $omeka = spec005Omeka();
    $id = spec005RunIn($omeka, 'stopping');
    [$user, $delete] = spec005User($omeka, $role);
    try {
        $page = $user->get(SPEC005_PAGE);
        $post = $user->post(SPEC005_PAGE, [SPEC005_END => (string) $id, SPEC005_CSRF => 'x']);
    } finally {
        $delete();
    }
    $status = $omeka->sql("SELECT status FROM job WHERE id = {$id}");
    $omeka->sql("UPDATE job SET status = 'stopped', ended = NOW() WHERE id = {$id}");

    expect($page->status)->not->toBe(200)
        ->and($page->body)->not->toContain('name="'.SPEC005_END.'"')
        ->and($post->status)->not->toBe(302)
        ->and($status)->toBe('stopping');
})->with(['site_admin', 'editor', 'author'])->group('SPEC-005');

/**
 * The lines of a job log that are not the module's own (AC7, AC15).
 *
 * @return list<string>
 */
function spec005OwnLines(string $log): array
{
    $own = '#^\S+ (INFO \(6\): (Checking \d+ media\.|Checked \d+ of \d+ media\.|Checked \d+ media: \d+ Trusted, \d+ Valid, \d+ Invalid, \d+ none, \d+ error\.|Stopped after \d+ of \d+ media\.|The check of media \d+ was not stored\.|\d+ checks? (was|were) not stored\.|The run ended early because of an error\.)|ERR \(3\): The run failed\.)$#';
    $lines = array_values(array_filter(explode("\n", $log), static fn (string $line): bool => $line !== ''));

    return array_values(array_filter($lines, static fn (string $line): bool => preg_match($own, $line) !== 1));
}

it('AC15: a run that throws is failed, in the module\'s own words', function (): void {
    $omeka = spec005Omeka();
    $omeka->sql('RENAME TABLE content_credentials_verdict TO spec005_verdict_aside');
    try {
        // "without a result" joins the renamed table in its first query.
        $id = spec005Start($omeka, 'without_result');
        $status = spec005Wait($omeka, $id);
        $log = $omeka->sql("SELECT log FROM job WHERE id = {$id}");
    } finally {
        $omeka->sql('RENAME TABLE spec005_verdict_aside TO content_credentials_verdict');
    }
    $page = spec005Section($omeka->get(SPEC005_PAGE)->body);

    expect($status)->toBe('error')
        ->and($log)->toContain('The run ended early because of an error.')
        ->and($log)->toContain('ERR (3): The run failed.')
        ->and(spec005OwnLines($log))->toBe([])
        ->and($log)->not->toContain('/var/www')
        ->and($log)->not->toContain('SQLSTATE')
        ->and($log)->not->toContain('Exception')
        ->and($page)->toContain('Latest run: Failed')
        ->and($page)->toContain('href="/admin/job/'.$id.'"')
        ->and($page)->toContain('name="'.SPEC005_START.'"')
        ->and($page)->not->toContain('name="'.SPEC005_END.'"');
})->group('SPEC-005');

it('AC15: a run with a check not stored is failed, says how many, and keeps what was stored', function (): void {
    $omeka = spec005Omeka();
    $signed = spec005Upload($omeka, 'fixture-signed.jpg');
    $unsigned = spec005Upload($omeka, 'fixture-unsigned.jpg');
    $omeka->sql("DELETE FROM content_credentials_verdict WHERE media_id = {$unsigned}");
    // A check with a document cannot be stored; one without can.
    $omeka->sql('RENAME TABLE content_credentials_evidence TO spec005_evidence_aside');
    try {
        $id = spec005Start($omeka);
        $status = spec005Wait($omeka, $id);
        $log = $omeka->sql("SELECT log FROM job WHERE id = {$id}");
        $stored = $omeka->sql("SELECT state FROM content_credentials_verdict WHERE media_id = {$unsigned}");
        $notStored = $omeka->sql("SELECT state FROM content_credentials_verdict WHERE media_id = {$signed}");
    } finally {
        $omeka->sql('RENAME TABLE spec005_evidence_aside TO content_credentials_evidence');
    }
    preg_match('#(\d+) checks? (?:was|were) not stored\.#', $log, $count);
    $lines = preg_match_all('#The check of media \d+ was not stored\.#', $log);

    expect($status)->toBe('error')
        ->and($log)->toContain("The check of media {$signed} was not stored.")
        ->and((int) ($count[1] ?? 0))->toBe($lines)
        ->and($lines)->toBeGreaterThan(0)
        ->and($log)->toContain('ERR (3): The run failed.')
        ->and(spec005OwnLines($log))->toBe([])
        ->and($log)->not->toContain('SQLSTATE')
        ->and($stored)->toBe('none')
        ->and($notStored)->not->toBe('');
})->group('SPEC-005');

it('AC15: a run stopped while checks are not stored stays stopped, without the count', function (): void {
    $omeka = spec005Omeka();
    spec005Clones($omeka, 3000);
    $omeka->sql('RENAME TABLE content_credentials_evidence TO spec005_evidence_aside');
    try {
        $id = spec005Start($omeka);
        $end = time() + 60;
        while ($omeka->sql("SELECT status FROM job WHERE id = {$id}") !== 'in_progress' && time() < $end) {
            usleep(50_000);
        }
        spec005Stop($omeka, $id);
        $status = spec005Wait($omeka, $id);
        $log = $omeka->sql("SELECT log FROM job WHERE id = {$id}");
    } finally {
        $omeka->sql('RENAME TABLE spec005_evidence_aside TO content_credentials_evidence');
    }
    spec005RemoveClones($omeka);

    expect($status)->toBe('stopped')
        ->and($log)->toContain('was not stored.')
        ->and($log)->toContain('Stopped after')
        ->and($log)->not->toMatch('#\d+ checks? (was|were) not stored\.#')
        ->and($log)->not->toContain('The run failed.')
        ->and(spec005OwnLines($log))->toBe([]);
})->group('SPEC-005');

it('AC15: an unreadable original still leaves the run completed', function (): void {
    $omeka = spec005Omeka();
    $gone = spec005Upload($omeka, 'fixture-signed.jpg');
    $file = $omeka->sql("SELECT CONCAT(storage_id, '.', extension) FROM media WHERE id = {$gone}");
    $omeka->php(['-r', 'unlink("'.SPEC005_ORIGINALS.'/'.$file.'");']);

    $id = spec005Start($omeka);
    $status = spec005Wait($omeka, $id);
    $row = $omeka->sql("SELECT state FROM content_credentials_verdict WHERE media_id = {$gone}");
    $log = $omeka->sql("SELECT log FROM job WHERE id = {$id}");
    $omeka->api('DELETE', "/api/media/{$gone}");

    expect($status)->toBe('completed')
        ->and($row)->toBe('error')
        ->and($log)->not->toContain('The run failed.');
})->group('SPEC-005');
