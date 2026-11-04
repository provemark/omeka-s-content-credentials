# SPEC-004: Trust settings

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | approved                                          |
| Author     | Maurice van Loon                                  |
| Approved   | Maurice van Loon, 2026-11-04                      |
| Supersedes | —                                                 |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

SPEC-002 verifies without trust settings. Without them no file can be
`Trusted`: the best a signed file gets is `Valid` with
`signingCredential.untrusted`. This spec is M3 of `docs/milestones.md`:
verify against the C2PA trust lists by default, let an administrator
choose about DigiCert timestamps or replace the lists with their own
settings, and record which trust source decided each verdict.

It rests on:
- decision 4 in `NOTES.md` (bundle the lists, DigiCert on by default,
  custom settings replace everything, an age warning, never fetch);
- ADR-0004 (accepted 2026-11-04): what is bundled, the record in code, and
  the two dates, with the warning counted from the comparison date;
- `notes/step-15-settings.md`, measured on Omeka S 4.2.1:
  - the module's configure page is for global admins only and checked
    for CSRF;
  - a refused form loses the posted text unless the module puts it back;
  - a 74 KB setting reads back exactly, also after an upload's flush;
  - uninstalling leaves a module's settings behind;
- `notes/step-16-trust-lists.md`, measured with verifier v0.10.0:
  - the library equals the CLI on 52 file × settings pairs;
  - building the settings costs about 15 ms per upload;
  - the verdicts of the fixtures below are stable until at least 2035,
    unlike the OpenAI image's.

## Scope

**In scope**

- `trust/` in the module: `C2PA-TRUST-LIST.pem`, `C2PA-TSA-TRUST-LIST.pem`
  and `DigiCertTrustedRootG4.crt.pem`, unchanged from Tracefern's `trust/`
  (ADR-0004), with `trust/README.md` (source, commit, licence, SHA-256,
  the CC BY 4.0 notice, the DigiCert fingerprint).
- A record of the bundled files in code: name, SHA-256, upstream commit
  `47009ee`, list date 2026-10-17, comparison date 2026-11-04.
- `tools/trust/compare.sh` (development only, never shipped): compares
  the bundled lists with upstream (ADR-0004).
- The module's configure page (`/admin/module/configure?id=ContentCredentials`):
  - a DigiCert checkbox;
  - a textarea for custom trust settings (the verifier's JSON format);
  - the list date, commit and comparison date;
  - the age warning;
  - a note that settings apply to new uploads only.
- Two Omeka settings: `content_credentials_digicert` and
  `content_credentials_custom_settings`. Nothing is stored on install; an
  absent setting means its default (DigiCert on, no custom settings).
- Verifying each new upload with the settings built from the above, and a
  column `trust` in the verdict row naming the source
  (**SPEC-002 amendment 1**, below).
- One line in the admin block naming the trust source
  (**SPEC-003 amendment 1**, below).
- Uninstalling removes both settings.
- Fixtures copied from Tracefern's `tests/Fixtures/` with their licence
  files:
  - `google-20250919-pixel10-npld-picnic-table.jpg` (public domain);
  - `c2pa-rs-ocsp.jpg` (Apache-2.0 OR MIT);
  - `c2pa-rs-test-trust-anchors.pem` (Apache-2.0 OR MIT).

**Out of scope** (each needs its own spec before it may be built)

- Re-checking media stored before a settings change (M4).
- Fetching or updating a list at run time (never; ADR-0004).
- Merging custom settings with the bundled lists (decision 4: they
  replace).
- An age warning on every admin page (see Open questions).
- Settings per site, and revocation checks (OCSP or CRL fetching).

## Behavior

Acceptance criteria as Given/When/Then. Each is individually testable and will be
covered by a Pest test tagged `->group('SPEC-004')`, in `tests/Unit` when it
needs no Omeka S and in `tests/Integration` (the Docker environment of
`tools/env/`) when it does. At least one criterion MUST be an error /
malformed-input path. This project fails closed: it never shows a verdict
the verifier did not give, a failure is stored as state `error` with a
reason, and the upload always proceeds. Everything read from a file is
untrusted text; a criterion that displays it says how it is escaped (the
Laminas `escapeHtml` view helper, or which other).

**The settings a check uses**, and the value stored in `trust`:

| custom settings | DigiCert | settings passed to the verifier | `trust` |
|---|---|---|---|
| empty (or absent) | on (or absent) | `verify_trust` true; anchors: the C2PA list (`manifest`), the C2PA TSA list (`tsa`), the DigiCert root (`tsa`) | `c2pa-2026-10-17+digicert` |
| empty (or absent) | off | the same without the DigiCert root | `c2pa-2026-10-17` |
| set | either | the custom JSON as it is, through `TrustSettings::fromJson()` | `custom` |
| settings that cannot be built (see AC6) | either | none | `none` |

The date in `trust` is the bundled list date from the record. A check that
ends as `error` before verification (SPEC-002 reasons `requirements`,
`unreadable`, `interrupted`) has `trust` NULL.

Custom trust settings are text from the administrator; their content is
never written to a verdict row or a log. A reason from `TrustException`
may echo parts of that text, so it is shown escaped (`escapeHtml`) and
never logged.

- **AC1 — the bundled files are what the record says**
  - Given `trust/` and the record in code
  - When the unit test runs
  - Then each of the three files has exactly the recorded SHA-256 (the
    C2PA lists `b3949271…421af8ae` and `7182cb22…7cd2c675` in full, as in
    step 16); the DigiCert root's SHA-256 certificate fingerprint is
    `55:2F:7B:DC:…:C8:99:88`; `trust/README.md` names each file's commit,
    licence and SHA-256; and no other file is in `trust/`

- **AC2 — by default, a Pixel 10 photo is Trusted**
  - Given no settings stored (a fresh install)
  - When `google-20250919-pixel10-npld-picnic-table.jpg` is uploaded
  - Then its row has state `Trusted`, no codes, and `trust`
    `c2pa-2026-10-17+digicert`, equal to `vendor/bin/c2pa-verify --settings`
    with the same settings built by the test; the column shows "Verified:
    trusted signer"

- **AC3 — DigiCert decides an expired signer with a DigiCert timestamp**
  - Given `c2pa-rs-ocsp.jpg`
  - When it is uploaded with DigiCert on, and again with DigiCert off
  - Then the first row is `Valid` with `trust` `c2pa-2026-10-17+digicert`,
    and the second `Invalid` (`signingCredential.expired`) with `trust`
    `c2pa-2026-10-17`, each equal to the CLI with the matching settings

- **AC4 — custom settings replace the bundled lists**
  - Given custom settings whose only anchor is
    `c2pa-rs-test-trust-anchors.pem` (trust kind `manifest`), saved on
    the configure page
  - When `fixture-signed.jpg` and the Pixel 10 photo are uploaded
  - Then the first is `Trusted` and the second is not (`Invalid`), both
    with `trust` `custom`, each equal to the CLI with the custom settings;
    the DigiCert option has no effect

- **AC5 — invalid custom settings are refused** *(error path)*
  - Given saved custom settings S
  - When the configure page is submitted with text that is not JSON, with
    JSON the verifier refuses (a top-level `trust.allowed_list`), or with
    more than 1 MiB of text
  - Then the stored value is still S; the page shows "These trust
    settings were not saved:" and the verifier's reason (or "larger than
    1 MiB"), escaped; and the textarea holds the submitted text, escaped,
    so nothing is lost

- **AC6 — settings that cannot be built at upload: check without them**
  *(error path)*
  - Given custom settings that `TrustSettings::fromJson()` refuses,
    written to the `setting` table directly (as a verifier update or a
    damaged database could leave them)
  - When an image is uploaded, and the configure page is opened
  - Then the image is verified without settings, its row has `trust`
    `none` and the CLI's verdict without settings; the upload proceeds;
    and the configure page warns "The saved custom trust settings cannot
    be used; new uploads are checked without a trust list." with the
    verifier's reason, escaped. The same holds when a bundled file cannot
    be read

- **AC7 — the block names the trust source** (with SPEC-003 amendment 1)
  - Given rows with each `trust` value, one with `trust` NULL, and one
    with `trust` `whatever`
  - When their blocks are rendered
  - Then the block ends with the line:
    - "Trust: the C2PA trust list of 2026-10-17, with DigiCert
      timestamps", for `c2pa-2026-10-17+digicert`;
    - "Trust: the C2PA trust list of 2026-10-17", for `c2pa-2026-10-17`;
    - "Trust: custom trust settings", for `custom`;
    - "Trust: none (checked without a trust list)", for `none`.

    A row with NULL has no trust line. `whatever`, and any other value,
    is "Result unreadable". The date shown is the one in the row, not
    today's record

- **AC8 — the configure page**
  - Given a global administrator, and the custom settings
    `</textarea><script>alert(1)</script>` written to the `setting` table
    directly
  - When the configure page renders
  - Then it shows:
    - "C2PA trust list of 2026-10-17 (commit 47009ee), last compared
      with the published list on 2026-11-04";
    - the DigiCert checkbox, checked when no setting is stored;
    - the textarea with the stored text, escaped, so no `<script>`
      element appears;
    - "Changes apply to new uploads; media checked before keep their
      verdict."

    Saving an empty textarea deletes `content_credentials_custom_settings`.
    A site administrator or an editor cannot open the page (Omeka's
    `PermissionDeniedException`, measured in step 15)

- **AC9 — the age warning**
  - Given the record's comparison date 2026-11-04
  - When the age is computed for a given "now"
  - Then it is stale from 183 days after the comparison date
    (2027-05-06) and not before. The configure page shows "The bundled
    trust lists were last compared with the published lists on
    2026-11-04, more than six months ago. A newer release of this module
    may have newer lists." only when it is stale; no other page shows it

- **AC10 — uninstall removes the settings**
  - Given both settings saved on the configure page
  - When the module is uninstalled
  - Then the database snapshot of SPEC-001 equals the one from before
    installing: no `content_credentials_%` row in `setting`

## References

- Specification: C2PA Technical Specification 2.4, §14.4 (trust lists:
  signer and TSA anchors kept apart). The verifier's
  `docs/trust-settings.md` (the settings recipe) and README ("Public
  API": `TrustSettings::fromJson()`, `TrustException`).
- ADR-0004; `notes/step-15-settings.md`; `notes/step-16-trust-lists.md`.
- Oracle: `vendor/bin/c2pa-verify` (provemark/c2pa-verifier v0.10.0)
  with `--settings`, on the same file and the same settings bytes;
  measured on Omeka S 4.2.1, PHP 8.3, in the Docker environment. The
  verdicts depend on the date: the three fixtures give the verdicts above
  at every date from 2026-11-04 to 2035-01-01 (step 16, measured).
- Read, not yet measured: that a checkbox missing from the POST (an
  unchecked box) reaches `handleConfigForm()` as absent; AC3 measures it.

## API sketch

Illustrative only.

```php
namespace ContentCredentials\Trust;            // new Deptrac layer Trust: no Omeka S

final class BundledLists {                      // the record of ADR-0004
    public const LIST_DATE = '2026-10-17';
    public const COMMIT = '47009ee';
    public const COMPARED = '2026-11-04';
    public const FILES = [/* name => sha256 */];
    public static function isStale(\DateTimeImmutable $now): bool;
}

final class TrustChoice {                       // the table above
    public static function from(?string $custom, bool $digicert, string $trustDirectory): self;
    public function settingsJson(): ?string;    // null: verify without settings
    public function source(): string;           // the `trust` value
}
```

`MediaCheck` gets the settings through its `$verify` closure, which now
builds `TrustSettings` inside its own try (a failure there means "verify
without settings, `trust` `none`", not `error`). `Verdict` gains `trust`.

## Amendments proposed with this spec

Both approved by Maurice van Loon with this spec on 2026-11-04, and
recorded in the Amendments sections of SPEC-002 and SPEC-003.

- **SPEC-002 amendment 1:** the table gains a column
  `trust VARCHAR(64) NULL`, after `verifier`, and each verdict is
  produced with the settings of this spec's table. Was: verified without
  settings, no `trust` column. The module is unreleased (version 0.1.0),
  so the `CREATE TABLE` statement changes and there is no `upgrade()`
  (see Open questions). SPEC-002's criteria hold as they are: their
  fixtures give the same states with and without the bundled lists
  (step 16: the signed fixtures are `Valid`, the unsigned `none`; and,
  measured while writing this spec, SPEC-002's altered JPEG is `Invalid`
  with `signingCredential.untrusted` and `assertion.dataHash.mismatch`
  both without settings and with `c2pa+digicert`).
- **SPEC-003 amendment 1:** the block gains the trust line of AC7, after
  the "Checked …" line, and a row whose `trust` is not NULL and not one
  of the four forms (`c2pa-YYYY-MM-DD+digicert`, `c2pa-YYYY-MM-DD`,
  `custom`, `none`) is unreadable. Was: no trust line.

## Open questions

- **The table change.** Changing the `CREATE TABLE` before the first
  release means installations made during development (only the test
  environment, and anyone who tried the module locally) must uninstall
  and install again. An `upgrade()` with `ALTER TABLE` needs a version
  bump, which stays 0.1.0 until release (Maurice, 2026-11-04).
  Recommendation: change the statement, no `upgrade()`; `upgrade()`
  starts with the first release after 0.1.0. *Blocker for approval.*
  *Status 2026-11-04:* answered, the recommendation approved by Maurice
  van Loon with the spec.
- **Where the age warning shows.** Only on the configure page
  (recommendation, as in Tracefern), or on every admin page through
  `view.layout` (measured possible in step 15). Every page reaches
  administrators who never open the configure page, but it shows to
  every editor too, who cannot act on it. *Blocker for approval.*
  *Status 2026-11-04:* answered, the recommendation approved by Maurice
  van Loon with the spec.
- **Where custom settings are stored.** In `Omeka\Settings`
  (recommendation; each request decodes all settings, about 0.2 ms for
  74 KB, step 15), or in a module table read only when verifying.
  *Non-blocker.*
  *Status 2026-11-04:* answered, the recommendation approved by Maurice
  van Loon with the spec.
- **A 5.8 MB fixture.** The Pixel 10 photo is the only file in step 16
  that is `Trusted` by default and stays so until 2035. Recommendation:
  copy it; `Trusted` by default is this spec's point. *Non-blocker.*
  *Status 2026-11-04:* answered, the recommendation approved by Maurice
  van Loon with the spec.

## Traceability

Filled when status becomes `implemented`. Every acceptance criterion maps to at
least one test; every source file maps back to this spec.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1                  | —                           | —                    |
| AC2                  | —                           | —                    |
| AC3                  | —                           | —                    |
| AC4                  | —                           | —                    |
| AC5                  | —                           | —                    |
| AC6                  | —                           | —                    |
| AC7                  | —                           | —                    |
| AC8                  | —                           | —                    |
| AC9                  | —                           | —                    |
| AC10                 | —                           | —                    |
