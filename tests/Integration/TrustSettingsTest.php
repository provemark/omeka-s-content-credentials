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

    expect($row)->toBe(['Trusted', '[]', 'c2pa-2026-10-17+digicert'])
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

    expect($on[0])->toBe(['Valid', '[]', 'c2pa-2026-10-17+digicert'])
        ->and($on[1])->toBe(['Valid', '[]'])
        ->and($off[0][0])->toBe('Invalid')
        ->and($off[0][2])->toBe('c2pa-2026-10-17')
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
        ->and($page)->toContain('The saved custom trust settings cannot be used; new uploads are checked without a trust list.')
        ->and($page)->not->toContain('not json <b>');
})->group('SPEC-004');

it('AC7: the block names the trust source', function (): void {
    $omeka = spec004Omeka();
    $media = spec004Upload($omeka, 'fixture-signed.jpg');
    $block = static fn (): string => (string) preg_replace('#.*(<div class="meta-group content-credentials">.*?<!-- /content-credentials -->).*#s', '$1', $omeka->get("/admin/media/{$media}")->body);

    expect($block())->toMatch('#Checked [^<]+ UTC with c2pa-verifier [^<]+</div><div class="value">Trust: the C2PA trust list of 2026-10-17, with DigiCert timestamps</div>#');
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
        ->and($page->body)->toContain('C2PA trust list of 2026-10-17 (commit 47009ee), last compared with the published list on 2026-11-04')
        ->and($page->body)->toMatch('#name="content_credentials_digicert" id="content-credentials-digicert" value="1" checked#')
        ->and($page->body)->not->toContain('<script>alert')
        ->and(spec004Textarea($page->body))->toBe('</textarea><script>alert(1)</script>')
        ->and($page->body)->toContain('Changes apply to new uploads; media checked before keep their verdict.');

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
