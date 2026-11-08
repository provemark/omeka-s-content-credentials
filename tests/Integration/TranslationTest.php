<?php

declare(strict_types=1);

/*
 * SPEC-009: translatable text, in the pinned Omeka S of tools/env/ (PHP 8.3).
 * The module ships no language (amendment 1); these tests make a pseudo
 * translation from language/template.pot, in which every msgstr is its
 * msgid between ⟦ and ⟧, and place it as language/nl_NL.mo. A string of the
 * module shown without the brackets did not go through translate(), or is
 * not in the template. Every test puts the locale and the file back.
 */

use ContentCredentials\Tests\Integration\Support\HttpResponse;
use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\Gettext;
use ContentCredentials\Tests\Support\VerifierCli;

const SPEC009_MODULE = '/var/www/html/modules/ContentCredentials';
const SPEC009_PAGE = '/admin/module/configure?id=ContentCredentials';
const SPEC009_CSRF = 'module_ContentCredentials_configure_csrf';
const SPEC009_CLASS = 'ContentCredentials\\\\Omeka\\\\RecheckJob'; // as an SQL literal
const SPEC009_LIST = 'c2pa-2026-11-04';

/** The PHP 8.3 installation, logged in, the module installed and active, its settings the defaults. */
function spec009Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow('ContentCredentials');
    if ($row === null) {
        $omeka->moduleAction('install', 'ContentCredentials');
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', 'ContentCredentials');
    }
    $omeka->sql("DELETE FROM setting WHERE id LIKE 'content\\_credentials\\_%'");

    return $omeka;
}

/** @return list<string> */
function spec009Msgids(): array
{
    return array_keys(Gettext::entries((string) file_get_contents(dirname(__DIR__, 2).'/language/template.pot')));
}

/**
 * Places a translation as language/<locale>.mo where Omeka S loads the
 * module from, or removes it (null). Written inside the container: in the
 * release environment into the unpacked zip, in the development environment
 * into the tmpfs compose.yaml mounts there over the read-only working tree.
 */
function spec009PlaceMo(OmekaInstallation $omeka, ?string $mo, string $locale = 'nl_NL'): void
{
    $file = SPEC009_MODULE.'/language/'.$locale.'.mo';
    $omeka->rootShell($mo === null
        ? 'rm -f '.$file
        : 'mkdir -p '.SPEC009_MODULE.'/language && echo '.base64_encode($mo).' | base64 -d > '.$file.' && chmod 644 '.$file);
}

/** The global locale, and the test administrator's own; null for none. */
function spec009Locale(OmekaInstallation $omeka, ?string $global, ?string $user = null): void
{
    $omeka->sql("DELETE FROM setting WHERE id = 'locale'");
    $omeka->sql("DELETE FROM user_setting WHERE id = 'locale'");
    if ($global !== null) {
        $omeka->sql("INSERT INTO setting (id, value) VALUES ('locale', '\"{$global}\"')");
    }
    if ($user !== null) {
        $omeka->sql("INSERT INTO user_setting (id, user_id, value) SELECT 'locale', id, '\"{$user}\"' FROM user WHERE email = '".OmekaInstallation::EMAIL."'");
    }
}

function spec009Upload(OmekaInstallation $omeka, string $fixture): int
{
    $media = $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($omeka->uploadThroughAdminForm(VerifierCli::fixture($fixture), $fixture, 'image/jpeg')));

    return $media[0] ?? throw new RuntimeException("No media for {$fixture}");
}

/** @param array<string, string|null> $values */
function spec009Set(OmekaInstallation $omeka, int $mediaId, array $values): void
{
    $sets = [];
    foreach ($values as $column => $value) {
        $sets[] = $column.' = '.($value === null ? 'NULL' : "CONVERT(X'".bin2hex($value)."' USING utf8mb4)");
    }
    $omeka->sql('UPDATE content_credentials_verdict SET '.implode(', ', $sets).' WHERE media_id = '.$mediaId);
}

/** The module's block on a media page, both places (sidebar and Details panel). */
function spec009Blocks(string $html): string
{
    preg_match_all('#<div class="meta-group content-credentials">.*?<!-- /content-credentials -->#s', $html, $m);

    return implode("\n", $m[0]);
}

/** The column's heading and cells of the media browse. */
function spec009Column(string $html): string
{
    preg_match_all('#<th class="column-content_credentials">.*?</th>|<td class="column-content_credentials">.*?</td>#s', $html, $m);

    return implode("\n", $m[0]);
}

/** The module's two fields of the advanced search. */
function spec009SearchFields(string $html): string
{
    preg_match_all('#<label for="content-credentials-(?:state|ai)">.*?</select>#s', $html, $m);

    return implode("\n", $m[0]);
}

/** The page title, and the module's part of an evidence page without the document itself. */
function spec009EvidencePage(string $html): string
{
    preg_match('#<h1.*?</h1>#s', $html, $title);
    $start = (int) strpos($html, '<p><a href="/admin/');
    $end = strpos($html, '<pre', $start);
    $tableEnd = strpos($html, '</table>', $start);
    $stop = $end !== false ? $end : ($tableEnd !== false ? $tableEnd + 8 : strlen($html));

    return ($title[0] ?? '')."\n".substr($html, $start, $stop - $start);
}

/** The module's part of the configure page: trust settings and runs. */
function spec009ConfigureSections(string $html): string
{
    $start = (int) strpos($html, '<div class="content-credentials-settings">');
    $end = (int) strpos($html, '<!-- /content-credentials-recheck -->');

    return substr($html, $start, $end - $start);
}

/** The text of markup, with everything between ⟦ and ⟧ taken out (innermost first). */
function spec009Untranslated(string $html): string
{
    $text = html_entity_decode((string) preg_replace('/<[^>]*>/', ' ', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    do {
        $before = $text;
        $text = (string) preg_replace('/⟦[^⟦⟧]*⟧/u', '', $text);
    } while ($text !== $before);

    return $text;
}

/**
 * The template's msgids whose English shows in $html outside the brackets.
 *
 * @param  list<string>  $msgids
 * @return list<string>
 */
function spec009English(string $html, array $msgids): array
{
    $text = spec009Untranslated($html);
    $found = [];
    foreach ($msgids as $msgid) {
        foreach (Gettext::fragments($msgid) as $fragment) {
            if (str_contains($text, $fragment)) {
                $found[] = $msgid;
                break;
            }
        }
    }

    return $found;
}

/**
 * Submits the configure page with its CSRF token.
 *
 * @param  array<string, string>  $fields
 */
function spec009Configure(OmekaInstallation $omeka, array $fields): HttpResponse
{
    preg_match('#name="'.SPEC009_CSRF.'" value="([^"]+)"#', $omeka->get(SPEC009_PAGE)->body, $m);

    return $omeka->post(SPEC009_PAGE, $fields + [SPEC009_CSRF => $m[1] ?? '']);
}

/** @return list<string> the messages of a page, as text */
function spec009Messages(HttpResponse $response): array
{
    return array_map(static fn (string $m): string => html_entity_decode($m, ENT_QUOTES | ENT_HTML5, 'UTF-8'), OmekaInstallation::messages($response->body));
}

/** A run row of this module, as Omeka keeps it; returns its id. */
function spec009Job(OmekaInstallation $omeka, string $status): int
{
    $omeka->sql("INSERT INTO job (status, class, started, ended) VALUES ('{$status}', '".SPEC009_CLASS."', '2026-11-08 10:00:00', ".($status === 'completed' ? "'2026-11-08 10:05:00'" : 'NULL').')');

    return (int) $omeka->sql("SELECT MAX(id) FROM job WHERE class = '".SPEC009_CLASS."'");
}

/**
 * Every page AC1 names, each reduced to the module's part.
 *
 * @return array<string, string>
 */
function spec009Pages(OmekaInstallation $omeka, int $media): array
{
    $pages = [];
    $variants = [
        'Trusted, AI-generated, DigiCert' => ['state' => 'Trusted', 'ai_statement' => 'generated', 'codes' => '[]', 'trust' => SPEC009_LIST.'+digicert'],
        'Valid, AI-edited, the list' => ['state' => 'Valid', 'ai_statement' => 'edited', 'codes' => '[]', 'trust' => SPEC009_LIST],
        'Valid, signer name only' => ['state' => 'Valid', 'ai_statement' => null, 'signer_issuer' => null, 'trust' => 'custom'],
        'Invalid' => ['state' => 'Invalid', 'ai_statement' => null, 'codes' => '["assertion.dataHash.mismatch"]', 'trust' => 'none'],
        'none, elsewhere' => ['state' => 'none', 'codes' => '[]', 'remote_manifest_url' => 'https://example.org/manifest.c2pa', 'trust' => null],
        'error, interrupted' => ['state' => 'error', 'reason' => 'interrupted'],
        'error, unreadable' => ['state' => 'error', 'reason' => 'unreadable'],
        'error, exception' => ['state' => 'error', 'reason' => 'exception'],
        'error, requirements' => ['state' => 'error', 'reason' => 'requirements'],
        'unreadable row' => ['state' => 'Maybe'],
    ];
    $original = $omeka->verdictRow($media) ?? throw new RuntimeException('No row');
    foreach ($variants as $name => $values) {
        spec009Set($omeka, $media, $values);
        $pages['media page: '.$name] = spec009Blocks($omeka->get('/admin/media/'.$media)->body);
        $pages['browse: '.$name] = spec009Column($omeka->get('/admin/media?per_page=1000000&sort_by=id&sort_order=desc')->body);
        $restore = [];
        foreach (array_keys($values) as $column) {
            $restore[$column] = is_scalar($original[$column] ?? null) ? (string) $original[$column] : null;
        }
        spec009Set($omeka, $media, $restore);
    }
    $pages['search'] = spec009SearchFields($omeka->get('/admin/media/search')->body);
    preg_match('#<div class="search-filters">.*?</div>\s*</div>#s', $omeka->get('/admin/media?content_credentials_state=Valid&content_credentials_ai=generated')->body, $filters);
    $pages['active filters'] = $filters[0] ?? '';
    $pages['evidence list'] = spec009EvidencePage($omeka->get('/admin/content-credentials/media/'.$media.'/evidence')->body);
    $evidence = (int) $omeka->sql('SELECT MAX(id) FROM content_credentials_evidence WHERE media_id = '.$media);
    $pages['evidence document'] = spec009EvidencePage($omeka->get('/admin/content-credentials/evidence/'.$evidence)->body);
    spec009Job($omeka, 'completed');
    $pages['configure page'] = spec009ConfigureSections($omeka->get(SPEC009_PAGE)->body);
    $omeka->sql("DELETE FROM job WHERE class = '".SPEC009_CLASS."' AND started = '2026-11-08 10:00:00'");
    $omeka->sql('DELETE FROM content_credentials_verdict WHERE media_id = '.$media);
    $pages['media page: not checked'] = spec009Blocks($omeka->get('/admin/media/'.$media)->body);

    return $pages;
}

afterEach(function (): void {
    $omeka = OmekaInstallation::php83();
    spec009PlaceMo($omeka, null);
    spec009Locale($omeka, null);
    $omeka->sql("DELETE FROM job WHERE class = '".SPEC009_CLASS."' AND started = '2026-11-08 10:00:00'");
});

it('AC1: with a translation, every string of the module on every page is translated', function (): void {
    $omeka = spec009Omeka();
    $media = spec009Upload($omeka, 'fixture-signed.jpg');
    $msgids = spec009Msgids();
    spec009PlaceMo($omeka, Gettext::mo(Gettext::pseudo($msgids)));
    spec009Locale($omeka, 'nl_NL');

    $pages = spec009Pages($omeka, $media);
    $english = [];
    $empty = [];
    foreach ($pages as $name => $html) {
        if (! str_contains($html, Gettext::OPEN)) {
            $empty[] = $name;
        }
        foreach (spec009English($html, $msgids) as $msgid) {
            $english[] = $name.': '.$msgid;
        }
    }
    $all = implode("\n", $pages);

    expect($empty)->toBe([])
        ->and($english)->toBe([])
        ->and($all)->toContain('⟦Verified: trusted signer⟧')
        ->and($all)->toContain('⟦AI-generated (signed)⟧')
        ->and($all)->toContain('⟦Reason: this server&#039;s PHP is older than 8.3⟧')
        ->and($all)->toContain('⟦Latest run: ⟦Completed⟧, started 2026-11-08 10:00:00, ended 2026-11-08 10:05:00.⟧')
        // Values from the file are filled in unchanged, and escaped once.
        ->and($all)->toMatch('#⟦Signed by [^⟦⟧<]+ \([^⟦⟧<]+\)⟧#')
        ->and($all)->toContain('⟦Refers to Content Credentials elsewhere (not checked): https://example.org/manifest.c2pa⟧');
})->group('SPEC-009');

it('AC2: a user whose own locale is English sees English', function (): void {
    $omeka = spec009Omeka();
    $media = spec009Upload($omeka, 'fixture-signed.jpg');
    spec009PlaceMo($omeka, Gettext::mo(Gettext::pseudo(spec009Msgids())));
    spec009Locale($omeka, 'nl_NL', 'en_US');

    $pages = spec009Pages($omeka, $media);
    $translated = array_keys(array_filter($pages, static fn (string $html): bool => str_contains($html, Gettext::OPEN)));

    expect($translated)->toBe([])
        ->and(implode("\n", $pages))->toContain('Verified: trusted signer');
})->group('SPEC-009');

it('AC3: a locale the module has no translation for shows the module in English, and every page answers', function (): void {
    $omeka = spec009Omeka();
    $media = spec009Upload($omeka, 'fixture-signed.jpg');
    spec009PlaceMo($omeka, Gettext::mo(Gettext::pseudo(spec009Msgids())));
    spec009Locale($omeka, 'de_DE');

    $statuses = [];
    foreach (['/admin/media', '/admin/media/'.$media, '/admin/media/search', '/admin/content-credentials/media/'.$media.'/evidence', SPEC009_PAGE] as $path) {
        $statuses[$path] = $omeka->get($path)->status;
    }
    $page = $omeka->get('/admin/media/'.$media)->body;

    expect(array_unique(array_values($statuses)))->toBe([200])
        ->and($page)->toContain('Abmelden')
        ->and(spec009Blocks($page))->toContain('Content Credentials')
        ->and(spec009Blocks($page))->not->toContain(Gettext::OPEN);
})->group('SPEC-009');

it('AC7: a translation that breaks a format does not take a page down', function (): void {
    $omeka = spec009Omeka();
    $media = spec009Upload($omeka, 'fixture-signed.jpg');
    $broken = [];
    foreach (spec009Msgids() as $msgid) {
        $broken[$msgid] = Gettext::OPEN.$msgid.(preg_match('/%(?:\d+\$)?[sd]/', $msgid) === 1 ? ' %9$s' : '').Gettext::CLOSE;
    }
    spec009PlaceMo($omeka, Gettext::mo($broken));
    spec009Locale($omeka, 'nl_NL');
    spec009Job($omeka, 'completed');

    $show = $omeka->get('/admin/media/'.$media);
    $configure = $omeka->get(SPEC009_PAGE);
    $refused = spec009Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => str_repeat('x', 1024 * 1024 + 1)]);
    $block = spec009Blocks($show->body);

    expect($show->status)->toBe(200)
        ->and($block)->toContain('⟦Intact: signer not trusted⟧')
        ->and($block)->toMatch('#[^⟦]Signed by [^<]+ \([^<]+\)#')
        ->and($configure->status)->toBe(200)
        ->and(spec009ConfigureSections($configure->body))->toContain('Latest run: ⟦Completed⟧, started 2026-11-08 10:00:00, ended 2026-11-08 10:05:00.')
        ->and($refused->status)->toBe(200)
        ->and(spec009Messages($refused))->toContain('These trust settings were not saved: ⟦larger than 1 MiB⟧');
})->group('SPEC-009');

it('AC8: the messages of the configure page are translated whole', function (): void {
    $omeka = spec009Omeka();
    spec009PlaceMo($omeka, Gettext::mo(Gettext::pseudo(spec009Msgids())));
    spec009Locale($omeka, 'nl_NL');

    $tooLarge = spec009Messages(spec009Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => str_repeat('x', 1024 * 1024 + 1)]));
    $unreadable = spec009Messages(spec009Configure($omeka, ['content_credentials_digicert' => '1', 'content_credentials_custom_settings' => 'not json']));

    $running = spec009Job($omeka, 'in_progress');
    $alreadyRunning = spec009Messages(spec009Configure($omeka, ['content_credentials_recheck' => 'all']));
    $omeka->sql("UPDATE job SET status = 'starting' WHERE id = {$running}");
    $ended = spec009Configure($omeka, ['content_credentials_end_run' => (string) $running]);
    $endedMessages = spec009Messages($omeka->get('/admin/module'));
    $omeka->sql("DELETE FROM job WHERE class = '".SPEC009_CLASS."' AND started = '2026-11-08 10:00:00'");

    $started = spec009Configure($omeka, ['content_credentials_recheck' => 'without_result']);
    $startedMessages = spec009Messages($omeka->get('/admin/module'));
    $job = (int) $omeka->sql("SELECT MAX(id) FROM job WHERE class = '".SPEC009_CLASS."'");
    for ($i = 0; $i < 60 && ! in_array($omeka->sql("SELECT status FROM job WHERE id = {$job}"), ['completed', 'stopped', 'error'], true); $i++) {
        usleep(500000);
    }

    $verifierReason = array_values(array_filter($unreadable, static fn (string $m): bool => str_starts_with($m, '⟦These trust settings were not saved: ')));

    expect($tooLarge)->toContain('⟦These trust settings were not saved: ⟦larger than 1 MiB⟧⟧')
        ->and($verifierReason)->toHaveCount(1)
        // The verifier's own words are filled in as they are.
        ->and($verifierReason[0] ?? '')->toContain('JSON')
        ->and($verifierReason[0] ?? '')->not->toContain('⟦These trust settings were not saved: ⟦')
        ->and($alreadyRunning)->toContain('⟦A run is already in progress; no new run was started.⟧')
        ->and($ended->status)->toBe(302)
        ->and($endedMessages)->toContain('⟦The run was marked as ended.⟧')
        ->and($started->status)->toBe(302)
        ->and($startedMessages)->toContain("⟦Checking existing media has started. Its status is on this module's configure page.⟧");
})->group('SPEC-009');

it('AC9: what is not translated stays as it was', function (): void {
    $omeka = spec009Omeka();
    $media = spec009Upload($omeka, 'fixture-signed.jpg');
    spec009PlaceMo($omeka, Gettext::mo(Gettext::pseudo(spec009Msgids())));
    spec009Locale($omeka, 'nl_NL');
    $row = json_decode($omeka->sql("SELECT JSON_OBJECT('id', id, 'document', document) FROM content_credentials_evidence WHERE media_id = {$media} ORDER BY id DESC LIMIT 1"), true, 512, JSON_THROW_ON_ERROR);
    expect($row)->toBeArray();
    /** @var array{id: int, document: string} $row */
    $download = $omeka->get("/admin/content-credentials/evidence/{$row['id']}/download");
    spec009Set($omeka, $media, ['state' => 'Invalid', 'ai_statement' => null, 'codes' => '["assertion.dataHash.mismatch"]']);
    $block = spec009Blocks($omeka->get('/admin/media/'.$media)->body);

    expect($download->status)->toBe(200)
        ->and($download->body)->toBe($row['document'])
        ->and($download->headers['content-disposition'] ?? null)->toBe(['attachment; filename="media-'.$media.'-evidence-'.$row['id'].'.json"'])
        ->and($block)->toContain('⟦Codes:⟧')
        ->and($block)->toContain('>assertion.dataHash.mismatch<')
        ->and($block)->not->toContain('⟦assertion');
})->group('SPEC-009');
