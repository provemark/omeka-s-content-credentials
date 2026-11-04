<?php

declare(strict_types=1);

/*
 * SPEC-004, the parts that need no Omeka S: the record of the bundled
 * files (BundledLists), which settings a check uses (TrustChoice), a check
 * whose settings cannot be built (MediaCheck), the trust line
 * (Presentation) and the configure page's markup (SettingsPage).
 */

use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Display\Presentation;
use ContentCredentials\Display\SettingsPage;
use ContentCredentials\Tests\Support\VerifierCli;
use ContentCredentials\Trust\BuiltTrust;
use ContentCredentials\Trust\BundledLists;
use ContentCredentials\Trust\TrustChoice;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

const SPEC004_TRUST_DIR = __DIR__.'/../../trust';

/** The hashes measured in notes/step-16-trust-lists.md, written out in full. */
const SPEC004_HASHES = [
    'C2PA-TRUST-LIST.pem' => 'b3949271a07b007e92ef1d18050b55388f5e0d2686a16fd864176c48421af8ae',
    'C2PA-TSA-TRUST-LIST.pem' => '7182cb227c02701be0eebd67ab635fca7f85431c32796fb53b267c657cd2c675',
    'DigiCertTrustedRootG4.crt.pem' => 'ce7d6b44f5d510391be98c8d76b18709400a30cd87659bfebe1c6f97ff5181ee',
];

/** The checks' verifier closures with settings. */
function spec004Check(Closure $trust): MediaCheck
{
    return new MediaCheck(
        static fn (): string => Verifier::VERSION,
        static fn ($stream, ?TrustSettings $settings = null) => (new Verifier)->verify($stream, $settings),
        $trust,
    );
}

/** @return list<string> */
function spec004Lines(Presentation $presentation): array
{
    return array_map(static fn (array $line): string => vsprintf($line[0], $line[1]), $presentation->lines());
}

/** The configure page's markup with plain-PHP translate and escape. */
function spec004Page(bool $digicert = true, string $custom = '', bool $stale = false, ?string $problem = null): string
{
    return SettingsPage::html(
        static fn (string $s): string => $s,
        static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $digicert,
        $custom,
        $stale,
        $problem,
    );
}

it('AC1: the bundled files are exactly what the record says', function (): void {
    $files = array_values(array_diff(scandir(SPEC004_TRUST_DIR) ?: [], ['.', '..']));
    sort($files);

    expect(BundledLists::FILES)->toBe(SPEC004_HASHES)
        ->and($files)->toBe(['C2PA-TRUST-LIST.pem', 'C2PA-TSA-TRUST-LIST.pem', 'DigiCertTrustedRootG4.crt.pem', 'README.md']);
    foreach (BundledLists::FILES as $name => $sha256) {
        expect(hash_file('sha256', SPEC004_TRUST_DIR.'/'.$name))->toBe($sha256, $name);
    }
})->group('SPEC-004');

it('AC1: the DigiCert root has the published fingerprint', function (): void {
    $pem = (string) file_get_contents(SPEC004_TRUST_DIR.'/DigiCertTrustedRootG4.crt.pem');
    $der = base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem), true);

    expect(strtoupper(implode(':', str_split(hash('sha256', (string) $der), 2))))
        ->toBe('55:2F:7B:DC:F1:A7:AF:9E:6C:E6:72:01:7F:4F:12:AB:F7:72:40:C7:8E:76:1A:C2:03:D1:D9:D2:0A:C8:99:88')
        ->and(BundledLists::DIGICERT_FINGERPRINT)->toBe('55:2F:7B:DC:F1:A7:AF:9E:6C:E6:72:01:7F:4F:12:AB:F7:72:40:C7:8E:76:1A:C2:03:D1:D9:D2:0A:C8:99:88');
})->group('SPEC-004');

it('AC1: the README names each file\'s commit, licence and hash', function (): void {
    $readme = (string) file_get_contents(SPEC004_TRUST_DIR.'/README.md');

    expect($readme)->toContain(BundledLists::COMMIT)
        ->and($readme)->toContain(BundledLists::LIST_DATE)
        ->and($readme)->toContain(BundledLists::COMPARED)
        ->and($readme)->toContain('CC BY 4.0')
        ->and($readme)->toContain(BundledLists::DIGICERT_FINGERPRINT);
    foreach (SPEC004_HASHES as $name => $sha256) {
        expect($readme)->toContain($name)->and($readme)->toContain($sha256);
    }
})->group('SPEC-004');

it('AC2, AC3: the bundled settings, with and without DigiCert', function (bool $digicert, string $source, array $kinds): void {
    $json = TrustChoice::bundledJson($digicert, SPEC004_TRUST_DIR);
    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $decoded = is_array($decoded) ? $decoded : [];
    $trust = is_array($decoded['trust'] ?? null) ? $decoded['trust'] : [];
    $anchors = is_array($trust['anchors'] ?? null) ? $trust['anchors'] : [];
    $built = TrustChoice::build(null, $digicert, SPEC004_TRUST_DIR);

    expect($decoded['verify'] ?? null)->toBe(['verify_trust' => true])
        ->and(array_column($anchors, 'trust_kind'))->toBe($kinds)
        ->and($json)->toBe((string) file_get_contents(VerifierCli::settingsFile($digicert)))
        ->and($built->source)->toBe($source)
        ->and($built->settings)->toBeInstanceOf(TrustSettings::class)
        ->and($built->problem)->toBeNull();
})->with([
    'DigiCert on' => [true, 'c2pa-2026-10-17+digicert', ['manifest', 'tsa', 'tsa']],
    'DigiCert off' => [false, 'c2pa-2026-10-17', ['manifest', 'tsa']],
])->group('SPEC-004');

it('AC2: no custom settings means the bundled lists', function (?string $custom): void {
    expect(TrustChoice::build($custom, true, SPEC004_TRUST_DIR)->source)->toBe('c2pa-2026-10-17+digicert');
})->with(['absent' => [null], 'empty' => [''], 'whitespace' => ["  \n"]])->group('SPEC-004');

it('AC4: custom settings replace the bundled lists, whatever DigiCert says', function (bool $digicert): void {
    $custom = (string) file_get_contents(VerifierCli::settingsFile(anchors: [[VerifierCli::fixture('c2pa-rs-test-trust-anchors.pem'), 'manifest']]));
    $built = TrustChoice::build($custom, $digicert, SPEC004_TRUST_DIR);
    $report = (new Verifier)->verify(fopen(VerifierCli::fixture('fixture-signed.jpg'), 'rb') ?: throw new RuntimeException, $built->settings);

    expect($built->source)->toBe('custom')
        ->and($built->settings?->anchorSets)->toHaveCount(1)
        ->and($report->result->state->value)->toBe('Trusted');
})->with([true, false])->group('SPEC-004');

it('AC5: custom settings that are not settings are refused with a reason', function (string $custom, string $reason): void {
    expect(TrustChoice::refusal($custom))->toContain($reason);
})->with([
    'not JSON' => ['not json', 'JSON'],
    'allowed_list at the top' => ['{"trust":{"allowed_list":"x"}}', 'allowed_list'],
    'over 1 MiB' => [str_repeat(' ', 1048577), 'larger than 1 MiB'],
])->group('SPEC-004');

it('AC5: valid custom settings and an empty text are not refused', function (string $custom): void {
    expect(TrustChoice::refusal($custom))->toBeNull();
})->with([
    'the test root' => [(string) file_get_contents(VerifierCli::settingsFile(anchors: [[VerifierCli::fixture('c2pa-rs-test-trust-anchors.pem'), 'manifest']]))],
    'empty' => [''],
])->group('SPEC-004');

it('AC5: the page gives back the refused text, escaped', function (): void {
    expect(spec004Page(custom: '</textarea><b>x'))->toContain('&lt;/textarea&gt;&lt;b&gt;x</textarea>')
        ->and(spec004Page(custom: '</textarea><b>x'))->not->toContain('</textarea><b>');
})->group('SPEC-004');

it('AC6: settings that cannot be built mean no settings, trust none, and a reason', function (?string $custom, string $directory): void {
    $built = TrustChoice::build($custom, true, $directory);

    expect($built->settings)->toBeNull()
        ->and($built->source)->toBe('none')
        ->and($built->problem !== null && $built->problem !== '')->toBeTrue();
})->with([
    'custom settings the verifier refuses' => ['not json', SPEC004_TRUST_DIR],
    'a bundled file that cannot be read' => [null, sys_get_temp_dir().'/spec004-no-trust-here'],
])->group('SPEC-004');

it('AC6: a check whose settings fail is verified without them, trust none', function (): void {
    $verdict = spec004Check(static fn (): BuiltTrust => throw new RuntimeException('settings'))->check(VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');
    $cli = VerifierCli::report(VerifierCli::fixture('fixture-signed.jpg'));

    expect($verdict->state)->toBe($cli['validation_state'])
        ->and($verdict->trust)->toBe('none');
})->group('SPEC-004');

it('AC2: a check passes the built settings to the verifier and records their source', function (): void {
    $verdict = spec004Check(static fn (): BuiltTrust => TrustChoice::build(null, true, SPEC004_TRUST_DIR))
        ->check(VerifierCli::fixture('google-20250919-pixel10-npld-picnic-table.jpg'), '8.3.0');

    expect($verdict->state)->toBe('Trusted')
        ->and($verdict->trust)->toBe('c2pa-2026-10-17+digicert');
})->group('SPEC-004');

it('AC6: an error before verification has no trust source', function (): void {
    $verdict = spec004Check(static fn (): BuiltTrust => TrustChoice::build(null, true, SPEC004_TRUST_DIR))->check(null, '8.3.0');

    expect($verdict->state)->toBe('error')->and($verdict->trust)->toBeNull();
})->group('SPEC-004');

it('AC6: the page warns when the saved custom settings cannot be used', function (): void {
    $page = spec004Page(custom: 'not json', problem: 'Syntax <error>');

    expect($page)->toContain('The saved custom trust settings cannot be used; new uploads are checked without a trust list.')
        ->and($page)->toContain('Syntax &lt;error&gt;')
        ->and(spec004Page())->not->toContain('cannot be used');
})->group('SPEC-004');

it('AC7: the block names the trust source', function (?string $trust, ?string $line): void {
    $lines = spec004Lines(Presentation::of([
        'state' => 'Valid', 'codes' => '[]', 'verifier' => '0.10.0', 'checked_at' => '2026-11-04 10:00:00', 'trust' => $trust,
    ]));

    expect($lines[0] ?? null)->toBe('Checked 2026-11-04 10:00:00 UTC with c2pa-verifier 0.10.0')
        ->and($lines[1] ?? null)->toBe($line);
})->with([
    'bundled with DigiCert' => ['c2pa-2026-10-17+digicert', 'Trust: the C2PA trust list of 2026-10-17, with DigiCert timestamps'],
    'bundled' => ['c2pa-2026-10-17', 'Trust: the C2PA trust list of 2026-10-17'],
    'a row from an older list' => ['c2pa-2025-01-31', 'Trust: the C2PA trust list of 2025-01-31'],
    'custom' => ['custom', 'Trust: custom trust settings'],
    'none' => ['none', 'Trust: none (checked without a trust list)'],
    'NULL' => [null, null],
])->group('SPEC-004');

it('AC7: an unknown trust value makes the row unreadable', function (string $trust): void {
    expect(Presentation::of(['state' => 'Valid', 'codes' => '[]', 'trust' => $trust])->headline())->toBe('Result unreadable');
})->with(['whatever', 'c2pa-2026-1-7', 'c2pa-2026-10-17+other', 'Custom', ''])->group('SPEC-004');

it('AC8: the page shows the list, the option, the text and the note', function (): void {
    $page = spec004Page(custom: '</textarea><script>alert(1)</script>');

    expect($page)->toContain('C2PA trust list of 2026-10-17 (commit 47009ee), last compared with the published list on 2026-11-04')
        ->and($page)->toMatch('#<input type="checkbox" name="content_credentials_digicert" id="content-credentials-digicert" value="1" checked>#')
        ->and($page)->toContain('&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;</textarea>')
        ->and($page)->not->toContain('<script>')
        ->and($page)->toContain('Changes apply to new uploads; media checked before keep their verdict.')
        ->and(spec004Page(digicert: false))->toMatch('#value="1">#')
        ->and(spec004Page(digicert: false))->not->toMatch('#name="content_credentials_digicert"[^>]*checked#');
})->group('SPEC-004');

it('AC9: the copy is stale from 183 days after the comparison', function (string $now, bool $stale): void {
    expect(BundledLists::isStale(new DateTimeImmutable($now)))->toBe($stale);
})->with([
    'the comparison day' => ['2026-11-04T00:00:00Z', false],
    'the day before 183 days' => ['2027-05-05T23:59:59Z', false],
    '183 days' => ['2027-05-06T00:00:00Z', true],
    'later' => ['2030-01-01T00:00:00Z', true],
])->group('SPEC-004');

it('AC9: the page shows the age warning only when stale', function (): void {
    $warning = 'The bundled trust lists were last compared with the published lists on 2026-11-04, more than six months ago. A newer release of this module may have newer lists.';

    expect(spec004Page(stale: true))->toContain($warning)
        ->and(spec004Page(stale: false))->not->toContain('more than six months ago');
})->group('SPEC-004');
