<?php

declare(strict_types=1);

/*
 * M7.1 probe, never shipped. Run from the repository root with COMPOSE_FILE and
 * CC_TEST_PORT/CC_TEST_PORT_PHP82 set to BuildProbe's environment:
 *   php tools/probes/BuildProbe/scripts/measure.php refuse|install|fixtures|rogue|one
 * The oracle is the unprefixed verifier of this repository's vendor/, on the host.
 */

require __DIR__.'/../../../../vendor/autoload.php';

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

const TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

function oracleEvidence(string $path, string $settings, string $stored): string
{
    $document = json_decode($stored, true);
    $manifests = is_array($document) ? ($document['manifests'] ?? []) : [];
    $at = null;
    foreach ($manifests as $manifest) {
        if (($manifest['time_source'] ?? null) === 'clock' && is_int($manifest['judged_at'] ?? null)) {
            $at = new DateTimeImmutable('@'.$manifest['judged_at']);
        }
    }
    $stream = fopen($path, 'rb');
    $report = (new Verifier)->verify($stream, TrustSettings::fromJson($settings), $at);
    fclose($stream);

    // As SPEC-006's test does: only the "given" the oracle then writes goes back.
    return $at === null ? $report->evidenceToJson() : str_replace('"time_source": "given"', '"time_source": "clock"', $report->evidenceToJson());
}

$what = $argv[1] ?? '';
if ($what === 'refuse') {
    $omeka = OmekaInstallation::php82();
    $omeka->logIn();
    $before = $omeka->snapshot();
    $response = $omeka->moduleAction('install', 'ContentCredentials');
    $page = $omeka->get('/admin/module');
    echo 'PHP 8.2 install: HTTP ', $response->status, ' messages: ', json_encode(OmekaInstallation::messages($page->body)), "\n";
    echo 'module row: ', json_encode($omeka->moduleRow('ContentCredentials')), "\n";
    echo 'snapshot unchanged: ', $before === $omeka->snapshot() ? 'yes' : 'NO', "\n";
    echo 'admin page HTTP: ', $omeka->get('/admin')->status, "\n";
    exit;
}

$omeka = OmekaInstallation::php83();
$omeka->logIn();
if ($what === 'install') {
    $response = $omeka->moduleAction('install', 'ContentCredentials');
    echo 'install: HTTP ', $response->status, ' row: ', json_encode($omeka->moduleRow('ContentCredentials')), "\n";
    echo 'tables: ', str_replace("\n", ' ', $omeka->sql("SHOW TABLES LIKE 'content_credentials%'")), "\n";
    echo 'configure page: HTTP ', $omeka->get('/admin/module/configure?id=ContentCredentials')->status, "\n";
    exit;
}
if ($what === 'fixtures') {
    $settings = (string) file_get_contents(VerifierCli::settingsFile(true));
    $same = $total = 0;
    foreach (glob(dirname(VerifierCli::fixture('x')).'/*.{jpg,png,webp}', GLOB_BRACE) ?: [] as $path) {
        $name = basename($path);
        $response = $omeka->uploadThroughAdminForm($path, $name, TYPES[pathinfo($name, PATHINFO_EXTENSION)]);
        $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response))[0];
        $row = $omeka->verdictRow($media);
        $cli = VerifierCli::report($path, VerifierCli::settingsFile(true));
        $cliState = $cli['validation_state'] ?? 'none';
        $document = $omeka->sql('SELECT document FROM content_credentials_evidence WHERE media_id = '.$media.' ORDER BY id DESC LIMIT 1');
        $evidence = $document === '' ? 'no document' : (oracleEvidence($path, $settings, $document) === $document ? 'identical' : 'DIFFERS');
        $state = $row['state'] ?? 'no row';
        printf('%-48s %-8s cli %-8s %-11s verifier %s
', $name, $state, $cliState, $evidence, $row['verifier'] ?? '?');
        $total++;
        // SPEC-002: a file without a manifest is `none` where the CLI says Invalid.
        $same += ($state === $cliState || ($state === 'none' && str_contains($name, 'unsigned'))) && $evidence !== 'DIFFERS' ? 1 : 0;
    }
    echo "$same of $total agree\n";
    exit;
}

// Installs RogueVerifier next to the module, uploads one file and shows which
// verifier version wrote the verdict, and what the other module's copy still is.
if ($what === 'rogue') {
    $response = $omeka->moduleAction('install', 'RogueVerifier');
    echo 'rogue row: ', json_encode($omeka->moduleRow('RogueVerifier')), "\n";
    $path = VerifierCli::fixture('fixture-signed.jpg');
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm($path, 'fixture-signed.jpg', 'image/jpeg')))[0];
    $row = $omeka->verdictRow($media);
    echo 'media ', $media, ' state ', $row['state'] ?? '?', ' reason ', json_encode($row['reason'] ?? null), ' verifier ', $row['verifier'] ?? '?', "\n";
    $omeka->moduleAction('uninstall', 'RogueVerifier');
    echo 'rogue row after uninstall: ', json_encode($omeka->moduleRow('RogueVerifier')), "\n";
    exit;
}

// One upload, for the case of a build that forgot the path.
if ($what === 'one') {
    $path = VerifierCli::fixture('fixture-signed.jpg');
    $response = $omeka->uploadThroughAdminForm($path, 'fixture-signed.jpg', 'image/jpeg');
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response))[0];
    $row = $omeka->verdictRow($media);
    echo 'upload HTTP ', $response->status, ' media ', $media, ' state ', $row['state'] ?? '?', ' reason ', json_encode($row['reason'] ?? null), ' verifier ', json_encode($row['verifier'] ?? null), "\n";
    echo 'evidence rows for it: ', $omeka->sql('SELECT COUNT(*) FROM content_credentials_evidence WHERE media_id = '.$media), "\n";
    echo 'media page HTTP ', $omeka->get('/admin/media/'.$media)->status, "\n";
    echo 'configure page HTTP ', $omeka->get('/admin/module/configure?id=ContentCredentials')->status, "\n";
    exit;
}
