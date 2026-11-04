<?php

declare(strict_types=1);

/*
 * SPEC-003: the verdict on the admin media page, in its Details panel, in
 * the media browse, and as a filter, in the pinned Omeka S of tools/env/
 * (PHP 8.3). Rows come from real uploads (SPEC-002), or are set by the test
 * in the database for states and text no fixture produces.
 */

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Verifier\Verifier;

const SPEC003_HOSTILE = ['<script>alert(1)</script>', '"><img src=x onerror=alert(1)>', "'"];

/** The PHP 8.3 installation, logged in, the module installed and active. */
function spec003Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow('ContentCredentials');
    if ($row === null) {
        $omeka->moduleAction('install', 'ContentCredentials');
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', 'ContentCredentials');
    }

    return $omeka;
}

function spec003Upload(OmekaInstallation $omeka, string $path, string $filename, string $mediaType): int
{
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm($path, $filename, $mediaType)));

    return $media[0] ?? throw new RuntimeException("No media for {$filename}");
}

/** A signed JPEG uploaded; its row is then the test's to change. */
function spec003Media(OmekaInstallation $omeka): int
{
    return spec003Upload($omeka, VerifierCli::fixture('fixture-signed.jpg'), 'fixture-signed.jpg', 'image/jpeg');
}

/**
 * Sets columns of a media item's row; values are bound as SQL string
 * literals (hex, so any byte arrives as it is), null as NULL.
 *
 * @param  array<string, string|null>  $values
 */
function spec003Set(OmekaInstallation $omeka, int $mediaId, array $values): void
{
    $sets = [];
    foreach ($values as $column => $value) {
        $sets[] = $column.' = '.($value === null ? 'NULL' : "CONVERT(X'".bin2hex($value)."' USING utf8mb4)");
    }
    $omeka->sql('UPDATE content_credentials_verdict SET '.implode(', ', $sets).' WHERE media_id = '.$mediaId);
}

/** The module's block in a page: from its opening div to its end marker. */
function spec003Block(string $html): string
{
    $start = strpos($html, '<div class="meta-group content-credentials">');
    $end = strpos($html, '<!-- /content-credentials -->');
    if ($start === false || $end === false) {
        throw new RuntimeException('No Content Credentials block on the page.');
    }

    return substr($html, $start, $end - $start);
}

/**
 * The browse's cell of the module's column per media id, as text.
 *
 * @return array<int, string>
 */
function spec003Cells(string $html): array
{
    $cells = [];
    preg_match_all('#<tr>(.*?)</tr>#s', $html, $rows);
    foreach ($rows[1] as $row) {
        if (preg_match('#name="resource_ids\[\]" value="(\d+)"#', $row, $id) === 1
            && preg_match('#<td class="column-content_credentials">(.*?)</td>#s', $row, $cell) === 1) {
            $cells[(int) $id[1]] = trim(html_entity_decode(strip_tags($cell[1]), ENT_QUOTES | ENT_HTML5));
        }
    }

    return $cells;
}

/** @return list<int> */
function spec003BrowseIds(OmekaInstallation $omeka, string $query): array
{
    preg_match_all('#name="resource_ids\[\]" value="(\d+)"#', $omeka->get('/admin/media?per_page=1000'.$query)->body, $m);

    return array_map('intval', $m[1]);
}

it('AC1: the column shows one headline per state', function (): void {
    $omeka = spec003Omeka();
    $valid = spec003Media($omeka);
    $trusted = spec003Media($omeka);
    spec003Set($omeka, $trusted, ['state' => 'Trusted']);
    $altered = VerifierCli::alteredJpeg();
    try {
        $invalid = spec003Upload($omeka, $altered, 'altered.jpg', 'image/jpeg');
    } finally {
        unlink($altered);
    }
    $none = spec003Upload($omeka, VerifierCli::fixture('fixture-unsigned.png'), 'fixture-unsigned.png', 'image/png');
    $error = spec003Media($omeka);
    spec003Set($omeka, $error, ['state' => 'error', 'reason' => 'unreadable', 'format' => null]);
    $notChecked = spec003Upload($omeka, VerifierCli::fixture('auth-1-1.gif'), 'auth-1-1.gif', 'image/gif');

    $page = $omeka->get('/admin/media');
    $cells = spec003Cells($page->body);

    expect($page->body)->toContain('<th class="column-content_credentials">Content Credentials</th>')
        ->and($cells[$trusted] ?? null)->toBe('Verified: trusted signer')
        ->and($cells[$valid] ?? null)->toBe('Intact: signer not trusted')
        ->and($cells[$invalid] ?? null)->toBe('Does not verify')
        ->and($cells[$none] ?? null)->toBe('No Content Credentials')
        ->and($cells[$error] ?? null)->toBe('Could not be checked')
        ->and($cells[$notChecked] ?? null)->toBe('Not checked');
})->group('SPEC-003');

it('AC2: the media page and the Details panel show a signed file', function (): void {
    $omeka = spec003Omeka();
    $media = spec003Media($omeka);

    foreach (["/admin/media/{$media}", "/admin/media/{$media}/show-details"] as $path) {
        $block = spec003Block($omeka->get($path)->body);
        expect($block)->toContain('<h4>Content Credentials</h4>')
            ->and($block)->toContain('Intact: signer not trusted')
            ->and($block)->toContain('Signed by C2PA Signer (C2PA Test Signing Cert)')
            ->and($block)->toMatch('#Checked \d{4}-\d\d-\d\d \d\d:\d\d:\d\d UTC with c2pa-verifier '.preg_quote(Verifier::VERSION, '#').'#');
    }
})->group('SPEC-003');

it('AC3: an invalid file shows its codes in order', function (): void {
    $omeka = spec003Omeka();
    $altered = VerifierCli::alteredJpeg();
    try {
        $media = spec003Upload($omeka, $altered, 'altered.jpg', 'image/jpeg');
    } finally {
        unlink($altered);
    }

    $block = spec003Block($omeka->get("/admin/media/{$media}")->body);

    expect($block)->toContain('Does not verify')
        ->and($block)->toMatch('#Codes:.*signingCredential\.untrusted.*assertion\.dataHash\.mismatch#s');
})->group('SPEC-003');

it('AC4: a manifest by URL is text, never a link', function (): void {
    $omeka = spec003Omeka();
    $media = spec003Upload($omeka, VerifierCli::fixture('fixture-unsigned.jpg'), 'fixture-unsigned.jpg', 'image/jpeg');
    spec003Set($omeka, $media, ['remote_manifest_url' => 'https://example.com/manifest.c2pa']);

    $block = spec003Block($omeka->get("/admin/media/{$media}")->body);

    expect($block)->toContain('No Content Credentials')
        ->and($block)->toContain('Refers to Content Credentials elsewhere (not checked): https://example.com/manifest.c2pa')
        ->and($block)->not->toContain('<a')
        ->and($block)->not->toContain('href');
})->group('SPEC-003');

it('AC5: an error shows its reason in words', function (): void {
    $omeka = spec003Omeka();
    $media = spec003Media($omeka);
    $words = [
        'interrupted' => 'the check did not finish',
        'unreadable' => 'the file could not be read',
        'exception' => 'the verifier failed',
        'requirements' => 'this server&#039;s PHP is older than 8.3',
    ];

    foreach ($words as $reason => $text) {
        spec003Set($omeka, $media, ['state' => 'error', 'reason' => $reason]);
        $block = spec003Block($omeka->get("/admin/media/{$media}")->body);
        expect($block)->toContain('Could not be checked')
            ->and($block)->toContain('Reason: '.$text);
    }
})->group('SPEC-003');

it('AC6: no row and unreadable rows show no verdict', function (): void {
    $omeka = spec003Omeka();
    $headlines = ['Verified: trusted signer', 'Intact: signer not trusted', 'Does not verify', 'No Content Credentials', 'Could not be checked'];
    $notChecked = spec003Upload($omeka, VerifierCli::fixture('auth-1-1.gif'), 'auth-1-1.gif', 'image/gif');
    $unreadable = [];
    foreach ([['state' => 'Maybe'], ['state' => 'Invalid', 'codes' => 'not json'], ['state' => 'Invalid', 'codes' => '[1]'], ['state' => 'error', 'reason' => 'whatever']] as $values) {
        $media = spec003Media($omeka);
        spec003Set($omeka, $media, $values);
        $unreadable[] = $media;
    }
    $cells = spec003Cells($omeka->get('/admin/media')->body);

    expect($cells[$notChecked] ?? null)->toBe('Not checked')
        ->and(spec003Block($omeka->get("/admin/media/{$notChecked}")->body))->toContain('Not checked');
    foreach ($unreadable as $media) {
        $block = spec003Block($omeka->get("/admin/media/{$media}")->body);
        expect($cells[$media] ?? null)->toBe('Result unreadable')
            ->and($block)->toContain('Result unreadable');
        foreach ($headlines as $headline) {
            expect($block)->not->toContain($headline);
        }
    }
})->group('SPEC-003');

it('AC7: hostile text is escaped on every page', function (): void {
    $omeka = spec003Omeka();
    $text = implode(' ', SPEC003_HOSTILE);
    $valid = spec003Media($omeka);
    spec003Set($omeka, $valid, ['signer_common_name' => $text, 'signer_issuer' => $text, 'signed_at' => $text]);
    $invalid = spec003Media($omeka);
    spec003Set($omeka, $invalid, ['state' => 'Invalid', 'codes' => json_encode(SPEC003_HOSTILE, JSON_THROW_ON_ERROR)]);
    $none = spec003Media($omeka);
    spec003Set($omeka, $none, ['state' => 'none', 'remote_manifest_url' => $text]);

    $pages = ['/admin/media'];
    foreach ([$valid, $invalid, $none] as $media) {
        $pages[] = "/admin/media/{$media}";
        $pages[] = "/admin/media/{$media}/show-details";
    }
    foreach ($pages as $path) {
        $body = $omeka->get($path)->body;
        expect($body)->not->toContain('<script>alert')
            ->and($body)->not->toContain('<img src=x')
            ->and($body)->not->toMatch('#<[^>]*onerror=#', $path);
    }
    expect(spec003Block($omeka->get("/admin/media/{$valid}")->body))->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and(spec003Block($omeka->get("/admin/media/{$invalid}")->body))->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and(spec003Block($omeka->get("/admin/media/{$none}")->body))->toContain('&quot;&gt;&lt;img src=x onerror=alert(1)&gt;');
})->group('SPEC-003');

it('AC8: control and direction characters are made visible', function (): void {
    $omeka = spec003Omeka();
    $media = spec003Media($omeka);
    spec003Set($omeka, $media, ['signer_common_name' => "C2PA\x00\x1B\u{85}\u{202E}Signer"]);

    $block = spec003Block($omeka->get("/admin/media/{$media}")->body);

    expect($block)->toContain("C2PA\u{FFFD}\u{FFFD}\u{FFFD}\u{FFFD}Signer");
    foreach (["\x00", "\x1B", "\u{85}", "\u{202E}"] as $character) {
        expect(str_contains($block, $character))->toBeFalse();
    }
})->group('SPEC-003');

it('AC9: the filter lists exactly the media in a state', function (): void {
    $omeka = spec003Omeka();
    spec003Media($omeka);
    spec003Upload($omeka, VerifierCli::fixture('fixture-unsigned.webp'), 'fixture-unsigned.webp', 'image/webp');
    spec003Set($omeka, spec003Media($omeka), ['state' => 'Trusted']);
    spec003Set($omeka, spec003Media($omeka), ['state' => 'Invalid', 'codes' => '["assertion.dataHash.mismatch"]']);
    spec003Set($omeka, spec003Media($omeka), ['state' => 'error', 'reason' => 'exception']);
    $headlines = ['Trusted' => 'Verified: trusted signer', 'Valid' => 'Intact: signer not trusted', 'Invalid' => 'Does not verify', 'none' => 'No Content Credentials', 'error' => 'Could not be checked'];

    foreach ($headlines as $state => $headline) {
        $expected = $omeka->sql("SELECT id FROM media WHERE id IN (SELECT media_id FROM content_credentials_verdict WHERE state = '{$state}') ORDER BY id DESC");
        $listed = spec003BrowseIds($omeka, '&content_credentials_state='.$state);
        expect($listed)->toBe(array_map('intval', explode("\n", $expected)), $state)
            ->and($omeka->get('/admin/media?content_credentials_state='.$state)->body)
            ->toMatch('#<span class="filter-label">Content Credentials</span>\s*<span class="filter-value">'.preg_quote($headline, '#').'</span>#', $state);
    }
    $search = $omeka->get('/admin/media/search')->body;
    expect($search)->toContain('name="content_credentials_state"');
    foreach ($headlines as $state => $headline) {
        expect($search)->toContain('<option value="'.$state.'">'.$headline.'</option>');
    }
})->group('SPEC-003');

it('AC10: a filter value that is not a state matches nothing', function (): void {
    $omeka = spec003Omeka();
    spec003Media($omeka);

    expect(spec003BrowseIds($omeka, '&content_credentials_state='))->toBe(spec003BrowseIds($omeka, ''));
    foreach (['&content_credentials_state=Maybe', '&content_credentials_state%5B%5D=Valid'] as $query) {
        $body = $omeka->get('/admin/media?per_page=1000'.$query)->body;
        expect(spec003BrowseIds($omeka, $query))->toBe([], $query)
            ->and($body)->toMatch('#<span class="filter-label">Content Credentials</span>\s*<span class="filter-value">\(unknown value\)</span>#', $query)
            // the module's output, the active filters; Omeka's own "Advanced
            // search" link carries the query, escaped, and is not ours
            ->and(preg_match('#<div class="search-filters">.*?</div>\s*</div>#s', $body, $filters))->toBe(1)
            ->and($filters[0] ?? '')->not->toContain('Maybe');
    }
})->group('SPEC-003');

it('AC11: nothing changes outside the admin interface', function (): void {
    $omeka = spec003Omeka();
    $media = spec003Media($omeka);
    $slug = 'spec003-'.getmypid().'-'.$media;
    $site = OmekaInstallation::idFrom($omeka->api('POST', '/api/sites', ['o:title' => 'SPEC-003', 'o:slug' => $slug, 'o:theme' => 'default', 'o:is_public' => true]));
    $item = (int) $omeka->sql('SELECT item_id FROM media WHERE id = '.$media);
    expect($omeka->api('PATCH', '/api/items/'.$item, ['o:site' => [['o:id' => $site]]])->status)->toBe(200);

    $public = OmekaInstallation::php83()->get("/s/{$slug}/media/{$media}");
    $visitor = OmekaInstallation::php83();
    $filtered = $visitor->get('/api/media?content_credentials_state=Valid&per_page=1000');
    $unfiltered = $visitor->get('/api/media?per_page=1000');

    expect($public->status)->toBe(200)
        ->and($public->body)->not->toContain('content-credentials')
        ->and($public->body)->not->toContain('Intact: signer not trusted')
        ->and($filtered->status)->toBe(200)
        ->and($filtered->body)->toBe($unfiltered->body);
})->group('SPEC-003');
