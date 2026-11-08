<?php

declare(strict_types=1);

/*
 * SPEC-009: translatable text. The template script, the committed
 * language/template.pot, and the fallback of a format a translation broke
 * (AC7), without Omeka S. The pages themselves are tested in
 * tests/Integration/TranslationTest.php.
 */

use ContentCredentials\Display\Format;
use ContentCredentials\Display\Presentation;
use ContentCredentials\Display\RecheckSection;
use ContentCredentials\Display\SettingsPage;
use ContentCredentials\Tests\Support\Gettext;

require_once dirname(__DIR__, 2).'/bin/i18n-template.php';

function spec009Root(): string
{
    return dirname(__DIR__, 2);
}

/** @return array<string, array{msgstr: string, flags: list<string>}> */
function spec009Template(): array
{
    return Gettext::entries((string) file_get_contents(spec009Root().'/language/template.pot'));
}

it('AC4: the committed template is what the script writes', function (): void {
    expect(i18nTemplate(spec009Root()))->toBe((string) file_get_contents(spec009Root().'/language/template.pot'));
})->group('SPEC-009');

it('AC4: the template holds the strings the module passes on', function (): void {
    $template = spec009Template();
    $expected = [
        // Presentation: headlines, labels, the two other headlines, the reasons, the lines.
        ...array_values(Presentation::HEADLINES),
        ...array_values(Presentation::AI_LABELS),
        Presentation::NOT_CHECKED,
        Presentation::UNREADABLE,
        'Reason: the check did not finish',
        'Reason: the file could not be read',
        'Reason: the verifier failed',
        "Reason: this server's PHP is older than 8.3",
        'Codes:',
        'Refers to Content Credentials elsewhere (not checked): %s',
        'Signed by %1$s (%2$s)',
        'Signed by %s',
        'Signed at %s',
        'Checked %1$s UTC with c2pa-verifier %2$s',
        'Trust: the C2PA trust list of %s, with DigiCert timestamps',
        'Trust: the C2PA trust list of %s',
        'Trust: custom trust settings',
        'Trust: none (checked without a trust list)',
        // The block, the column, the filters.
        'Content Credentials',
        'Evidence: 1 check kept',
        'Evidence: %s checks kept',
        '(unknown value)',
        'Any',
        'Content Credentials: AI statement',
        // The evidence pages.
        'Evidence',
        'Evidence document',
        'Back to the media item',
        'No evidence is kept for this media item.',
        'Checked', 'Verifier', 'Trust', 'File SHA-256', 'Document',
        'View', 'Download', 'All checks of this media item',
        // The configure page.
        ...array_values(RecheckSection::STATUSES),
        'Check existing media',
        'Job page',
        'Mark this run as ended',
        'Check all media',
        'Check media without a result',
        'DigiCert timestamps',
        'Custom trust settings',
        // The messages, and the module's own reasons in them.
        'The run was marked as ended.',
        "Checking existing media has started. Its status is on this module's configure page.",
        'These trust settings were not saved: %s',
        'No run was started: unknown kind of run.',
        'A run is already in progress; no new run was started.',
        'The run could not start; see its job page.',
        'No run was marked as ended.',
        'larger than 1 MiB',
        'the trust settings could not be read',
    ];

    $missing = array_values(array_filter($expected, static fn (string $msgid): bool => ! isset($template[$msgid])));
    expect($missing)->toBe([]);
})->group('SPEC-009');

it('AC4: a format is marked php-format, nothing else is, and no msgid is only a placeholder', function (): void {
    $wrong = [];
    foreach (spec009Template() as $msgid => $entry) {
        $format = preg_match('/%(?:\d+\$)?[sd]/', $msgid) === 1;
        if ($format !== in_array('php-format', $entry['flags'], true)) {
            $wrong[] = $msgid;
        }
        if (trim((string) preg_replace('/%(?:\d+\$)?[sd]/', '', $msgid)) === '') {
            $wrong[] = $msgid;
        }
        if ($entry['msgstr'] !== '') {
            $wrong[] = $msgid;
        }
    }

    expect($wrong)->toBe([]);
})->group('SPEC-009');

it('AC4: the script takes literal calls and marked lines, and leaves keys and unmarked strings', function (): void {
    $entries = Gettext::entries(i18nTemplate(dirname(__DIR__).'/Fixtures/i18n'));

    expect(array_keys($entries))->toBe([
        'A literal call',
        'A marked format %s',
        'A message',
        'A value on a marked line',
        'Also on the marked line',
        'From a phtml view',
        'Through a closure',
        'Through the variable',
    ])->and($entries['A marked format %s']['flags'])->toBe(['php-format']);
})->group('SPEC-009');

it('AC7: a format a translation broke falls back to the English source', function (string $translated): void {
    expect(Format::fill($translated, 'Signed by %1$s (%2$s)', ['A', 'B']))->toBe('Signed by A (B)');
})->with([
    'a placeholder too many' => ['Ondertekend door %1$s (%2$s) %3$s'],
    'an unknown conversion' => ['Ondertekend door %1$s %z'],
])->group('SPEC-009');

it('AC7: a good translation is filled in', function (): void {
    expect(Format::fill('Ondertekend door %1$s (%2$s)', 'Signed by %1$s (%2$s)', ['A', 'B']))->toBe('Ondertekend door A (B)')
        ->and(Format::fill('Door %2$s: %1$s', 'Signed by %1$s (%2$s)', ['A', 'B']))->toBe('Door B: A');
})->group('SPEC-009');

it('AC7: the configure page shows English where a translation broke a format', function (): void {
    $broken = static fn (string $source): string => str_contains($source, '%') ? $source.' %9$s' : '⟦'.$source.'⟧';
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $settings = SettingsPage::html($broken, $escape, true, '', true, 'a reason', false);
    $section = RecheckSection::html($broken, $escape, ['id' => 7, 'status' => 'completed', 'started' => '2026-11-08 10:00:00', 'ended' => '2026-11-08 10:05:00'], null);

    expect($settings)->toContain('more than six months ago')
        ->and($settings)->toContain('Reason: a reason')
        ->and($section)->toContain('Latest run: ⟦Completed⟧, started 2026-11-08 10:00:00, ended 2026-11-08 10:05:00.');
})->group('SPEC-009');
