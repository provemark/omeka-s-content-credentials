<?php

declare(strict_types=1);

/*
 * SPEC-004: trust settings in the pinned Omeka S of tools/env/ (PHP 8.3):
 * the configure page, verifying uploads with the bundled lists or custom
 * settings, and the trust source in each row. The oracle is
 * vendor/bin/c2pa-verify --settings on the same file, with settings built
 * by the test from trust/ the way the verifier's docs build them.
 */

use ContentCredentials\Tests\Integration\Support\HttpResponse;
use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;
use ContentCredentials\Trust\BundledLists;

const SPEC004_PAGE = '/admin/module/configure?id=ContentCredentials';
const SPEC004_CSRF = 'module_ContentCredentials_configure_csrf';

/**
 * The PHP 8.3 installation, logged in, the module active with the table of
 * SPEC-002 amendment 1, and no trust setting stored.
 */
function spec004Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow('ContentCredentials');
    if ($row !== null && $omeka->sql("SHOW COLUMNS FROM content_credentials_verdict LIKE 'trust'") === '') {
        // A table from before SPEC-002 amendment 1: install again.
        $omeka->moduleAction('uninstall', 'ContentCredentials');
        $row = null;
    }
    if ($row === null) {
        $omeka->moduleAction('install', 'ContentCredentials');
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', 'ContentCredentials');
    }
    $omeka->sql("DELETE FROM setting WHERE id LIKE 'content\\_credentials\\_%'");

    return $omeka;
}

/** Writes a setting as Omeka stores it (a JSON value), bypassing the page. */
function spec004Store(OmekaInstallation $omeka, string $id, mixed $value): void
{
    $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $omeka->sql("INSERT INTO setting (id, value) VALUES ('{$id}', CONVERT(X'".bin2hex($json)."' USING utf8mb4)) ON DUPLICATE KEY UPDATE value = VALUES(value)");
}

/** A setting's stored JSON value, or '' when there is no row. */
function spec004Setting(OmekaInstallation $omeka, string $id): string
{
    return $omeka->sql("SELECT value FROM setting WHERE id = '{$id}'");
}

/**
 * Submits the configure page with its CSRF token.
 *
 * @param  array<string, string>  $fields
 */
function spec004Configure(OmekaInstallation $omeka, array $fields): HttpResponse
{
    $page = $omeka->get(SPEC004_PAGE);
    preg_match('#name="'.SPEC004_CSRF.'" value="([^"]+)"#', $page->body, $m);

    return $omeka->post(SPEC004_PAGE, $fields + [SPEC004_CSRF => $m[1] ?? '']);
}

function spec004Upload(OmekaInstallation $omeka, string $fixture): int
{
    $type = str_ends_with($fixture, '.png') ? 'image/png' : 'image/jpeg';
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm(VerifierCli::fixture($fixture), $fixture, $type)));

    return $media[0] ?? throw new RuntimeException("No media for {$fixture}");
}

/** The custom settings of AC4: only the c2pa-rs test roots, as signers. */
function spec004Custom(): string
{
    return (string) file_get_contents(VerifierCli::settingsFile(anchors: [[VerifierCli::fixture('c2pa-rs-test-trust-anchors.pem'), 'manifest']]));
}

/** The textarea's content on a page, decoded. */
function spec004Textarea(string $html): ?string
{
    // strpos, not a regex: a 1 MiB textarea exceeds PCRE's backtrack limit.
    $open = strpos($html, '<textarea name="content_credentials_custom_settings"');
    $start = $open === false ? false : strpos($html, '>', $open);
    $end = $start === false ? false : strpos($html, '</textarea>', $start);

    return $start === false || $end === false
        ? null
        : html_entity_decode(substr($html, $start + 1, $end - $start - 1), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Whether the DigiCert checkbox on a page is checked. */
function spec004DigicertChecked(string $html): bool
{
    expect(preg_match('#<input type="checkbox" name="content_credentials_digicert"[^>]*>#', $html, $box))->toBe(1);

    return str_contains($box[0] ?? '', ' checked');
}

/**
 * Waits until the container sees a bundled list with this SHA-256: the
 * bind mount was measured to show a second write within a second late.
 */
function spec004SeenInContainer(OmekaInstallation $omeka, string $name, string $sha256): void
{
    $path = '/var/www/html/modules/ContentCredentials/trust/'.$name;
    for ($i = 0; $i < 100; $i++) {
        if (str_starts_with($omeka->rootShell("sha256sum {$path}"), $sha256)) {
            return;
        }
        usleep(100_000);
    }
    throw new RuntimeException("The container does not see {$name} as written.");
}

/**
 * The row's state, codes and trust, next to the CLI's state and codes.
 *
 * @return array{0: array{mixed, mixed, mixed}, 1: array{mixed, string}}
 */
function spec004Compare(OmekaInstallation $omeka, int $media, string $fixture, ?string $settingsFile): array
{
    $row = $omeka->verdictRow($media) ?? [];
    $cli = VerifierCli::report(VerifierCli::fixture($fixture), $settingsFile);
    $codes = $cli['validation_state'] === 'Invalid' ? VerifierCli::codes($cli) : [];

    return [
        [$row['state'] ?? null, $row['codes'] ?? null, $row['trust'] ?? null],
        [$cli['has_manifest'] === true ? $cli['validation_state'] : 'none', json_encode($codes, JSON_THROW_ON_ERROR)],
    ];
}

it('AC2: by default, a Pixel 10 photo is Trusted', function (): void {
    $omeka = spec004Omeka();
    $fixture = 'google-20250919-pixel10-npld-picnic-table.jpg';

    $media = spec004Upload($omeka, $fixture);
    [$row, $cli] = spec004Compare($omeka, $media, $fixture, VerifierCli::settingsFile(true));
    $cell = preg_match('#value="'.$media.'".*?<td class="column-content_credentials">(.*?)</td>#s', $omeka->get('/admin/media')->body, $m) === 1 ? strip_tags($m[1]) : null;

    expect($row)->toBe(['Trusted', '[]', 'c2pa-2026-11-04+digicert'])
        ->and($cli)->toBe(['Trusted', '[]'])
        ->and($cell)->toBe('Verified: trusted signer');
})->group('SPEC-004');

it('AC3: DigiCert decides an expired signer with a DigiCert timestamp', function (): void {
    $omeka = spec004Omeka();
    $fixture = 'c2pa-rs-ocsp.jpg';

    expect(spec004Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => ''])->status)->toBe(302);
    $on = spec004Compare($omeka, spec004Upload($omeka, $fixture), $fixture, VerifierCli::settingsFile(true));
    // An unchecked box is absent from the POST; only the hidden field remains.
    expect(spec004Configure($omeka, ['content_credentials_digicert' => '0', 'content_credentials_custom_settings' => ''])->status)->toBe(302);
    $off = spec004Compare($omeka, spec004Upload($omeka, $fixture), $fixture, VerifierCli::settingsFile(false));

    expect($on[0])->toBe(['Valid', '[]', 'c2pa-2026-11-04+digicert'])
        ->and($on[1])->toBe(['Valid', '[]'])
        ->and($off[0][0])->toBe('Invalid')
        ->and($off[0][2])->toBe('c2pa-2026-11-04')
        ->and([$off[0][0], $off[0][1]])->toBe($off[1])
        ->and(is_string($off[0][1]) ? $off[0][1] : '')->toContain('signingCredential.expired');
})->group('SPEC-004');

it('AC4: custom settings replace the bundled lists', function (): void {
    $omeka = spec004Omeka();
    $custom = spec004Custom();
    $file = VerifierCli::settingsFile(anchors: [[VerifierCli::fixture('c2pa-rs-test-trust-anchors.pem'), 'manifest']]);

    expect(spec004Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => $custom])->status)->toBe(302)
        ->and(json_decode(spec004Setting($omeka, 'content_credentials_custom_settings'), true))->toBe($custom);
    $signed = spec004Compare($omeka, spec004Upload($omeka, 'fixture-signed.jpg'), 'fixture-signed.jpg', $file);
    $pixel = spec004Compare($omeka, spec004Upload($omeka, 'google-20250919-pixel10-npld-picnic-table.jpg'), 'google-20250919-pixel10-npld-picnic-table.jpg', $file);

    expect($signed[0])->toBe(['Trusted', '[]', 'custom'])
        ->and($signed[1])->toBe(['Trusted', '[]'])
        ->and($pixel[0][0])->toBe('Invalid')
        ->and($pixel[0][2])->toBe('custom')
        ->and([$pixel[0][0], $pixel[0][1]])->toBe($pixel[1]);
})->group('SPEC-004');

it('AC5: invalid custom settings are refused and given back', function (string $text, string $reason): void {
    $omeka = spec004Omeka();
    expect(spec004Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => spec004Custom()])->status)->toBe(302);
    $saved = spec004Setting($omeka, 'content_credentials_custom_settings');

    $response = spec004Configure($omeka, ['content_credentials_digicert' => '0', 'content_credentials_custom_settings' => $text]);
    $messages = OmekaInstallation::messages($response->body);

    expect($response->status)->toBe(200)
        ->and(spec004Setting($omeka, 'content_credentials_custom_settings'))->toBe($saved)
        ->and(spec004Setting($omeka, 'content_credentials_digicert'))->toBe('true')
        ->and(implode("\n", $messages))->toContain('These trust settings were not saved:')
        ->and(implode("\n", $messages))->toContain($reason)
        ->and(spec004Textarea($response->body))->toBe($text);
})->with([
    'not JSON' => ['not json <b>', 'JSON'],
    'allowed_list at the top' => ['{"trust":{"allowed_list":"<i>x</i>"}}', 'allowed_list'],
    'over 1 MiB' => [str_repeat(' ', 1048577), 'larger than 1 MiB'],
])->group('SPEC-004');

it('AC12: a refused form gives back the DigiCert choice', function (bool $saved, string $submitted, bool $shown): void {
    $omeka = spec004Omeka();
    spec004Store($omeka, 'content_credentials_digicert', $saved);
    $stored = spec004Setting($omeka, 'content_credentials_digicert');

    $response = spec004Configure($omeka, ['content_credentials_digicert' => $submitted, 'content_credentials_custom_settings' => 'not json']);

    expect($response->status)->toBe(200)
        ->and(spec004DigicertChecked($response->body))->toBe($shown)
        ->and(spec004Setting($omeka, 'content_credentials_digicert'))->toBe($stored)
        ->and(spec004Textarea($response->body))->toBe('not json')
        // Only the refused request gives it back; a new page shows what is stored.
        ->and(spec004DigicertChecked($omeka->get(SPEC004_PAGE)->body))->toBe($saved);

    // The page saved as shown, with the text corrected, saves the submitted choice.
    expect(spec004Configure($omeka, ['content_credentials_digicert' => $shown ? '1' : '0', 'content_credentials_custom_settings' => ''])->status)->toBe(302)
        ->and(spec004Setting($omeka, 'content_credentials_digicert'))->toBe($shown ? 'true' : 'false')
        ->and(spec004DigicertChecked($omeka->get(SPEC004_PAGE)->body))->toBe($shown);
})->with([
    'on, turned off' => [true, '0', false],
    'off, turned on' => [false, '1', true],
])->group('SPEC-004');

it('AC12: a submitted value other than 1 is unchecked', function (string $name, string $value): void {
    $omeka = spec004Omeka();
    spec004Store($omeka, 'content_credentials_digicert', true);

    $response = spec004Configure($omeka, [$name => $value, 'content_credentials_custom_settings' => 'not json']);

    expect($response->status)->toBe(200)
        ->and(spec004DigicertChecked($response->body))->toBeFalse()
        ->and(spec004Setting($omeka, 'content_credentials_digicert'))->toBe('true')
        ->and($response->body)->not->toContain('Warning:')
        ->and($response->body)->not->toContain('Array to string');
})->with([
    'missing' => ['another_field', '1'],
    'an array' => ['content_credentials_digicert[]', '1'],
    'zero' => ['content_credentials_digicert', '0'],
    'on' => ['content_credentials_digicert', 'on'],
    'one with a space' => ['content_credentials_digicert', '1 '],
    'empty' => ['content_credentials_digicert', ''],
])->group('SPEC-004');

it('AC6: settings that cannot be built: checked without them', function (): void {
    $omeka = spec004Omeka();
    spec004Store($omeka, 'content_credentials_custom_settings', 'not json <b>');

    $response = $omeka->uploadThroughAdminForm(VerifierCli::fixture('fixture-signed.jpg'), 'fixture-signed.jpg', 'image/jpeg');
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response))[0] ?? 0;
    [$row, $cli] = spec004Compare($omeka, $media, 'fixture-signed.jpg', null);
    $page = $omeka->get(SPEC004_PAGE)->body;

    expect($response->status)->toBe(302)
        ->and($row)->toBe(['Valid', '[]', 'none'])
        ->and($cli)->toBe(['Valid', '[]'])
        ->and($page)->toContain('The saved custom trust settings cannot be used; new uploads and runs are checked without a trust list.')
        ->and($page)->not->toContain('not json <b>');
})->group('SPEC-004');

it('AC13: a bundled list that is not the shipped file: checked without settings', function (string $damaged): void {
    $omeka = spec004Omeka();
    // The module is mounted from the working tree: the list is damaged there, and put back.
    $list = dirname(__DIR__, 2).'/trust/C2PA-TRUST-LIST.pem';
    $shipped = (string) file_get_contents($list);
    expect(hash('sha256', $shipped))->toBe(BundledLists::FILES[BundledLists::C2PA]);
    try {
        file_put_contents($list, $damaged);
        spec004SeenInContainer($omeka, BundledLists::C2PA, hash('sha256', $damaged));
        $response = $omeka->uploadThroughAdminForm(VerifierCli::fixture('fixture-signed.jpg'), 'fixture-signed.jpg', 'image/jpeg');
        $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response))[0] ?? 0;
        $page = $omeka->get(SPEC004_PAGE);
    } finally {
        file_put_contents($list, $shipped);
        spec004SeenInContainer($omeka, BundledLists::C2PA, BundledLists::FILES[BundledLists::C2PA]);
    }
    [$row, $cli] = spec004Compare($omeka, $media, 'fixture-signed.jpg', null);

    expect(hash_file('sha256', $list))->toBe(BundledLists::FILES[BundledLists::C2PA])
        ->and($response->status)->toBe(302)
        ->and($row)->toBe(['Valid', '[]', 'none'])
        ->and($cli)->toBe(['Valid', '[]'])
        ->and($page->status)->toBe(200)
        ->and($page->body)->toContain('The bundled trust lists cannot be used; new uploads and runs are checked without a trust list.')
        ->and($page->body)->toContain('C2PA-TRUST-LIST.pem')
        ->and($page->body)->not->toContain('custom trust settings cannot be used');
})->with([
    'empty' => [''],
    'invalid UTF-8' => ["\xFFbad"],
])->group('SPEC-004');

it('AC13: refused custom text is not saved custom settings', function (): void {
    $omeka = spec004Omeka();
    $list = dirname(__DIR__, 2).'/trust/C2PA-TRUST-LIST.pem';
    $shipped = (string) file_get_contents($list);
    try {
        file_put_contents($list, '');
        spec004SeenInContainer($omeka, BundledLists::C2PA, hash('sha256', ''));
        $response = spec004Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => 'not json']);
    } finally {
        file_put_contents($list, $shipped);
        spec004SeenInContainer($omeka, BundledLists::C2PA, BundledLists::FILES[BundledLists::C2PA]);
    }

    expect($response->status)->toBe(200)
        ->and(spec004Textarea($response->body))->toBe('not json')
        ->and($response->body)->toContain('The bundled trust lists cannot be used; new uploads and runs are checked without a trust list.')
        ->and($response->body)->not->toContain('custom trust settings cannot be used');
})->group('SPEC-004');

it('AC7: the block names the trust source', function (): void {
    $omeka = spec004Omeka();
    $media = spec004Upload($omeka, 'fixture-signed.jpg');
    $block = static fn (): string => (string) preg_replace('#.*(<div class="meta-group content-credentials">.*?<!-- /content-credentials -->).*#s', '$1', $omeka->get("/admin/media/{$media}")->body);

    expect($block())->toMatch('#Checked [^<]+ UTC with c2pa-verifier [^<]+</div><div class="value">Trust: the C2PA trust list of 2026-11-04, with DigiCert timestamps</div>#');
    $omeka->sql("UPDATE content_credentials_verdict SET trust = 'whatever' WHERE media_id = {$media}");
    expect($block())->toContain('Result unreadable')->and($block())->not->toContain('Intact');
    $omeka->sql("UPDATE content_credentials_verdict SET trust = NULL WHERE media_id = {$media}");
    expect($block())->toContain('Intact: signer not trusted')->and($block())->not->toContain('Trust:');
})->group('SPEC-004');

it('AC8: the configure page', function (): void {
    $omeka = spec004Omeka();
    spec004Store($omeka, 'content_credentials_custom_settings', '</textarea><script>alert(1)</script>');

    $page = $omeka->get(SPEC004_PAGE);

    expect($page->status)->toBe(200)
        ->and($page->body)->toContain('C2PA trust list of 2026-11-04 (commit 64b3f9c), last compared with the published list on 2026-11-06')
        ->and($page->body)->toMatch('#name="content_credentials_digicert" id="content-credentials-digicert" value="1" checked#')
        ->and($page->body)->not->toContain('<script>alert')
        ->and(spec004Textarea($page->body))->toBe('</textarea><script>alert(1)</script>')
        ->and($page->body)->toContain('Changes apply to new uploads and to runs started below; until such a run, media checked before keep their verdict.');

    expect(spec004Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => ''])->status)->toBe(302)
        ->and(spec004Setting($omeka, 'content_credentials_custom_settings'))->toBe('');

    foreach (['site_admin', 'editor'] as $role) {
        $email = "spec004-{$role}-".getmypid().'-'.random_int(0, PHP_INT_MAX).'@example.com';
        $id = OmekaInstallation::idFrom($omeka->api('POST', '/api/users', ['o:email' => $email, 'o:name' => $role, 'o:role' => $role, 'o:is_active' => true]));
        $omeka->sql("UPDATE user SET password_hash = '".password_hash('spec004-test-only', PASSWORD_DEFAULT)."' WHERE id = {$id}");
        $user = OmekaInstallation::php83();
        $user->logIn($email, 'spec004-test-only');
        $denied = $user->get(SPEC004_PAGE);
        $omeka->sql("DELETE FROM user WHERE id = {$id}");
        expect($denied->status)->not->toBe(200)
            ->and($denied->body)->not->toContain('content_credentials_custom_settings');
    }
})->group('SPEC-004');

it('AC9: no age warning while the comparison is recent', function (): void {
    $omeka = spec004Omeka();

    expect($omeka->get(SPEC004_PAGE)->body)->not->toContain('more than six months ago');
})->group('SPEC-004');

it('AC10: uninstall removes the settings', function (): void {
    $omeka = spec004Omeka();
    $omeka->moduleAction('uninstall', 'ContentCredentials');
    $before = $omeka->snapshot();
    $omeka->moduleAction('install', 'ContentCredentials');
    expect(spec004Configure($omeka, ['content_credentials_digicert' => '0', 'content_credentials_custom_settings' => spec004Custom()])->status)->toBe(302)
        ->and(spec004Setting($omeka, 'content_credentials_digicert'))->toBe('false');

    $omeka->moduleAction('uninstall', 'ContentCredentials');

    expect($omeka->snapshot())->toBe($before)
        ->and($omeka->sql("SELECT COUNT(*) FROM setting WHERE id LIKE 'content\\_credentials\\_%'"))->toBe('0');
    spec004Omeka();
})->group('SPEC-004');

it('AC11: the modules page links to the configure page, for global admins only', function (): void {
    $omeka = spec004Omeka();
    $link = 'href="/admin/module/configure?id=ContentCredentials"';
    $decoded = static fn (string $html): string => html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $admin = $omeka->get('/admin/module');

    $email = 'spec004-site_admin-'.getmypid().'-'.random_int(0, PHP_INT_MAX).'@example.com';
    $id = OmekaInstallation::idFrom($omeka->api('POST', '/api/users', ['o:email' => $email, 'o:name' => 'site_admin', 'o:role' => 'site_admin', 'o:is_active' => true]));
    $omeka->sql("UPDATE user SET password_hash = '".password_hash('spec004-test-only', PASSWORD_DEFAULT)."' WHERE id = {$id}");
    $siteAdmin = OmekaInstallation::php83();
    $siteAdmin->logIn($email, 'spec004-test-only');
    $theirs = $siteAdmin->get('/admin/module');
    $omeka->sql("DELETE FROM user WHERE id = {$id}");

    expect($admin->status)->toBe(200)
        ->and($decoded($admin->body))->toContain($link)
        ->and($theirs->status)->toBe(200)
        ->and($decoded($theirs->body))->toContain('Content Credentials (C2PA)')
        ->and($decoded($theirs->body))->not->toContain('configure?id=ContentCredentials');
})->group('SPEC-004');
