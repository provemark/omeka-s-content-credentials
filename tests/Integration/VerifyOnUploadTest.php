<?php

declare(strict_types=1);

/*
 * SPEC-002: every new JPEG, PNG or WebP media item is verified once and its
 * verdict stored, in the pinned Omeka S of tools/env/ (PHP 8.3). The oracle
 * is vendor/bin/c2pa-verify on the same bytes, run here on the host.
 */

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Verifier\Verifier;

const SPEC002_TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

/** The PHP 8.3 installation, logged in, with the module installed and active. */
function spec002Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow('ContentCredentials');
    if ($row === null) {
        $omeka->moduleAction('install', 'ContentCredentials');
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', 'ContentCredentials');
    }
    expect($omeka->moduleRow('ContentCredentials')['is_active'] ?? null)->toBe('1');

    return $omeka;
}

/** Uploads one file through the admin item form and returns its one media id. */
function spec002Upload(OmekaInstallation $omeka, string $path, string $filename, string $mediaType): int
{
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm($path, $filename, $mediaType)));
    expect($media)->toHaveCount(1);

    return $media[0];
}

it('AC1: a signed file gets the CLI\'s verdict', function (string $extension): void {
    $omeka = spec002Omeka();
    $path = VerifierCli::fixture("fixture-signed.{$extension}");
    $cli = VerifierCli::report($path);
    $signature = VerifierCli::signature($cli);

    $row = $omeka->verdictRow(spec002Upload($omeka, $path, "fixture-signed.{$extension}", SPEC002_TYPES[$extension]));

    expect($row)->not->toBeNull()
        ->and($row['state'] ?? null)->toBe($cli['validation_state'])
        ->and($row['state'] ?? null)->toBe('Valid')
        ->and($row['format'] ?? null)->toBe($cli['format'])
        ->and($row['signer_issuer'] ?? null)->toBe($signature['issuer'] ?? null)
        ->and($row['signer_common_name'] ?? null)->toBe($signature['common_name'] ?? null)
        ->and($row['signed_at'] ?? null)->toBe($signature['time'] ?? null)
        ->and($row['codes'] ?? null)->toBe('[]')
        ->and($row['reason'] ?? null)->toBeNull()
        ->and($row['checked_sha256'] ?? null)->toBe(hash_file('sha256', $path))
        ->and($row['verifier'] ?? null)->toBe(Verifier::VERSION);
})->with(array_keys(SPEC002_TYPES))->group('SPEC-002');

it('AC2: every route gets exactly one row', function (): void {
    $omeka = spec002Omeka();
    $path = VerifierCli::fixture('fixture-signed.png');
    $sha = hash_file('sha256', $path);
    $omeka->startFixtureServer();

    $viaItem = $omeka->mediaIdsOf(OmekaInstallation::idFrom($omeka->api('POST', '/api/items', ['o:media' => [['o:ingester' => 'upload', 'file_index' => '0']]], ['file[0]' => [$path, 'fixture-signed.png', 'image/png']])));
    $item = OmekaInstallation::idFrom($omeka->api('POST', '/api/items', []));
    $ontoItem = [OmekaInstallation::idFrom($omeka->api('POST', '/api/media', ['o:ingester' => 'upload', 'file_index' => '0', 'o:item' => ['o:id' => $item]], ['file[0]' => [$path, 'fixture-signed.png', 'image/png']]))];
    $viaUrl = $omeka->mediaIdsOf(OmekaInstallation::idFrom($omeka->api('POST', '/api/items', ['o:media' => [['o:ingester' => 'url', 'ingest_url' => 'http://127.0.0.1:'.OmekaInstallation::FIXTURE_PORT.'/fixture-signed.png']]])));

    foreach (['REST API, item with media' => $viaItem, 'REST API, onto an item' => $ontoItem, 'URL ingester' => $viaUrl] as $route => $media) {
        expect($media)->toHaveCount(1, $route);
        $row = $omeka->verdictRow($media[0]);
        expect($row['state'] ?? null)->toBe('Valid', $route)
            ->and($row['checked_sha256'] ?? null)->toBe($sha, $route);
        expect($omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict WHERE media_id = '.$media[0]))->toBe('1', $route);
    }
})->group('SPEC-002');

it('AC3: an unsigned file is none, not Invalid', function (string $extension): void {
    $omeka = spec002Omeka();
    $path = VerifierCli::fixture("fixture-unsigned.{$extension}");

    $row = $omeka->verdictRow(spec002Upload($omeka, $path, "fixture-unsigned.{$extension}", SPEC002_TYPES[$extension]));

    expect(VerifierCli::report($path)['validation_state'])->toBe('Invalid')
        ->and($row)->toHaveKeys(['signer_issuer', 'signer_common_name'])
        ->and($row['state'] ?? null)->toBe('none')
        ->and($row['signer_issuer'] ?? null)->toBeNull()
        ->and($row['signer_common_name'] ?? null)->toBeNull()
        ->and($row['codes'] ?? null)->toBe('[]');
})->with(array_keys(SPEC002_TYPES))->group('SPEC-002');

it('AC4: an altered file is Invalid with the CLI\'s codes', function (): void {
    $omeka = spec002Omeka();
    $path = VerifierCli::alteredJpeg();
    try {
        $cli = VerifierCli::report($path);
        $row = $omeka->verdictRow(spec002Upload($omeka, $path, 'altered.jpg', 'image/jpeg'));
    } finally {
        unlink($path);
    }
    $codes = VerifierCli::codes($cli);

    expect($row['state'] ?? null)->toBe('Invalid')
        ->and(json_decode(is_string($row['codes'] ?? null) ? $row['codes'] : 'null', true))->toBe($codes)
        ->and($codes)->not->toBe([]);
})->group('SPEC-002');

it('AC6: other media are left alone', function (): void {
    $omeka = spec002Omeka();
    $pdf = sys_get_temp_dir().'/spec002-'.getmypid().'.pdf';
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    try {
        $gif = spec002Upload($omeka, VerifierCli::fixture('auth-1-1.gif'), 'auth-1-1.gif', 'image/gif');
        $pdfMedia = spec002Upload($omeka, $pdf, 'document.pdf', 'application/pdf');
    } finally {
        unlink($pdf);
    }
    $html = $omeka->mediaIdsOf(OmekaInstallation::idFrom($omeka->api('POST', '/api/items', ['o:media' => [['o:ingester' => 'html', 'html' => '<p>text</p>']]])));

    expect($html)->toHaveCount(1)
        ->and($omeka->verdictRow($gif))->toBeNull()
        ->and($omeka->verdictRow($pdfMedia))->toBeNull()
        ->and($omeka->verdictRow($html[0]))->toBeNull();
})->group('SPEC-002');

it('AC7: deleting the media or its item removes the row', function (): void {
    $omeka = spec002Omeka();
    $path = VerifierCli::fixture('fixture-signed.jpg');
    $single = spec002Upload($omeka, $path, 'one.jpg', 'image/jpeg');
    $item = OmekaInstallation::idFrom($omeka->api('POST', '/api/items', ['o:media' => [
        ['o:ingester' => 'upload', 'file_index' => '0'], ['o:ingester' => 'upload', 'file_index' => '1'],
    ]], ['file[0]' => [$path, 'a.jpg', 'image/jpeg'], 'file[1]' => [$path, 'b.jpg', 'image/jpeg']]));
    $pair = $omeka->mediaIdsOf($item);
    expect($pair)->toHaveCount(2)
        ->and($omeka->verdictRow($single))->not->toBeNull()
        ->and($omeka->verdictRow($pair[0]))->not->toBeNull()
        ->and($omeka->verdictRow($pair[1]))->not->toBeNull();

    expect($omeka->api('DELETE', '/api/media/'.$single)->status)->toBe(200)
        ->and($omeka->api('DELETE', '/api/items/'.$item)->status)->toBe(200);

    expect($omeka->verdictRow($single))->toBeNull()
        ->and($omeka->verdictRow($pair[0]))->toBeNull()
        ->and($omeka->verdictRow($pair[1]))->toBeNull();
})->group('SPEC-002');

it('AC11: a store that cannot be written leaves the upload alone', function (): void {
    $omeka = spec002Omeka();
    $omeka->sql('RENAME TABLE content_credentials_verdict TO spec002_away');
    try {
        $response = $omeka->uploadThroughAdminForm(VerifierCli::fixture('fixture-signed.jpg'), 'fixture-signed.jpg', 'image/jpeg');
    } finally {
        $omeka->sql('RENAME TABLE spec002_away TO content_credentials_verdict');
    }

    expect($response->status)->toBe(302)
        ->and($response->location)->toMatch('#/admin/item/\d+$#')
        ->and($omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response)))->toHaveCount(1);
})->group('SPEC-002');

it('AC13: only the verifier\'s own source is loaded from vendor/', function (): void {
    $omeka = spec002Omeka();
    $omeka->startFixtureServer();

    $out = json_decode($omeka->php([
        OmekaInstallation::MODULE_DIR.'/tests/Integration/Support/included-files.php',
        'http://127.0.0.1:'.OmekaInstallation::FIXTURE_PORT.'/fixture-signed.jpg',
    ]), true, 512, JSON_THROW_ON_ERROR);
    $out = is_array($out) ? $out : [];
    $media = is_array($out['media'] ?? null) ? $out['media'] : [];

    expect($out['vendor_outside_verifier'] ?? null)->toBe([])
        ->and($out['verifier_files'] ?? 0)->toBeGreaterThan(0)
        ->and($out['logger_interface'] ?? null)->toStartWith('/var/www/html/vendor/')
        ->and($out['entity_state'] ?? null)->toBe('Valid')
        ->and($media)->toHaveCount(1)
        ->and($omeka->verdictRow(is_int($media[0] ?? null) ? $media[0] : 0)['state'] ?? null)->toBe('Valid');
})->group('SPEC-002');

it('AC14: text from the file is stored exactly', function (string $extension): void {
    $omeka = spec002Omeka();
    $path = VerifierCli::fixture("fixture-signed.{$extension}");
    $cli = VerifierCli::report($path);
    $signature = VerifierCli::signature($cli);

    $row = $omeka->verdictRow(spec002Upload($omeka, $path, "fixture-signed.{$extension}", SPEC002_TYPES[$extension]));

    expect($signature['issuer'] ?? null)->toBeString()
        ->and($row['signer_issuer'] ?? null)->toBe($signature['issuer'] ?? null)
        ->and($row['signer_common_name'] ?? null)->toBe($signature['common_name'] ?? null)
        ->and($row['signed_at'] ?? null)->toBe($signature['time'] ?? null)
        ->and($row['remote_manifest_url'] ?? null)->toBe($cli['remote_manifest'] ?? null);
})->with(array_keys(SPEC002_TYPES))->group('SPEC-002');
