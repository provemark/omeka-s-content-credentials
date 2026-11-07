<?php

declare(strict_types=1);

/*
 * SPEC-003, the parts that need no Omeka S: what a row says (Presentation),
 * how untrusted text is made visible (Text), and what a filter value means
 * (FilterValue).
 */

use ContentCredentials\Display\FilterValue;
use ContentCredentials\Display\Presentation;
use ContentCredentials\Display\Text;

/**
 * A row as the table holds it, every column a string or null.
 *
 * @param  array<string, string|null>  $values
 * @return array<string, string|null>
 */
function spec003Row(array $values): array
{
    return $values + [
        'state' => 'Valid', 'format' => 'image/jpeg', 'signer_issuer' => null, 'signer_common_name' => null,
        'signed_at' => null, 'codes' => '[]', 'remote_manifest_url' => null, 'reason' => null,
        'verifier' => '0.10.0', 'checked_sha256' => null, 'checked_at' => '2026-11-04 10:00:00',
    ];
}

/**
 * The lines with their values filled in, as plain text.
 *
 * @return list<string>
 */
function spec003Lines(Presentation $presentation): array
{
    return array_map(static fn (array $line): string => vsprintf($line[0], $line[1]), $presentation->lines());
}

it('AC1: one headline per state, and for no row', function (?array $row, string $headline): void {
    expect(Presentation::of($row)->headline())->toBe($headline);
})->with([
    'Trusted' => [spec003Row(['state' => 'Trusted']), 'Verified: trusted signer'],
    'Valid' => [spec003Row(['state' => 'Valid']), 'Intact: signer not trusted'],
    'Invalid' => [spec003Row(['state' => 'Invalid', 'codes' => '["assertion.dataHash.mismatch"]']), 'Does not verify'],
    'none' => [spec003Row(['state' => 'none']), 'No Content Credentials'],
    'error' => [spec003Row(['state' => 'error', 'reason' => 'exception']), 'Could not be checked'],
    'no row' => [null, 'Not checked'],
])->group('SPEC-003');

it('AC2: the signer line names what is present (amendment 5)', function (mixed $name, mixed $organisation, ?string $line): void {
    // Values that are not text, so not through spec003Row()'s types.
    $lines = spec003Lines(Presentation::of(['signer_common_name' => $name, 'signer_issuer' => $organisation] + spec003Row([])));

    expect($lines[0] ?? null)->toBe($line ?? 'Checked 2026-11-04 10:00:00 UTC with c2pa-verifier 0.10.0')
        ->and(implode("\n", $lines))->not->toContain('()')
        ->and(implode("\n", $lines))->not->toContain('Signed by  ');
})->with([
    'both' => ['C2PA Signer', 'Google LLC', 'Signed by C2PA Signer (Google LLC)'],
    'a name only' => ['C2PA Signer', null, 'Signed by C2PA Signer'],
    'an organisation only' => [null, 'Google LLC', 'Signed by Google LLC'],
    'neither' => [null, null, null],
    'an empty organisation' => ['C2PA Signer', '', 'Signed by C2PA Signer'],
    'an empty name' => ['', 'Google LLC', 'Signed by Google LLC'],
    'both empty' => ['', '', null],
    'spaces only' => ['  ', "\t", null],
    'a name of spaces' => [' ', 'Google LLC', 'Signed by Google LLC'],
    'an organisation that is not text' => ['C2PA Signer', 42, 'Signed by C2PA Signer'],
    'a name that is not text' => [['x'], 'Google LLC', 'Signed by Google LLC'],
    'an invisible character is present' => ['C2PA Signer', "\u{200B}", "Signed by C2PA Signer (\u{FFFD})"],
])->group('SPEC-003');

it('AC2: the signer line on every signed state', function (string $state): void {
    $lines = spec003Lines(Presentation::of(spec003Row(['state' => $state, 'codes' => $state === 'Invalid' ? '["x"]' : '[]', 'signer_common_name' => 'C2PA Signer'])));

    expect($lines)->toContain('Signed by C2PA Signer')
        ->and(implode("\n", $lines))->not->toContain('()');
})->with(['Trusted', 'Valid', 'Invalid'])->group('SPEC-003');

it('AC2: a signed row names its signer and the check', function (): void {
    $lines = spec003Lines(Presentation::of(spec003Row([
        'signer_common_name' => 'C2PA Signer', 'signer_issuer' => 'C2PA Test Signing Cert', 'signed_at' => '2026-01-02T03:04:05+00:00',
    ])));

    expect($lines)->toBe([
        'Signed by C2PA Signer (C2PA Test Signing Cert)',
        'Signed at 2026-01-02T03:04:05+00:00',
        'Checked 2026-11-04 10:00:00 UTC with c2pa-verifier 0.10.0',
    ]);
})->group('SPEC-003');

it('AC3: an invalid row lists its codes in order', function (): void {
    $lines = spec003Lines(Presentation::of(spec003Row([
        'state' => 'Invalid', 'codes' => '["signingCredential.untrusted","assertion.dataHash.mismatch"]',
    ])));

    expect(array_slice($lines, 0, 3))->toBe(['Codes:', 'signingCredential.untrusted', 'assertion.dataHash.mismatch']);
})->group('SPEC-003');

it('AC4: a manifest by URL is a line of text', function (): void {
    $lines = spec003Lines(Presentation::of(spec003Row(['state' => 'none', 'remote_manifest_url' => 'https://example.com/manifest.c2pa'])));

    expect($lines[0] ?? null)->toBe('Refers to Content Credentials elsewhere (not checked): https://example.com/manifest.c2pa');
})->group('SPEC-003');

it('AC5: each reason in words', function (string $reason, string $words): void {
    $presentation = Presentation::of(spec003Row(['state' => 'error', 'reason' => $reason, 'verifier' => null]));

    expect($presentation->headline())->toBe('Could not be checked')
        ->and(spec003Lines($presentation)[0] ?? null)->toBe('Reason: '.$words);
})->with([
    ['interrupted', 'the check did not finish'],
    ['unreadable', 'the file could not be read'],
    ['exception', 'the verifier failed'],
    ['requirements', "this server's PHP is older than 8.3"],
])->group('SPEC-003');

it('AC6: an unreadable row shows no verdict', function (array $row): void {
    $presentation = Presentation::of($row);

    expect($presentation->headline())->toBe('Result unreadable')
        ->and($presentation->lines())->toBe([]);
})->with([
    'unknown state' => [spec003Row(['state' => 'Maybe'])],
    'codes not JSON' => [spec003Row(['state' => 'Invalid', 'codes' => 'not json'])],
    'codes not strings' => [spec003Row(['state' => 'Invalid', 'codes' => '[1]'])],
    'codes not a list' => [spec003Row(['state' => 'Invalid', 'codes' => '{"a":"b"}'])],
    'unknown reason' => [spec003Row(['state' => 'error', 'reason' => 'whatever'])],
    'no state' => [['codes' => '[]']],
])->group('SPEC-003');

it('AC8: control and direction characters become U+FFFD', function (string $character): void {
    $visible = Text::visible("C2PA{$character}Signer");

    expect($visible)->toBe("C2PA\u{FFFD}Signer")
        ->and(str_contains($visible, $character))->toBeFalse();
})->with([
    'NUL' => ["\x00"], 'ESC' => ["\x1B"], 'DEL' => ["\x7F"], 'NEL (C1)' => ["\u{85}"],
    'LRM' => ["\u{200E}"], 'RLM' => ["\u{200F}"], 'RLO' => ["\u{202E}"], 'LRE' => ["\u{202A}"], 'PDI' => ["\u{2069}"],
    // Amendment 4: every Cc and Cf character.
    'ALM' => ["\u{061C}"], 'ZWSP' => ["\u{200B}"], 'ZWNJ' => ["\u{200C}"], 'ZWJ' => ["\u{200D}"], 'WJ' => ["\u{2060}"],
    'invisible plus' => ["\u{2064}"], 'BOM' => ["\u{FEFF}"], 'soft hyphen' => ["\u{00AD}"], 'Arabic number sign' => ["\u{0600}"],
    'interlinear annotation' => ["\u{FFF9}"], 'tag A' => ["\u{E0041}"], 'cancel tag' => ["\u{E007F}"], 'language tag' => ["\u{E0001}"],
])->group('SPEC-003');

it('AC8: visible text is unchanged', function (string $text): void {
    expect(Text::visible($text))->toBe($text);
})->with([
    'Latin' => ['Google LLC'],
    'accents' => ['Société Générale, Zürich'],
    'Greek' => ['Ελλάδα'],
    'Japanese' => ['日本'],
    'Arabic' => ['مصر'],
    'combining mark' => ["e\u{0301}"],
    'an emoji' => ["\u{1F642}"],
    'a space and a no-break space' => ["a b\u{00A0}c"],
    'the replacement character' => ["\u{FFFD}"],
    'a private use character, not Cc or Cf' => ["\u{E000}"],
    'blank-looking letters, a known limit' => ["\u{3164}\u{2800}"],
])->group('SPEC-003');

it('AC8: hidden characters in a signer name show in the line', function (): void {
    $lines = spec003Lines(Presentation::of(spec003Row(['signer_common_name' => "Google\u{200B} LLC\u{E0041}", 'signer_issuer' => "i\u{FEFF}"])));

    expect($lines[0] ?? null)->toBe("Signed by Google\u{FFFD} LLC\u{FFFD} (i\u{FFFD})");
})->group('SPEC-003');

it('AC8: the row\'s values reach the lines made visible', function (): void {
    $lines = spec003Lines(Presentation::of(spec003Row(['signer_common_name' => "a\x00b\u{202E}c", 'signer_issuer' => 'i'])));

    expect($lines[0] ?? null)->toBe("Signed by a\u{FFFD}b\u{FFFD}c (i)");
})->group('SPEC-003');

it('AC8: bytes that are not UTF-8 never pass through as they are', function (): void {
    $visible = Text::visible("a\xFFb");

    expect(mb_check_encoding($visible, 'UTF-8'))->toBeTrue()
        // Replaced, not dropped with the rest of the text.
        ->and(str_starts_with($visible, 'a') && str_ends_with($visible, 'b') && strlen($visible) > 2)->toBeTrue();
})->group('SPEC-003');

it('AC10: a filter value is a state, nothing, or unknown', function (mixed $value, string|false|null $meaning): void {
    expect(FilterValue::parse($value))->toBe($meaning);
})->with([
    'absent' => [null, null],
    'empty' => ['', null],
    'Trusted' => ['Trusted', 'Trusted'],
    'Valid' => ['Valid', 'Valid'],
    'Invalid' => ['Invalid', 'Invalid'],
    'none' => ['none', 'none'],
    'error' => ['error', 'error'],
    'unknown' => ['Maybe', false],
    'other case' => ['valid', false],
    'array' => [['Valid'], false],
    'number' => [1, false],
])->group('SPEC-003');
