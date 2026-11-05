<?php

declare(strict_types=1);

/*
 * SPEC-006: the verifier's evidence document kept for every check, in the
 * pinned Omeka S of tools/env/ (PHP 8.3). The oracle is the verifier
 * library here on the host, with the settings text the module used; a
 * document judged by the clock is compared at its own judged_at.
 */

use ContentCredentials\Tests\Integration\Support\HttpResponse;
use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;
use ContentCredentials\Trust\TrustChoice;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

const SPEC006_TABLE = 'content_credentials_evidence';
const SPEC006_PAGE = '/admin/module/configure?id=ContentCredentials';
const SPEC006_CSRF = 'module_ContentCredentials_configure_csrf';
const SPEC006_RECHECK_CLASS = 'ContentCredentials\\\\Omeka\\\\RecheckJob'; // as an SQL literal
const SPEC006_ORIGINALS = '/var/www/html/files/original';
const SPEC006_PIXEL = 'google-20250919-pixel10-npld-picnic-table.jpg';

/**
 * The PHP 8.3 installation, logged in, the module active with the evidence
 * table (installed again when it lacks it), no trust setting stored.
 */
function spec006Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow('ContentCredentials');
    if ($row !== null && $omeka->sql("SHOW TABLES LIKE '".SPEC006_TABLE."'") === '') {
        $omeka->moduleAction('uninstall', 'ContentCredentials');
        $row = null;
    }
    if ($row === null) {
        $omeka->moduleAction('install', 'ContentCredentials');
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', 'ContentCredentials');
    }
    expect($omeka->sql("SHOW TABLES LIKE '".SPEC006_TABLE."'"))->toBe(SPEC006_TABLE);
    $omeka->sql("DELETE FROM setting WHERE id LIKE 'content\\_credentials\\_%'");

    return $omeka;
}

function spec006Upload(OmekaInstallation $omeka, string $fixture): int
{
    $type = str_ends_with($fixture, '.png') ? 'image/png' : (str_ends_with($fixture, '.webp') ? 'image/webp' : 'image/jpeg');
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm(VerifierCli::fixture($fixture), $fixture, $type)));

    return $media[0] ?? throw new RuntimeException("No media for {$fixture}");
}

/**
 * A media item's evidence rows, oldest first, every column as stored.
 *
 * @return list<array{id: int, checked_at: string, checked_sha256: string, verifier: string, trust: ?string, trust_sha256: ?string, sha256: string, document: string}>
 */
function spec006Rows(OmekaInstallation $omeka, int $media): array
{
    $out = $omeka->sql('SELECT id, checked_at, checked_sha256, verifier, IFNULL(trust, "-"), IFNULL(trust_sha256, "-"), sha256, HEX(document) FROM '.SPEC006_TABLE." WHERE media_id = {$media} ORDER BY id");
    $rows = [];
    foreach ($out === '' ? [] : explode("\n", $out) as $line) {
        $c = explode("\t", $line);
        $rows[] = [
            'id' => (int) $c[0], 'checked_at' => $c[1], 'checked_sha256' => $c[2], 'verifier' => $c[3],
            'trust' => $c[4] === '-' ? null : $c[4], 'trust_sha256' => $c[5] === '-' ? null : $c[5],
            'sha256' => $c[6], 'document' => (string) hex2bin($c[7]),
        ];
    }

    return $rows;
}

/**
 * What the verifier writes for this file and settings text. A stored
 * document judged by the clock is matched at its own judged_at, given as
 * the validation time; only the "given" the verifier then writes is put
 * back to "clock".
 */
function spec006Expected(string $path, ?string $settings, string $stored): string
{
    $at = null;
    foreach (spec006Manifests($stored) as $manifest) {
        if ($manifest['time_source'] === 'clock') {
            $at = new DateTimeImmutable('@'.$manifest['judged_at']);
        }
    }
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        throw new RuntimeException("Cannot open {$path}");
    }
    try {
        $json = (new Verifier)->verify($stream, $settings === null ? null : TrustSettings::fromJson($settings), $at)->evidenceToJson();
    } finally {
        fclose($stream);
    }

    return $at === null ? $json : str_replace('"time_source": "given"', '"time_source": "clock"', $json);
}

/**
 * The judged time and its source of each manifest in a document.
 *
 * @return list<array{judged_at: int, time_source: string}>
 */
function spec006Manifests(string $document): array
{
    $decoded = json_decode($document, true, 512, JSON_THROW_ON_ERROR);
    $manifests = is_array($decoded) && is_array($decoded['manifests'] ?? null) ? $decoded['manifests'] : [];
    $out = [];
    foreach ($manifests as $manifest) {
        if (is_array($manifest) && is_int($manifest['judged_at'] ?? null) && is_string($manifest['time_source'] ?? null)) {
            $out[] = ['judged_at' => $manifest['judged_at'], 'time_source' => $manifest['time_source']];
        }
    }

    return $out;
}

/** The module's block in a page, from its opening div to its end marker. */
function spec006Block(string $html): string
{
    $start = strpos($html, '<div class="meta-group content-credentials">');

    return $start === false ? '' : substr($html, $start, (int) strpos($html, '<!-- /content-credentials -->', $start) - $start);
}

/** Starts "Check all media" and waits until it has ended. */
function spec006Run(OmekaInstallation $omeka): void
{
    preg_match('#name="'.SPEC006_CSRF.'" value="([^"]+)"#', $omeka->get(SPEC006_PAGE)->body, $m);
    $before = (int) $omeka->sql("SELECT IFNULL(MAX(id), 0) FROM job WHERE class = '".SPEC006_RECHECK_CLASS."'");
    expect($omeka->post(SPEC006_PAGE, ['content_credentials_recheck' => 'all', SPEC006_CSRF => $m[1] ?? ''])->status)->toBe(302);
    $id = (int) $omeka->sql("SELECT IFNULL(MAX(id), 0) FROM job WHERE class = '".SPEC006_RECHECK_CLASS."'");
    expect($id)->toBeGreaterThan($before);
    $end = time() + 300;
    while (! in_array($status = $omeka->sql("SELECT status FROM job WHERE id = {$id}"), ['completed', 'stopped', 'error'], true) && time() < $end) {
        usleep(200_000);
    }
    expect($status)->toBe('completed');
}

/** Adds copies of a media item's newest evidence row. */
function spec006Copies(OmekaInstallation $omeka, int $media, int $n): void
{
    for ($i = 0; $i < $n; $i++) {
        $omeka->sql('INSERT INTO '.SPEC006_TABLE.' (media_id, checked_at, checked_sha256, verifier, trust, trust_sha256, sha256, document)'
            .' SELECT media_id, checked_at, checked_sha256, verifier, trust, trust_sha256, sha256, document FROM '.SPEC006_TABLE." WHERE media_id = {$media} ORDER BY id DESC LIMIT 1");
    }
}

/** Sets an evidence row's document (hex, so it arrives as it is) and its sha256. */
function spec006SetDocument(OmekaInstallation $omeka, int $id, string $document, ?string $verifier = null): void
{
    $omeka->sql('UPDATE '.SPEC006_TABLE." SET document = CONVERT(X'".bin2hex($document)."' USING utf8mb4), sha256 = '".hash('sha256', $document)."'"
        .($verifier === null ? '' : ", verifier = '{$verifier}'")." WHERE id = {$id}");
}

/**
 * A user of a role, logged in, and a function that deletes it.
 *
 * @return array{OmekaInstallation, Closure(): string}
 */
function spec006User(OmekaInstallation $omeka, string $role): array
{
    $email = "spec006-{$role}-".getmypid().'-'.random_int(0, PHP_INT_MAX).'@example.com';
    $id = OmekaInstallation::idFrom($omeka->api('POST', '/api/users', ['o:email' => $email, 'o:name' => $role, 'o:role' => $role, 'o:is_active' => true]));
    $omeka->sql("UPDATE user SET password_hash = '".password_hash('spec006-test-only', PASSWORD_DEFAULT)."' WHERE id = {$id}");
    $user = OmekaInstallation::php83();
    $user->logIn($email, 'spec006-test-only');

    return [$user, static fn () => $omeka->sql("DELETE FROM user WHERE id = {$id}")];
}

/** A status as the table of AC10 compares it: 302 with where it goes. */
function spec006Answer(HttpResponse $response): string
{
    return $response->status === 302 ? '302 '.(str_contains($response->location, '/login') ? 'login' : $response->location) : (string) $response->status;
}

it('AC1: an upload keeps its evidence', function (string $fixture): void {
    $omeka = spec006Omeka();
    $settings = TrustChoice::bundledJson(true, dirname(__DIR__, 2).'/trust');
    $before = time();
    $media = spec006Upload($omeka, $fixture);
    $after = time();
    $rows = spec006Rows($omeka, $media);
    $row = $rows[0] ?? null;
    $same = $omeka->sql('SELECT CONCAT_WS(" ", e.checked_at = v.checked_at, e.trust <=> v.trust) FROM '.SPEC006_TABLE." e JOIN content_credentials_verdict v ON v.media_id = e.media_id WHERE e.media_id = {$media}");

    expect($rows)->toHaveCount(1)
        ->and($row['document'] ?? null)->toBe(spec006Expected(VerifierCli::fixture($fixture), $settings, $row['document'] ?? '{}'))
        ->and($row['sha256'] ?? null)->toBe(hash('sha256', $row['document'] ?? ''))
        ->and($row['checked_sha256'] ?? null)->toBe(hash_file('sha256', VerifierCli::fixture($fixture)))
        ->and($row['verifier'] ?? null)->toBe(Verifier::VERSION)
        ->and($row['trust_sha256'] ?? null)->toBe(hash('sha256', $settings))
        ->and($same)->toBe('1 1');
    if ($fixture === SPEC006_PIXEL) {
        // judged at its trusted time-stamp: the same bytes whatever the clock
        expect($row['document'] ?? '')->toContain('"time_source": "timestamp"')
            ->and($row['document'] ?? '')->not->toContain('"time_source": "clock"');
    } else {
        foreach (spec006Manifests($row['document'] ?? '{}') as $manifest) {
            expect($manifest['judged_at'])->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual($after);
        }
    }
})->with(['fixture-signed.jpg', SPEC006_PIXEL])->group('SPEC-006');

it('AC2: custom settings and settings that cannot be built', function (): void {
    $omeka = spec006Omeka();
    $custom = (string) file_get_contents(VerifierCli::settingsFile(anchors: [[VerifierCli::fixture('c2pa-rs-test-trust-anchors.pem'), 'manifest']]));
    $store = static function (string $value) use ($omeka): void {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $omeka->sql("INSERT INTO setting (id, value) VALUES ('content_credentials_custom_settings', CONVERT(X'".bin2hex($json)."' USING utf8mb4)) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    };
    $store($custom);
    $withCustom = spec006Upload($omeka, 'fixture-signed.png');
    $store('not json <b>');
    $without = spec006Upload($omeka, 'fixture-signed.png');
    $omeka->sql("DELETE FROM setting WHERE id LIKE 'content\\_credentials\\_%'");
    [$c] = spec006Rows($omeka, $withCustom) + [null];
    [$n] = spec006Rows($omeka, $without) + [null];

    expect($c['trust'] ?? null)->toBe('custom')
        ->and($c['trust_sha256'] ?? null)->toBe(hash('sha256', $custom))
        ->and($c['document'] ?? null)->toBe(spec006Expected(VerifierCli::fixture('fixture-signed.png'), $custom, $c['document'] ?? '{}'))
        ->and($n['trust'] ?? null)->toBe($omeka->sql("SELECT trust FROM content_credentials_verdict WHERE media_id = {$without}"))
        ->and($n['trust'] ?? null)->toBe('none')
        ->and($n)->toHaveKey('trust_sha256', null)
        ->and($n['document'] ?? null)->toBe(spec006Expected(VerifierCli::fixture('fixture-signed.png'), null, $n['document'] ?? '{}'));
})->group('SPEC-006');

it('AC3: a file without Content Credentials keeps no evidence', function (string $fixture): void {
    $omeka = spec006Omeka();
    $media = spec006Upload($omeka, $fixture);

    expect($omeka->sql("SELECT state FROM content_credentials_verdict WHERE media_id = {$media}"))->toBe('none')
        ->and(spec006Rows($omeka, $media))->toBe([]);
})->with(['fixture-unsigned.jpg', 'fixture-unsigned.png', 'fixture-unsigned.webp'])->group('SPEC-006');

it('AC4: an unreadable original keeps no evidence', function (): void {
    $omeka = spec006Omeka();
    $gone = spec006Upload($omeka, 'fixture-signed.jpg');
    $file = $omeka->sql("SELECT CONCAT(storage_id, '.', extension) FROM media WHERE id = {$gone}");
    $omeka->php(['-r', 'unlink("'.SPEC006_ORIGINALS.'/'.$file.'");']);

    spec006Run($omeka);

    expect($omeka->sql("SELECT CONCAT_WS(' ', state, reason) FROM content_credentials_verdict WHERE media_id = {$gone}"))->toBe('error unreadable')
        ->and(spec006Rows($omeka, $gone))->toHaveCount(1);
    $omeka->api('DELETE', "/api/media/{$gone}");
})->group('SPEC-006');

it('AC5: every run adds, nothing is changed', function (): void {
    $omeka = spec006Omeka();
    $media = spec006Upload($omeka, 'fixture-signed.jpg');
    $first = spec006Rows($omeka, $media)[0]['id'] ?? 0;
    spec006SetDocument($omeka, $first, "{\n    \"written\": \"by an older verifier\"\n}", '0.9.0');
    $kept = spec006Rows($omeka, $media)[0] ?? null;

    spec006Run($omeka);
    spec006Run($omeka);
    $rows = spec006Rows($omeka, $media);
    $newest = $rows[2]['id'] ?? 0;
    $same = $omeka->sql('SELECT CONCAT_WS(" ", e.checked_at = v.checked_at, e.trust <=> v.trust, e.verifier = v.verifier, e.checked_sha256 = v.checked_sha256) FROM '.SPEC006_TABLE." e JOIN content_credentials_verdict v ON v.media_id = e.media_id WHERE e.id = {$newest}");

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe($kept)
        ->and($rows[1]['id'])->toBeGreaterThan($rows[0]['id'])
        ->and($rows[2]['id'])->toBeGreaterThan($rows[1]['id'])
        ->and($rows[1]['verifier'])->toBe(Verifier::VERSION)
        ->and($same)->toBe('1 1 1 1');
})->group('SPEC-006');

it('AC6: the block counts and links', function (): void {
    $omeka = spec006Omeka();
    $three = spec006Upload($omeka, 'fixture-signed.jpg');
    spec006Copies($omeka, $three, 2);
    $one = spec006Upload($omeka, 'fixture-signed.png');
    $none = spec006Upload($omeka, 'fixture-unsigned.jpg');
    $line = static fn (int $media, string $text): string => '<div class="value"><a href="/admin/content-credentials/media/'.$media.'/evidence">'.$text.'</a></div>';

    foreach (["/admin/media/{$three}", "/admin/media/{$three}/show-details"] as $page) {
        expect(spec006Block($omeka->get($page)->body))->toEndWith($line($three, 'Evidence: 3 checks kept').'</div>');
    }
    foreach (["/admin/media/{$one}", "/admin/media/{$one}/show-details"] as $page) {
        expect(spec006Block($omeka->get($page)->body))->toEndWith($line($one, 'Evidence: 1 check kept').'</div>');
    }
    $block = spec006Block($omeka->get("/admin/media/{$none}")->body);
    expect($block)->not->toBe('')
        ->and($block)->not->toContain('Evidence')
        ->and($block)->not->toContain('<a');
})->group('SPEC-006');

it('AC7: the evidence page and the document page', function (): void {
    $omeka = spec006Omeka();
    $media = spec006Upload($omeka, 'fixture-signed.jpg');
    spec006Copies($omeka, $media, 1);
    [$older, $newer] = spec006Rows($omeka, $media);
    spec006SetDocument($omeka, $newer['id'], '{"active_manifest": "<script>alert(1)</script>\u202e"}');

    $list = $omeka->get("/admin/content-credentials/media/{$media}/evidence");
    $show = $omeka->get("/admin/content-credentials/evidence/{$newer['id']}");
    $at = static fn (int $id): int => (int) strpos($list->body, '/admin/content-credentials/evidence/'.$id.'"');

    expect($list->status)->toBe(200)
        ->and($list->body)->toContain('href="/admin/content-credentials/evidence/'.$older['id'].'"')
        ->and($list->body)->toContain('href="/admin/content-credentials/evidence/'.$older['id'].'/download"')
        ->and($list->body)->toContain('href="/admin/content-credentials/evidence/'.$newer['id'].'/download"')
        ->and($at($newer['id']))->toBeGreaterThan(0)
        ->and($at($newer['id']))->toBeLessThan($at($older['id']))
        ->and($list->body)->toContain($older['checked_at'].' UTC')
        ->and($list->body)->toContain(Verifier::VERSION)
        ->and($list->body)->toContain('Trust: the C2PA trust list of 2026-10-17, with DigiCert timestamps')
        ->and($list->body)->toContain($older['checked_sha256'])
        ->and($show->status)->toBe(200)
        ->and($show->body)->toMatch('#<pre[^>]*>\{&quot;active_manifest&quot;: &quot;&lt;script&gt;alert\(1\)&lt;/script&gt;\\\\u202e&quot;\}</pre>#')
        ->and($show->body)->not->toContain('<script>alert(1)');
})->group('SPEC-006');

it('AC8: the download is the stored document', function (): void {
    $omeka = spec006Omeka();
    $media = spec006Upload($omeka, 'fixture-signed.jpg');
    $row = spec006Rows($omeka, $media)[0];

    $download = $omeka->get("/admin/content-credentials/evidence/{$row['id']}/download");

    expect($download->status)->toBe(200)
        ->and($download->headers['content-type'] ?? null)->toBe(['application/json'])
        ->and($download->headers['content-disposition'] ?? null)->toBe(['attachment; filename="media-'.$media.'-evidence-'.$row['id'].'.json"'])
        ->and($download->headers['x-content-type-options'] ?? null)->toBe(['nosniff'])
        ->and($download->body)->toBe($row['document'])
        ->and(hash('sha256', $download->body))->toBe($row['sha256']);
})->group('SPEC-006');

it('AC9: an evidence table that cannot be written: no new verdict, the upload proceeds', function (): void {
    $omeka = spec006Omeka();
    $omeka->sql('RENAME TABLE '.SPEC006_TABLE.' TO spec006_away');
    try {
        $response = $omeka->uploadThroughAdminForm(VerifierCli::fixture('fixture-signed.jpg'), 'fixture-signed.jpg', 'image/jpeg');
    } finally {
        $omeka->sql('RENAME TABLE spec006_away TO '.SPEC006_TABLE);
    }
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response));

    expect($response->status)->toBe(302)
        ->and($media)->toHaveCount(1)
        ->and($omeka->sql('SELECT CONCAT_WS(" ", state, reason) FROM content_credentials_verdict WHERE media_id = '.($media[0] ?? 0)))->toBe('error interrupted')
        ->and(spec006Rows($omeka, $media[0] ?? 0))->toBe([]);
})->group('SPEC-006');

it('AC9: a verdict that cannot be written takes its evidence back', function (): void {
    $omeka = spec006Omeka();
    // Only the upsert over the placeholder row fails, inside the transaction.
    $omeka->sql('CREATE TRIGGER spec006_refuse BEFORE UPDATE ON content_credentials_verdict FOR EACH ROW SET @spec006 = (SELECT COUNT(*) FROM spec006_no_such_table)');
    try {
        $response = $omeka->uploadThroughAdminForm(VerifierCli::fixture('fixture-signed.jpg'), 'fixture-signed.jpg', 'image/jpeg');
    } finally {
        $omeka->sql('DROP TRIGGER spec006_refuse');
    }
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response));

    expect($response->status)->toBe(302)
        ->and($omeka->sql('SELECT CONCAT_WS(" ", state, reason) FROM content_credentials_verdict WHERE media_id = '.($media[0] ?? 0)))->toBe('error interrupted')
        ->and(spec006Rows($omeka, $media[0] ?? 0))->toBe([]);
})->group('SPEC-006');

it('AC10: access follows the media item', function (): void {
    $omeka = spec006Omeka();
    $p = spec006Upload($omeka, 'fixture-signed.jpg');
    $omeka->startFixtureServer();
    $item = OmekaInstallation::idFrom($omeka->api('POST', '/api/items', ['o:is_public' => false, 'o:media' => [['o:ingester' => 'url', 'o:is_public' => false, 'ingest_url' => 'http://127.0.0.1:'.OmekaInstallation::FIXTURE_PORT.'/fixture-signed.jpg']]]));
    $q = $omeka->mediaIdsOf($item)[0] ?? 0;
    $evidence = [$p => spec006Rows($omeka, $p)[0]['id'] ?? 0, $q => spec006Rows($omeka, $q)[0]['id'] ?? 0];
    expect($evidence[$p])->toBeGreaterThan(0)->and($evidence[$q])->toBeGreaterThan(0);
    $users = ['anonymous' => [OmekaInstallation::php83(), static fn (): string => '']];
    $users['global_admin'] = [$omeka, static fn (): string => ''];
    foreach (['site_admin', 'editor', 'reviewer', 'author', 'researcher'] as $role) {
        $users[$role] = spec006User($omeka, $role);
    }

    $answers = [];
    try {
        foreach ($users as $role => [$user]) {
            foreach ([$p => 'P', $q => 'Q'] as $media => $name) {
                $page = spec006Answer($user->get("/admin/media/{$media}"));
                $answers["{$role} {$name}"] = [
                    $page,
                    spec006Answer($user->get("/admin/content-credentials/media/{$media}/evidence")),
                    spec006Answer($user->get("/admin/content-credentials/evidence/{$evidence[$media]}")),
                    spec006Answer($user->get("/admin/content-credentials/evidence/{$evidence[$media]}/download")),
                ];
            }
        }
    } finally {
        foreach ($users as [, $delete]) {
            $delete();
        }
        $omeka->api('DELETE', "/api/items/{$item}");
    }

    foreach ($answers as $who => $answer) {
        expect([$who, $answer])->toBe([$who, array_fill(0, 4, $answer[0])]);
    }
    expect($answers['anonymous P'][0] ?? null)->toBe('302 login')
        ->and($answers['global_admin Q'][0] ?? null)->toBe('200')
        ->and($answers['editor P'][0] ?? null)->toBe('200')
        ->and($answers['author Q'][0] ?? null)->toBe('404')
        ->and($answers['researcher Q'][0] ?? null)->toBe('404');
})->group('SPEC-006');

it('AC11: an unknown or malformed address is 404, with nothing inside', function (string $path): void {
    $omeka = spec006Omeka();

    $response = $omeka->get($path);

    expect($response->status)->toBe(404)
        ->and($response->body)->not->toContain('/var/www')
        ->and($response->body)->not->toContain('#0 ')
        ->and($response->body)->not->toContain('Stack trace');
})->with([
    '/admin/content-credentials/evidence/999999999',
    '/admin/content-credentials/evidence/999999999/download',
    '/admin/content-credentials/media/999999999/evidence',
    '/admin/content-credentials/evidence/abc',
    '/admin/content-credentials/evidence/abc/download',
    '/admin/content-credentials/media/abc/evidence',
])->group('SPEC-006');

it('AC12: deleting a media item, and uninstalling', function (): void {
    $omeka = spec006Omeka();
    $media = spec006Upload($omeka, 'fixture-signed.jpg');
    $item = (int) $omeka->sql("SELECT item_id FROM media WHERE id = {$media}");
    expect(spec006Rows($omeka, $media))->toHaveCount(1);

    $omeka->api('DELETE', "/api/items/{$item}");
    $left = $omeka->sql('SELECT COUNT(*) FROM '.SPEC006_TABLE." WHERE media_id = {$media}");
    $omeka->moduleAction('uninstall', 'ContentCredentials');
    $before = $omeka->snapshot();
    $omeka->moduleAction('install', 'ContentCredentials');
    $omeka->moduleAction('uninstall', 'ContentCredentials');
    $after = $omeka->snapshot();
    $table = $omeka->sql("SHOW TABLES LIKE '".SPEC006_TABLE."'");
    $omeka->moduleAction('install', 'ContentCredentials');

    expect($left)->toBe('0')
        ->and($table)->toBe('')
        ->and($after)->toBe($before);
})->group('SPEC-006');
