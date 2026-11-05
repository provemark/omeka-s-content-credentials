# Step 25 (M5.5): SPEC-006, keeping the evidence

2026-11-05. SPEC-006 implemented on steps 23 and 24 and ADR-0005. Every
check with Content Credentials now keeps the verifier's evidence document,
and the admin interface shows it.

## What was built

- `MediaCheck::checked()` gives a `Checked`: the verdict and, for a file
  with a manifest, an `Evidence` (the document and the SHA-256 of the
  settings text). `check()` stays, and gives only the verdict.
- `VerdictStore::write()` takes the evidence as well. `DbalVerdictStore`
  inserts it and upserts the verdict in one transaction. Without evidence
  it only upserts, as before.
- `Schema` creates and drops the second table.
- `EvidenceController` has three pages under two routes, each with its own
  `__NAMESPACE__`. Each page reads the media item through Omeka's API
  before it serves anything; `Module::onBootstrap()` allows every role on
  the controller, so that read is the limit.
- `EvidenceHtml` builds the two HTML pages and escapes all row values, so
  that PHPStan sees the code; the two templates only call it.
- `VerdictHtml::block()` gains the evidence line. `EvidenceReader::count()`
  answers 0 when the table cannot be read, so the media page still opens.

`Evidence` and `Checked` are `final class` with readonly properties, not
`final readonly class`, which PHP 8.1 cannot parse: `MediaCheck` also runs
below PHP 8.3, where it only reports that it cannot check (SPEC-002 AC12).
That this matters was reasoned, not measured.

## Tests, seen red

- `tests/Unit/EvidenceTest.php`: all 9 failed before the code, the Error
  cases because the classes did not exist.
- `tests/Integration/EvidenceTest.php`:
  - First run: all failed on the setup's check that the table exists, which
    is red for the wrong reason.
  - Second run, with the table made by hand from ADR-0005 and then dropped:
    11 failed, 9 passed. The failures were AC1 (2), AC2, AC4, AC5, AC6, AC7,
    AC8, AC9, AC10 and AC12, each on its own behaviour.
  - The 9 that passed: AC3 (3 cases), because nothing was stored yet, and
    AC11 (6 cases), because the routes did not exist.

Falsified, each by changing the code for one run and restoring it (compared
with `cmp`):

| change | result |
|---|---|
| evidence also for `none` | AC3: 3 failed |
| an unknown row throws `RuntimeException` with a path | AC11: 2 failed, 500 instead of 404 |
| `NotFoundException` whose message holds a path | AC11: all passed. Omeka does not show the message on its 404 page. This is a measurement, and it means AC11's check of the body has **not** been falsified |
| no transaction around the insert and the upsert | the second AC9 test failed: the evidence row stayed |

That second AC9 test goes beyond the criterion's wording: it covers the
other side of ADR-0005's "together or not at all". A trigger on the verdict
table refuses the update, so only the upsert fails, inside the transaction.

## Found while building

- **SPEC-001 AC1 named one table.** A second table made it red. ADR-0005
  and the SPEC-006 draft should have proposed an amendment and did not.
  This was my omission, seen when reading the test before changing the
  schema. SPEC-001 amendment 3, approved by Maurice.
- **U+202E in my own files.** AC7's `‮` reached the test file and
  SPEC-006 as the character itself, instead of the six ASCII characters. A
  failing assertion showed it; the bytes confirmed it. Both are corrected.
  The spec's correction went beyond Traceability, was made before it was
  approved, and was approved by Maurice afterwards.
- **SPEC-003 AC9's test** asked for `per_page=1000`, and the test
  environment now has more `Valid` media than that: the diff showed the
  list cut off after 1 000 ids. It now asks for `per_page=1000000`. This is
  a limit of the test, not of the module.
- **The stand-in stores** in `tests/Unit/VerifyOnUploadTest.php` and
  `RecheckTest.php` follow the interface's new signature.
- `HttpResponse` gained the response headers, for AC8.

## Limits

- **No real verifier update.** AC5 shows that a row set to `0.9.0` stays
  byte for byte through two runs. That is what the module controls; no
  other verifier version was installed.
- **An installation without the table.** There is no `upgrade()`
  (ADR-0005). A development installation from before this step stores
  `error`/`interrupted` for every upload with a manifest until it is
  installed again; AC9 is that behaviour. Installing again removes its
  verdicts. Measured through AC9, not through an old installation.
- **Not looked at in a browser.** The pages have only been read by tests
  and as HTML.
- **Growth** is as in step 24; nothing limits it, and a full run adds a row
  per checkable media item with a manifest.
