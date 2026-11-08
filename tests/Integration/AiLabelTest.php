<?php

declare(strict_types=1);

/*
 * SPEC-007: the AI label, in the pinned Omeka S of tools/env/ (PHP 8.3). Rows
 * come from real uploads with the module's settings, or are set by the test
 * for states no fixture holds for long. The oracle is vendor/bin/c2pa-verify
 * with the settings the module used.
 */

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;
use ContentCredentials\Trust\TrustChoice;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

const SPEC007_GENERATED = 'AI-generated (signed)';
const SPEC007_EDITED = 'AI-edited (signed)';
const SPEC007_TITAN = 'amazon-20240925-titan-g1.png';
const SPEC007_CHAIN = 'c2pa-verifier-ai-parent-chain.png';
const SPEC007_OCSP = 'c2pa-rs-ocsp.jpg';
const SPEC007_SIGNED = 'fixture-signed.jpg';
const SPEC007_PIXEL = 'google-20250919-pixel10-npld-picnic-table.jpg';
const SPEC007_UNSIGNED = 'fixture-unsigned.png';
const SPEC007_CONFIGURE = '/admin/module/configure?id=ContentCredentials';
const SPEC007_CSRF = 'module_ContentCredentials_configure_csrf';
const SPEC007_RECHECK_CLASS = 'ContentCredentials\\\\Omeka\\\\RecheckJob'; // as an SQL literal

/**
 * The PHP 8.3 installation, logged in, the module active with the column
 * (installed again when it lacks it), no trust setting stored.
 */
function spec007Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow('ContentCredentials');
    if ($row !== null && $omeka->sql("SHOW COLUMNS FROM content_credentials_verdict LIKE 'ai_statement'") === '') {
        $omeka->moduleAction('uninstall', 'ContentCredentials');
        $row = null;
    }
    if ($row === null) {
        $omeka->moduleAction('install', 'ContentCredentials');
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', 'ContentCredentials');
    }
    expect($omeka->sql("SHOW COLUMNS FROM content_credentials_verdict LIKE 'ai_statement'"))->toStartWith('ai_statement');
    $omeka->sql("DELETE FROM setting WHERE id LIKE 'content\\_credentials\\_%'");

    return $omeka;
}

function spec007Upload(OmekaInstallation $omeka, string $path, ?string $filename = null): int
{
    $filename ??= basename($path);
    $type = str_ends_with($filename, '.png') ? 'image/png' : 'image/jpeg';
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm($path, $filename, $type)));

    return $media[0] ?? throw new RuntimeException("No media for {$filename}");
}

function spec007Fixture(OmekaInstallation $omeka, string $fixture): int
{
    return spec007Upload($omeka, VerifierCli::fixture($fixture));
}

/**
 * Sets columns of a media item's row; values are bound as SQL string
 * literals (hex, so any byte arrives as it is), null as NULL.
 *
 * @param  array<string, string|null>  $values
 */
function spec007Set(OmekaInstallation $omeka, int $mediaId, array $values): void
{
    $sets = [];
    foreach ($values as $column => $value) {
        $sets[] = $column.' = '.($value === null ? 'NULL' : "CONVERT(X'".bin2hex($value)."' USING utf8mb4)");
    }
    $omeka->sql('UPDATE content_credentials_verdict SET '.implode(', ', $sets).' WHERE media_id = '.$mediaId);
}

function spec007Statement(OmekaInstallation $omeka, int $mediaId): string
{
    return $omeka->sql('SELECT IFNULL(ai_statement, \'NULL\') FROM content_credentials_verdict WHERE media_id = '.$mediaId);
}

function spec007Block(string $html): string
{
    $start = strpos($html, '<div class="meta-group content-credentials">');
    $end = strpos($html, '<!-- /content-credentials -->');
    if ($start === false || $end === false) {
        throw new RuntimeException('No Content Credentials block on the page.');
    }

    return substr($html, $start, $end - $start);
}

/** The browse's cell of the module's column for one media item, as markup. */
function spec007Cell(OmekaInstallation $omeka, int $mediaId): string
{
    preg_match_all('#<tr>(.*?)</tr>#s', $omeka->get('/admin/media?per_page=1000000&sort_by=id&sort_order=desc')->body, $rows);
    foreach ($rows[1] as $row) {
        if (preg_match('#name="resource_ids\[\]" value="(\d+)"#', $row, $id) === 1 && (int) $id[1] === $mediaId
            && preg_match('#<td class="column-content_credentials">(.*?)</td>#s', $row, $cell) === 1) {
            return $cell[1];
        }
    }
    throw new RuntimeException("No browse cell for media {$mediaId}");
}

/** @return list<int> */
function spec007BrowseIds(OmekaInstallation $omeka, string $query): array
{
    preg_match_all('#name="resource_ids\[\]" value="(\d+)"#', $omeka->get('/admin/media?per_page=1000000'.$query)->body, $m);

    return array_map('intval', $m[1]);
}

/** A PNG chunk's length, big-endian at its offset. */
function spec007Length(string $bytes, int $offset): int
{
    return (ord($bytes[$offset]) << 24) | (ord($bytes[$offset + 1]) << 16) | (ord($bytes[$offset + 2]) << 8) | ord($bytes[$offset + 3]);
}

/** A file flipped at one bit of its image data, in the system's temporary directory; the caller deletes it. */
function spec007Tampered(string $fixture): string
{
    $bytes = (string) file_get_contents(VerifierCli::fixture($fixture));
    if (str_ends_with($fixture, '.png')) {
        // The first IDAT chunk, middle byte, with its CRC recomputed.
        $offset = 8;
        while (substr($bytes, $offset + 4, 4) !== 'IDAT') {
            $offset += 12 + spec007Length($bytes, $offset);
        }
        $length = spec007Length($bytes, $offset);
        $at = $offset + 8 + intdiv($length, 2);
        $bytes[$at] = chr(ord($bytes[$at]) ^ 1);
        $bytes = substr_replace($bytes, pack('N', crc32(substr($bytes, $offset + 4, 4 + $length))), $offset + 8 + $length, 4);
    } else {
        // Scan data, 64 bytes before the end (as VerifierCli::alteredJpeg()).
        $at = strlen($bytes) - 64;
        $bytes[$at] = chr(ord($bytes[$at]) ^ 1);
    }
    $path = sys_get_temp_dir().'/spec007-tampered-'.getmypid().'-'.$fixture;
    file_put_contents($path, $bytes);

    return $path;
}

it('AC1: the real files', function (string $fixture, string $state, string $statement, ?string $label): void {
    $omeka = spec007Omeka();
    $media = spec007Fixture($omeka, $fixture);
    $cli = VerifierCli::report(VerifierCli::fixture($fixture), VerifierCli::settingsFile());

    expect($omeka->verdictRow($media)['state'] ?? null)->toBe($state)
        ->and($cli['has_manifest'] === true ? $cli['validation_state'] : 'none')->toBe($state)
        ->and(spec007Statement($omeka, $media))->toBe($statement);

    $block = spec007Block($omeka->get("/admin/media/{$media}")->body);
    $cell = spec007Cell($omeka, $media);
    foreach ([SPEC007_GENERATED, SPEC007_EDITED] as $any) {
        expect(str_contains($block, $any))->toBe($any === $label, "block, {$any}")
            ->and(str_contains($cell, $any))->toBe($any === $label, "cell, {$any}");
    }
})->with([
    [SPEC007_TITAN, 'Valid', 'generated', SPEC007_GENERATED],
    [SPEC007_CHAIN, 'Valid', 'generated', SPEC007_GENERATED],
    [SPEC007_OCSP, 'Valid', 'edited', SPEC007_EDITED],
    [SPEC007_SIGNED, 'Valid', 'NULL', null],
    [SPEC007_PIXEL, 'Trusted', 'NULL', null],
    [SPEC007_UNSIGNED, 'none', 'NULL', null],
])->group('SPEC-007');

it('AC7: a file that does not verify has no label', function (string $case): void {
    $omeka = spec007Omeka();
    $settings = VerifierCli::settingsFile();
    if ($case === SPEC007_TITAN) {
        $omeka->sql("INSERT INTO setting (id, value) VALUES ('content_credentials_digicert', 'false')");
        $settings = VerifierCli::settingsFile(false);
        $path = VerifierCli::fixture($case);
    } else {
        $path = spec007Tampered($case);
    }
    try {
        $media = spec007Upload($omeka, $path, $case);
        $cli = VerifierCli::report($path, $settings);
    } finally {
        if ($case !== SPEC007_TITAN) {
            unlink($path);
        }
        $omeka->sql("DELETE FROM setting WHERE id = 'content_credentials_digicert'");
    }
    $row = $omeka->verdictRow($media) ?? [];

    expect($row['state'] ?? null)->toBe('Invalid')
        ->and($cli['validation_state'])->toBe('Invalid')
        ->and($row['codes'] ?? null)->toBe(json_encode(VerifierCli::codes($cli), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
        ->and(spec007Statement($omeka, $media))->toBe('NULL');
    foreach ([spec007Block($omeka->get("/admin/media/{$media}")->body), spec007Cell($omeka, $media)] as $markup) {
        expect($markup)->not->toContain('AI-generated')->and($markup)->not->toContain('AI-edited');
    }
})->with([SPEC007_TITAN, SPEC007_CHAIN, SPEC007_OCSP])->group('SPEC-007');

it('AC8: a re-check sets the column, either way, and leaves the evidence the verifier\'s', function (): void {
    $omeka = spec007Omeka();
    $signed = spec007Fixture($omeka, SPEC007_SIGNED);
    $titan = spec007Fixture($omeka, SPEC007_TITAN);
    spec007Set($omeka, $signed, ['ai_statement' => 'generated']);
    spec007Set($omeka, $titan, ['ai_statement' => null]);

    $omeka->sql("UPDATE job SET status = 'stopped' WHERE class = '".SPEC007_RECHECK_CLASS."' AND status IN ('starting', 'in_progress', 'stopping')");
    $before = (int) $omeka->sql("SELECT IFNULL(MAX(id), 0) FROM job WHERE class = '".SPEC007_RECHECK_CLASS."'");
    preg_match('/name="'.SPEC007_CSRF.'" value="([^"]+)"/', $omeka->get(SPEC007_CONFIGURE)->body, $csrf);
    expect($omeka->post(SPEC007_CONFIGURE, [SPEC007_CSRF => $csrf[1] ?? '', 'content_credentials_recheck' => 'all'])->status)->toBe(302);
    $job = (int) $omeka->sql("SELECT IFNULL(MAX(id), 0) FROM job WHERE class = '".SPEC007_RECHECK_CLASS."'");
    expect($job)->toBeGreaterThan($before);
    $end = time() + 300;
    do {
        $status = $omeka->sql("SELECT status FROM job WHERE id = {$job}");
        usleep(200_000);
    } while (! in_array($status, ['completed', 'stopped', 'error'], true) && time() < $end);

    expect($status)->toBe('completed')
        ->and(spec007Statement($omeka, $signed))->toBe('NULL')
        ->and(spec007Statement($omeka, $titan))->toBe('generated');

    $root = dirname(__DIR__, 2);
    $settings = TrustChoice::bundledJson(true, $root.'/trust');
    $document = $omeka->sql("SELECT HEX(document) FROM content_credentials_evidence WHERE media_id = {$titan} ORDER BY id DESC LIMIT 1");
    $stored = (string) hex2bin($document);
    $at = null;
    $decoded = json_decode($stored, true);
    foreach (is_array($decoded) && is_array($decoded['manifests'] ?? null) ? $decoded['manifests'] : [] as $manifest) {
        if (is_array($manifest) && ($manifest['time_source'] ?? null) === 'clock' && is_int($manifest['judged_at'] ?? null)) {
            $at = new DateTimeImmutable('@'.$manifest['judged_at']);
        }
    }
    $stream = fopen(VerifierCli::fixture(SPEC007_TITAN), 'rb');
    if ($stream === false) {
        throw new RuntimeException('Cannot open Titan');
    }
    try {
        $expected = (new Verifier)->verify($stream, TrustSettings::fromJson($settings), $at)->evidenceToJson();
    } finally {
        fclose($stream);
    }
    if ($at !== null) {
        $expected = str_replace('"time_source": "given"', '"time_source": "clock"', $expected);
    }

    expect($stored)->toBe($expected)
        ->and($stored)->not->toContain('ai_statement')
        ->and($stored)->not->toContain('generated');
})->group('SPEC-007');

it('AC9: the label follows the state on every page', function (string $state, string $statement, ?string $label): void {
    $omeka = spec007Omeka();
    $media = spec007Fixture($omeka, SPEC007_SIGNED);
    spec007Set($omeka, $media, ['state' => $state, 'ai_statement' => $statement, 'reason' => $state === 'error' ? 'exception' : null, 'remote_manifest_url' => null]);

    $block = spec007Block($omeka->get("/admin/media/{$media}")->body);
    $details = spec007Block($omeka->get("/admin/media/{$media}/show-details")->body);
    $cell = spec007Cell($omeka, $media);
    if ($label === null) {
        foreach ([$block, $details, $cell] as $markup) {
            expect($markup)->not->toContain('AI-generated')->and($markup)->not->toContain('AI-edited');
        }

        return;
    }
    $first = '#</h4><div class="value">[^<]+</div><div class="value">'.preg_quote($label, '#').'</div>#';
    // Amendment 4: one element, so that Omeka's flex cell (tablesaw-cell-content)
    // keeps the label on its own line under the headline.
    $one = '#^<span class="content-credentials-cell"><span class="content-credentials-headline">[^<]+</span>'
        .'<br><span class="content-credentials-ai">'.preg_quote($label, '#').'</span></span>$#';
    expect($block)->toMatch($first)
        ->and($details)->toMatch($first)
        ->and(trim($cell))->toMatch($one);
})->with([
    ['Trusted', 'generated', SPEC007_GENERATED],
    ['Valid', 'generated', SPEC007_GENERATED],
    ['Trusted', 'edited', SPEC007_EDITED],
    ['Valid', 'edited', SPEC007_EDITED],
    ['Invalid', 'generated', null],
    ['Invalid', 'edited', null],
    ['none', 'generated', null],
    ['none', 'edited', null],
    ['error', 'generated', null],
    ['error', 'edited', null],
])->group('SPEC-007');

it('AC9: a value the module never writes is unreadable and never echoed', function (string $value): void {
    $omeka = spec007Omeka();
    $media = spec007Fixture($omeka, SPEC007_SIGNED);
    spec007Set($omeka, $media, ['ai_statement' => $value]);

    $block = spec007Block($omeka->get("/admin/media/{$media}")->body);
    $cell = spec007Cell($omeka, $media);

    expect($block)->toContain('Result unreadable')
        ->and($cell)->toContain('Result unreadable');
    foreach ([$block, $cell] as $markup) {
        expect($markup)->not->toContain('AI-generated')->and($markup)->not->toContain('AI-edited');
        if ($value !== '') {
            expect($markup)->not->toContain($value)->and($markup)->not->toContain(htmlspecialchars($value, ENT_QUOTES));
        }
    }
    if (str_starts_with($value, '<')) {
        $page = $omeka->get("/admin/media/{$media}")->body;
        expect($page)->not->toContain($value)->and($page)->not->toContain(htmlspecialchars($value, ENT_QUOTES));
    }
})->with(['true', '', '<i>spec007</i>'])->group('SPEC-007');

it('AC10: the filter', function (string $value, string $label): void {
    $omeka = spec007Omeka();
    $generated = spec007Fixture($omeka, SPEC007_SIGNED);
    $edited = spec007Fixture($omeka, SPEC007_SIGNED);
    $without = spec007Fixture($omeka, SPEC007_SIGNED);
    $trustedGenerated = spec007Fixture($omeka, SPEC007_SIGNED);
    spec007Set($omeka, $generated, ['ai_statement' => 'generated']);
    spec007Set($omeka, $edited, ['ai_statement' => 'edited']);
    spec007Set($omeka, $trustedGenerated, ['state' => 'Trusted', 'ai_statement' => 'generated']);

    $expected = $omeka->sql("SELECT id FROM media WHERE id IN (SELECT media_id FROM content_credentials_verdict WHERE ai_statement = '{$value}' AND state IN ('Trusted', 'Valid')) ORDER BY id");
    $listed = spec007BrowseIds($omeka, '&sort_by=id&sort_order=asc&content_credentials_ai='.$value);
    $wanted = $value === 'generated' ? [$generated, $trustedGenerated] : [$edited];

    expect($listed)->toBe(array_map('intval', explode("\n", $expected)))
        ->and(array_values(array_intersect($listed, [$generated, $edited, $without, $trustedGenerated])))->toBe($wanted)
        ->and($omeka->get('/admin/media?content_credentials_ai='.$value)->body)
        ->toMatch('#<span class="filter-label">Content Credentials</span>\s*<span class="filter-value">'.preg_quote($label, '#').'</span>#');

    $both = spec007BrowseIds($omeka, '&sort_by=id&sort_order=asc&content_credentials_state=Valid&content_credentials_ai='.$value);
    expect(array_values(array_intersect($both, [$generated, $edited, $without, $trustedGenerated])))
        ->toBe($value === 'generated' ? [$generated] : [$edited]);

    $search = $omeka->get('/admin/media/search')->body;
    expect($search)->toContain('name="content_credentials_ai"')
        ->and($search)->toContain('<option value="generated">'.SPEC007_GENERATED.'</option>')
        ->and($search)->toContain('<option value="edited">'.SPEC007_EDITED.'</option>');
})->with([['generated', SPEC007_GENERATED], ['edited', SPEC007_EDITED]])->group('SPEC-007');

it('AC11: the filter follows the state too', function (): void {
    $omeka = spec007Omeka();
    $invalid = spec007Fixture($omeka, SPEC007_SIGNED);
    $valid = spec007Fixture($omeka, SPEC007_SIGNED);
    spec007Set($omeka, $invalid, ['state' => 'Invalid', 'codes' => '["assertion.dataHash.mismatch"]', 'ai_statement' => 'generated']);
    spec007Set($omeka, $valid, ['ai_statement' => 'generated']);

    $listed = spec007BrowseIds($omeka, '&content_credentials_ai=generated');

    expect($listed)->toContain($valid)->and($listed)->not->toContain($invalid);
})->group('SPEC-007');

it('AC12: a filter value that is not a label matches nothing', function (): void {
    $omeka = spec007Omeka();
    spec007Fixture($omeka, SPEC007_SIGNED);

    expect(spec007BrowseIds($omeka, '&content_credentials_ai='))->toBe(spec007BrowseIds($omeka, ''));
    foreach (['&content_credentials_ai=Maybe', '&content_credentials_ai%5B%5D=generated'] as $query) {
        $body = $omeka->get('/admin/media?per_page=1000'.$query)->body;
        expect(spec007BrowseIds($omeka, $query))->toBe([], $query)
            ->and($body)->toMatch('#<span class="filter-label">Content Credentials</span>\s*<span class="filter-value">\(unknown value\)</span>#')
            ->and(preg_match('#<div class="search-filters">.*?</div>\s*</div>#s', $body, $filters))->toBe(1)
            ->and($filters[0] ?? '')->not->toContain('Maybe');
    }
})->group('SPEC-007');

it('AC13: only the admin side', function (): void {
    $omeka = spec007Omeka();
    $media = spec007Fixture($omeka, SPEC007_SIGNED); // no statement: a filter applied would hide it
    $labelled = spec007Fixture($omeka, SPEC007_SIGNED);
    spec007Set($omeka, $labelled, ['ai_statement' => 'generated']);
    $slug = 'spec007-'.getmypid().'-'.$media;
    $site = OmekaInstallation::idFrom($omeka->api('POST', '/api/sites', ['o:title' => 'SPEC-007', 'o:slug' => $slug, 'o:theme' => 'default', 'o:is_public' => true]));
    foreach ([$media, $labelled] as $each) {
        $item = (int) $omeka->sql('SELECT item_id FROM media WHERE id = '.$each);
        expect($omeka->api('PATCH', '/api/items/'.$item, ['o:site' => [['o:id' => $site]]])->status)->toBe(200);
    }

    $visitor = OmekaInstallation::php83();
    $page = $visitor->get("/s/{$slug}/media/{$labelled}?content_credentials_ai=generated");
    expect($page->status)->toBe(200)
        ->and($page->body)->not->toContain('AI-generated')
        ->and($page->body)->not->toContain('content-credentials');

    $newest = '&per_page=1000&sort_by=id&sort_order=desc';
    $api = $visitor->get('/api/media?content_credentials_ai=generated'.$newest);
    expect($api->status)->toBe(200)
        ->and($api->body)->toContain('"o:id":'.$media)
        ->and($api->body)->toBe($visitor->get('/api/media?content_credentials_ai='.$newest)->body);
})->group('SPEC-007');
