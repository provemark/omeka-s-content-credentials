<?php

declare(strict_types=1);

/*
 * SPEC-008 AC5 to AC7 and AC10: the zip, unpacked in a clean Omeka S. The oracle
 * is the unprefixed verifier of this repository's vendor/, on the host.
 */

require_once __DIR__.'/Support.php';

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

const RELEASE_INSTALLED = OmekaInstallation::MODULE_DIR;

/** The document the unprefixed verifier writes, as SPEC-006 AC1 compares it. */
function releaseExpectedDocument(string $path, string $settings, string $stored): string
{
    $document = json_decode($stored, true);
    $manifests = is_array($document) && is_array($document['manifests'] ?? null) ? $document['manifests'] : [];
    $at = null;
    foreach ($manifests as $manifest) {
        if (is_array($manifest) && ($manifest['time_source'] ?? null) === 'clock' && is_int($manifest['judged_at'] ?? null)) {
            $at = new DateTimeImmutable('@'.$manifest['judged_at']);
        }
    }
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        throw new RuntimeException("Cannot open {$path}");
    }
    try {
        $json = (new Verifier)->verify($stream, TrustSettings::fromJson($settings), $at)->evidenceToJson();
    } finally {
        fclose($stream);
    }

    return $at === null ? $json : str_replace('"time_source": "given"', '"time_source": "clock"', $json);
}

it('AC5: it installs, and gives the verifier\'s verdicts', function (string $fixture): void {
    $omeka = releaseOmeka();
    expect($omeka->moduleRow(RELEASE_MODULE)['version'] ?? null)->toBe(releaseVersion())
        ->and($omeka->moduleRow(RELEASE_MODULE)['is_active'] ?? null)->toBe('1');

    $media = releaseUpload($omeka, $fixture);

    $file = VerifierCli::settingsFile(true);
    $report = VerifierCli::report(VerifierCli::fixture($fixture), $file);
    $row = $omeka->verdictRow($media) ?? [];
    $document = $omeka->sql('SELECT document FROM content_credentials_evidence WHERE media_id = '.$media.' ORDER BY id DESC LIMIT 1');
    expect($row['verifier'] ?? null)->toBe(Verifier::VERSION);
    if (str_contains($fixture, 'unsigned')) {
        expect($row['state'] ?? null)->toBe('none')->and($document)->toBe('');

        return;
    }
    expect($row['state'] ?? null)->toBe($report['validation_state'] ?? 'no state')
        ->and($document)->toBe(releaseExpectedDocument(VerifierCli::fixture($fixture), (string) file_get_contents($file), $document));
})->with(array_map('basename', glob(dirname(__DIR__).'/Fixtures/c2pa/*.{jpg,png,webp}', GLOB_BRACE) ?: []))->group('SPEC-008');

it('AC6: it refuses PHP 8.2', function (): void {
    $omeka = OmekaInstallation::php82();
    $omeka->logIn();
    $before = $omeka->snapshot();

    $response = $omeka->moduleAction('install', RELEASE_MODULE);

    expect($response->status)->toBe(302)
        ->and(implode(' ', OmekaInstallation::messages($omeka->get('/admin/module')->body)))->toContain('needs PHP 8.3 or later')
        ->and($omeka->moduleRow(RELEASE_MODULE))->toBeNull()
        ->and($omeka->snapshot())->toBe($before);
})->group('SPEC-008');

it('AC7: another module\'s copy does not reach it', function (): void {
    $omeka = releaseOmeka();
    $copy = releaseWork('rogue').'/RogueVerifier';
    releaseRun(['cp', '-R', dirname(__DIR__).'/Fixtures/rogue-verifier', $copy]);
    releaseRun(['cp', '-R', releaseRoot().'/vendor/provemark/c2pa-verifier/src', $copy.'/verifier']);
    $class = $copy.'/verifier/Verifier/Verifier.php';
    $changed = (string) preg_replace("/const string VERSION = '[^']*'/", "const string VERSION = '0.0.1-other'", (string) file_get_contents($class));
    expect($changed)->toContain("'0.0.1-other'");
    file_put_contents($class, $changed);
    releaseRun(['docker', 'compose', 'cp', $copy, 'omeka:/var/www/html/modules/RogueVerifier'], releaseRoot());
    $omeka->rootShell('chown -R www-data /var/www/html/modules/RogueVerifier');
    try {
        $omeka->moduleAction('install', 'RogueVerifier');
        expect($omeka->moduleRow('RogueVerifier')['is_active'] ?? null)->toBe('1');

        $row = $omeka->verdictRow(releaseUpload($omeka, 'fixture-signed.jpg')) ?? [];

        expect($row['state'] ?? null)->toBe('Valid')
            ->and($row['verifier'] ?? null)->toBe(Verifier::VERSION);
    } finally {
        $omeka->moduleAction('uninstall', 'RogueVerifier');
        $omeka->rootShell('rm -rf /var/www/html/modules/RogueVerifier');
    }
})->group('SPEC-008');

it('AC10: no verifier at all', function (): void {
    $omeka = releaseOmeka();
    $folder = RELEASE_INSTALLED.'/vendor-prefixed';
    $omeka->rootShell("mv {$folder} {$folder}.away");
    try {
        $media = releaseUpload($omeka, 'fixture-signed.jpg');
        $row = $omeka->verdictRow($media) ?? [];

        expect($row['state'] ?? null)->toBe('error')
            ->and($row['reason'] ?? null)->toBe('exception')
            ->and($omeka->sql('SELECT COUNT(*) FROM content_credentials_evidence WHERE media_id = '.$media))->toBe('0')
            ->and($omeka->get('/admin/media/'.$media)->status)->toBe(200)
            ->and($omeka->get('/admin/module/configure?id='.RELEASE_MODULE)->status)->toBe(200);
    } finally {
        $omeka->rootShell("mv {$folder}.away {$folder}");
    }
})->group('SPEC-008');
