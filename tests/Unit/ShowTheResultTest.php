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
])->group('SPEC-003');

it('AC8: the row\'s values reach the lines made visible', function (): void {
    $lines = spec003Lines(Presentation::of(spec003Row(['signer_common_name' => "a\x00b\u{202E}c", 'signer_issuer' => 'i'])));

    expect($lines[0] ?? null)->toBe("Signed by a\u{FFFD}b\u{FFFD}c (i)");
})->group('SPEC-003');

it('AC8: bytes that are not UTF-8 never pass through as they are', function (): void {
    expect(mb_check_encoding(Text::visible("a\xFFb"), 'UTF-8'))->toBeTrue();
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
