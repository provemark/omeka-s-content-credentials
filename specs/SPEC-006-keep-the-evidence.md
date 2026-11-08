# SPEC-006: Keep the evidence

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | implemented                                       |
| Author     | Maurice van Loon                                  |
| Approved   | Maurice van Loon, 2026-11-05                      |
| Supersedes | —                                                 |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

A verdict says what held when a file was checked. It does not say what
it rested on, and a later check replaces it (SPEC-005). Certificates
expire, revocation answers change, and trust lists are revised. After a
few years nobody can show any more why a file was `Trusted` when it came
in. For archives and museums that record is the point. This is M5 of
`docs/milestones.md`: the stored document must be byte-identical to
`evidenceToJson()` and must survive a verifier update.

The verifier writes that record as a document: `evidenceToJson()`,
format version 1 of its `docs/evidence-format.md`. The document holds
the verdict, the certificates, time-stamp tokens and OCSP responses as
bytes, and the time each manifest was judged at. Anyone can check those
bytes later with `openssl`, without this module or this verifier.

It rests on:
- decision 5 in `NOTES.md`: one row per check, never updated, in a table
  of the module's own;
- `docs/adr/ADR-0005-evidence-table.md` (accepted 2026-11-05): the
  table, its columns, the transaction with the verdict, `fromJson()`
  kept, deletion with the media item and on uninstall, and the route
  that reads the media item through Omeka's API first;
- `notes/step-23-evidence-document.md`, measured outside Omeka with
  verifier 0.10.0:
  - every input that reaches the verifier gives a document;
  - the documents are ASCII and byte-identical for a fixed time, across
    PHP 8.3 and 8.5;
  - by the clock only `judged_at` changes;
  - a file without a manifest gets a document that says `Invalid`;
- `notes/step-24-evidence-in-omeka.md`, measured in Omeka S 4.2.1:
  - a route needs its own `__NAMESPACE__`;
  - reading the media item through the API gives the media page's
    access;
  - `LONGTEXT` and the download give the bytes back;
  - the foreign key removes the rows with the media item;
  - the table grows by about 6 MB on disk per run over 1 100 media;
- SPEC-002 (the check on upload, `Recorder`, the `error` reasons),
  SPEC-003 (the block), SPEC-004 (the trust settings and `trust`), and
  SPEC-005 (the runs);
- the Omeka S 4.2.1 source: `Omeka\Mvc\MvcListeners` (the admin redirect
  to the login page and the ACL check), `Omeka\Service\AclFactory`, and
  the `api()` controller plugin.

## Scope

**In scope**

- A table `content_credentials_evidence` (ADR-0005), created in
  `install()` and dropped in `uninstall()`.
- A row for every check whose verdict is `Trusted`, `Valid` or `Invalid`
  (open question 3). It is written on upload (SPEC-002) and on every run
  (SPEC-005), in one transaction with the final verdict, and only ever
  inserted (open question 2).
- In the block of SPEC-003, a line saying how many checks are kept, with
  a link to the media item's evidence page (SPEC-003 amendment 2, below).
- Three admin pages of the module's own, under its own route:
  - the evidence of a media item: one row per check, newest first;
  - one document, shown escaped;
  - one document as a download.

  Each reads the media item through Omeka's API before it shows
  anything, and is open to whoever can see the media item (open
  question 1).

**Out of scope** (each needs its own spec before it may be built)

- Evidence for checks before this spec. Existing media get evidence
  through a run of SPEC-005.
- Keeping evidence after its media item is deleted or the module is
  uninstalled (ADR-0005). A warning on Omeka's uninstall page belongs to
  M7.
- The public site, the REST API, and exporting all evidence at once.
- Re-verifying a stored document, or reading it back into a verdict.
- Signing or time-stamping the stored document.
- A revocation source that fetches OCSP: this module opens no network.

## Behavior

Acceptance criteria as Given/When/Then, tagged `->group('SPEC-006')`.
They run in `tests/Integration` against the Docker environment (Omeka S
4.2.1, PHP 8.3, MariaDB 11.4), except AC9, which needs a store that
fails and runs in `tests/Unit`.

- **The oracle** is the verifier library on the host:
  `(new Verifier)->verify($stream, TrustSettings::fromJson($text),
  $time)->evidenceToJson()`, with the settings text the module used.
- **`judged_at`.** A document whose manifests are judged by the clock
  (`time_source` `"clock"`) is compared with the oracle at the time in
  its own `judged_at`, given as `$time`. That `judged_at` must lie
  between the moments just before and just after the check.

  The verifier then writes `"given"` where the module's document says
  `"clock"`. The comparison replaces only that word, nowhere else.
- **Escaping.** Text from a document is shown only through
  `escapeHtml`.

- **AC1 — an upload keeps its evidence**
  - Given the bundled lists with DigiCert on (the default)
  - When `fixture-signed.jpg` and the Pixel 10 photo are uploaded
  - Then each has exactly one evidence row, and:
    - `document` equals the oracle: byte for byte for the Pixel 10 photo
      (judged at its trusted time-stamp), and as described above for
      `fixture-signed.jpg`;
    - `sha256` is the SHA-256 of `document`;
    - `checked_sha256` is the SHA-256 of the uploaded file;
    - `verifier` is `Verifier::VERSION`;
    - `trust` is the verdict row's `trust`;
    - `trust_sha256` is the SHA-256 of `TrustChoice::bundledJson(true, …)`;
    - `checked_at` is the verdict row's `checked_at`

- **AC2 — custom settings and no settings**
  - Given custom trust settings (SPEC-004 AC4), and then settings that
    cannot be built (SPEC-004 AC6)
  - When `fixture-signed.png` is uploaded under each
  - Then:
    - under custom settings, `trust` is `custom` and `trust_sha256` is
      the SHA-256 of the custom text as saved;
    - under settings that cannot be built, both are as the verdict row
      has them, `trust_sha256` NULL, and the document equals the oracle
      without settings

- **AC3 — a file without Content Credentials keeps no evidence**
  (open question 3)
  - Given `fixture-unsigned.jpg`, `.png` and `.webp`
  - When they are uploaded
  - Then their verdict is `none` (SPEC-002 AC3) and they have no
    evidence row

- **AC4 — a check that ends in `error` keeps no evidence** *(error path)*
  - Given an original that cannot be read (SPEC-002 AC8), and a check
    that throws (SPEC-002 AC9)
  - When they are checked
  - Then their verdict is `error` with its reason and they have no
    evidence row

- **AC5 — every run adds, nothing is changed** (open question 2)
  - Given a media item with one evidence row, whose `verifier` and
    `document` the test has set to `0.9.0` and a text of its own (a
    document from an older verifier)
  - When a run of SPEC-005 ("Check all media") ends twice
  - Then:
    - the media item has three rows;
    - the first is unchanged in every column;
    - the second and third are new and ordered by `id`;
    - the verdict row matches the newest row

- **AC6 — the block counts and links**
  (SPEC-003 amendment 2)
  - Given media with three, one and no evidence rows
  - When their media pages and Details panels are opened by a global
    administrator
  - Then the block ends with "Evidence: 3 checks kept" or "Evidence: 1
    check kept", a link to `/admin/content-credentials/media/{id}/evidence`.
    A media item without rows has no such line, and its block holds no
    `<a`

- **AC7 — the evidence page and the document page**
  - Given a media item with two evidence rows, the second with a document
    the test has set to contain
    `"active_manifest": "<script>alert(1)</script>\u202e"`
  - When a global administrator opens the evidence page and the second
    row's document page
  - Then:
    - the evidence page lists both rows, newest first, each with
      `checked_at` (UTC), `verifier`, the trust line of SPEC-004 AC7,
      `checked_sha256`, and links to its document page and its download;
    - the document page shows the document in a `<pre>`, escaped with
      `escapeHtml`: `&lt;script&gt;` appears, `<script>alert(1)` does
      not, and the `\u202e` stays the six characters the document holds

- **AC8 — the download is the stored document**
  - Given an evidence row
  - When its download is requested
  - Then the response is 200 with:
    - `Content-Type: application/json`;
    - `Content-Disposition: attachment;
      filename="media-{media id}-evidence-{row id}.json"`;
    - `X-Content-Type-Options: nosniff`;
    - a body whose bytes equal `document` and whose SHA-256 equals
      `sha256`

- **AC9 — the evidence cannot be stored: no new verdict, the upload
  proceeds** *(error path)*
  - Given a store whose evidence insert throws
  - When a media item is checked
  - Then:
    - the verdict row is the `error`/`interrupted` row that `Recorder`
      writes first (SPEC-002 AC10), not the new verdict;
    - no evidence row was added;
    - nothing is thrown out of `Recorder::record()`;
    - in Omeka, with the evidence table renamed by the test, an upload
      still answers 302 and the media item exists

- **AC10 — access follows the media item** (open question 1)
  - Given a public media item P, and the media item Q of a private item
    owned by the global admin, both with evidence
  - When each of anonymous, `global_admin`, `site_admin`, `editor`,
    `reviewer`, `author` and `researcher` opens the evidence page, a
    document page and a download of P and of Q
  - Then:
    - each answer is the one the same user gets for `/admin/media/P` and
      `/admin/media/Q`: 302 to the login page for anonymous, 200 where
      the media page is 200, and 404 where it is 404;
    - an author and a researcher therefore get 404 for every page of Q

- **AC11 — an unknown or malformed address** *(error path)*
  - Given no evidence row 999999999 and no media item 999999999
  - When a global administrator requests the document page, the
    download, and the evidence page with those ids, and with `abc` as id
  - Then each answer is 404, and no stack trace or path is in the body

- **AC12 — deleting a media item, and uninstalling**
  - Given media with evidence rows
  - When an item is deleted through the API, and later the module is
    uninstalled
  - Then the deleted item's media have no rows left, and after uninstall
    the table `content_credentials_evidence` does not exist and the
    schema equals the one before install (SPEC-001's snapshot)

## SPEC-003 amendment 2 (approved with this spec)

The block gains, as its last line, AC6's evidence line, when the media
item has at least one evidence row. It is a link: the first link in the
block. AC4 of SPEC-003 ("the block contains no `<a` and no `href`")
still holds, because AC4's row is `none`, and a `none` check keeps no
evidence (AC3). Was: no evidence line. Tested under SPEC-006 AC6.

## References

- Specification: the verifier's `docs/evidence-format.md` (format
  version 1, SPEC-089 there). The Omeka S developer documentation
  (`Modules`, `Server Events` under `omeka.org/s/docs/developer/`). The
  Omeka S 4.2.1 source: `application/src/Mvc/MvcListeners.php`,
  `application/src/Service/AclFactory.php`,
  `application/src/Mvc/Controller/Plugin/Api.php`,
  `application/config/module.config.php` (the `admin` route and its
  `__NAMESPACE__`).
- Oracle: provemark/c2pa-verifier v0.10.0 as a library,
  `VerificationReport::evidenceToJson()`, on `tests/Fixtures/c2pa/`, with
  the settings text the module used. Omeka S 4.2.1 on PHP 8.3.36.
- Measured: steps 23 and 24.
- Reasoned:
  - that a document from an older verifier stays readable because
    format version 1 only adds keys (the verifier's
    `docs/evidence-format.md`);
  - the size per run over 3 000 media (step 24);
  - that the certificates in a document are no more confidential than
    the original, which carries them (open question 1).

## API sketch

```php
namespace ContentCredentials\Check;

final readonly class Checked            // what MediaCheck::check() gives
{
    public Verdict $verdict;
    public ?string $evidence;           // evidenceToJson(); null for none and error
}

interface VerdictStore
{
    // the final verdict and, when there is one, its document, in one transaction
    public function write(int $mediaId, Verdict $verdict, ?Evidence $evidence = null): void;
}

final readonly class Evidence
{
    public string $document;
    public ?string $trustSha256;        // of the settings text given to fromJson()
}
```

```php
namespace ContentCredentials\Omeka;

final class EvidenceSchema { /* CREATE TABLE content_credentials_evidence … (ADR-0005) */ }
final class EvidenceReader { public function count(int $mediaId): int; public function rows(int $mediaId): array; public function row(int $id): ?array; }
final class EvidenceController extends AbstractActionController
{
    // route admin/content-credentials-evidence, __NAMESPACE__ ContentCredentials\Omeka
    //   /admin/content-credentials/media/:media-id/evidence      browseAction
    //   /admin/content-credentials/evidence/:id                  showAction
    //   /admin/content-credentials/evidence/:id/download         downloadAction
    // each: $this->api()->read('media', $mediaId) first; NotFound → 404
}
```

`BuiltTrust` gains the SHA-256 of the settings text it was built from.
The ACL allows every role on `EvidenceController`; the API read is the
limit (AC10). `Module.php` stays parseable on PHP 8.1 (ADR-0001).

## Open questions

Each with my recommendation and the trade-off. Maurice decides.

1. **Who may see and download the evidence.** Recommended: whoever can
   see the media item in the admin interface (AC10), through the API
   read of ADR-0005 point 8.
   - The document holds the file's own certificates and time-stamps,
     which anyone who can download the original already has.
   - It adds only the trust settings' hashes and the time of the check.

   Alternative: global and site administrators only, by the ACL (step
   24's "no rule": editors and reviewers then get Omeka's 500 for a
   denied page, and the line in the block is shown to administrators
   only). Trade-off: more confidentiality for something that is hardly
   confidential, and editors and reviewers without the record.
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
2. **Every check, or only a changed document.** Recommended: every check
   adds a row, as decision 5 says ("one row per check").
   - A re-check is a fact of its own: at that time, with those lists,
     this was the outcome.
   - Step 24: 79% of the documents differ at every run anyway, because of
     `judged_at`, so skipping the identical ones saves about a fifth.

   Alternative: add a row only when `sha256` differs from the media's
   latest row. Trade-off: about 20% less growth, but the list of checks
   is no longer the list of checks.
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
3. **Files without Content Credentials.** Recommended: no evidence row
   for `none` (AC3).
   - The document for such a file says `Invalid` with no manifest (step
     23), which contradicts the module's `none` (SPEC-002 AC3).
   - It holds nothing to keep: no certificate, no time-stamp.
   - The verdict row already records the check, with the file's SHA-256.

   Alternative: keep the document too, and show it only under the
   module's `none`. Trade-off: one row for each unsigned file per check,
   a document that contradicts the page, and SPEC-003 AC4 would need a
   change, since a `none` block would then hold a link.
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.

## Amendments

- **Correction (2026-11-05, approved by Maurice van Loon):** AC7's two
  mentions of `\u202e` held the character U+202E itself, a mistake made
  when the draft was written. They now hold the six ASCII characters the
  criterion describes. The meaning is unchanged.

- **Amendment 1 (2026-11-08, approved by Maurice van Loon):** AC10, from finding L1 of the security
  review (`notes/step-58-security-review.md`). The evidence controller is allowed for Omeka's six
  roles only (`researcher`, `author`, `reviewer`, `editor`, `site_admin`, `global_admin`). AC10 now
  also reads: given a role that another module adds (as the Guest module adds `guest`), Omeka's ACL
  does not allow it the evidence controller, while each of the six roles is allowed it as before.
  Was: `allow(null, …)`, every role. Record: `notes/step-59-evidence-for-omeka-roles.md`.

## Traceability

Every acceptance criterion maps to at least one test; every source file
maps back to this spec. All tests carry `->group('SPEC-006')`, except
SPEC-001's AC1 (`SPEC-001`). The record is
`notes/step-25-keep-the-evidence.md`.

| Acceptance criterion | Test (file :: name) | Source (file/symbol) |
|----------------------|---------------------|----------------------|
| AC1 | tests/Integration/EvidenceTest.php :: AC1: an upload keeps its evidence; tests/Unit/EvidenceTest.php :: AC1: a signed file gives the verifier's document | src/Check/MediaCheck.php :: checked(); src/Check/Evidence.php; src/Check/Checked.php; src/Omeka/DbalVerdictStore.php :: write(); src/Omeka/Schema.php :: create() |
| AC2 | tests/Integration/EvidenceTest.php :: AC2: custom settings and settings that cannot be built | src/Trust/TrustChoice.php :: build(); src/Trust/BuiltTrust.php :: sha256 |
| AC3 | tests/Integration/EvidenceTest.php :: AC3: a file without Content Credentials keeps no evidence; tests/Unit/EvidenceTest.php :: AC3: a file without Content Credentials gives no document | src/Check/MediaCheck.php :: checked() |
| AC4 | tests/Integration/EvidenceTest.php :: AC4: an unreadable original keeps no evidence; tests/Unit/EvidenceTest.php :: AC4: a check that ends in error gives no document | src/Check/MediaCheck.php :: checked() |
| AC5 | tests/Integration/EvidenceTest.php :: AC5: every run adds, nothing is changed | src/Check/Recorder.php :: record(); src/Check/RecheckCounts.php :: write(); src/Omeka/DbalVerdictStore.php :: write() |
| AC6 | tests/Integration/EvidenceTest.php :: AC6: the block counts and links | src/Omeka/VerdictHtml.php :: block(); src/Omeka/AdminDisplay.php :: echoBlock(); src/Omeka/EvidenceReader.php :: count() |
| AC7 | tests/Integration/EvidenceTest.php :: AC7: the evidence page and the document page | src/Omeka/EvidenceController.php :: browseAction(), showAction(); src/Omeka/EvidenceHtml.php; src/Omeka/EvidenceReader.php; view/content-credentials/evidence/; config/module.config.php |
| AC8 | tests/Integration/EvidenceTest.php :: AC8: the download is the stored document | src/Omeka/EvidenceController.php :: downloadAction() |
| AC9 | tests/Integration/EvidenceTest.php :: AC9: an evidence table that cannot be written: no new verdict, the upload proceeds; AC9: a verdict that cannot be written takes its evidence back; tests/Unit/EvidenceTest.php :: AC9 (2 cases) | src/Omeka/DbalVerdictStore.php :: write() (the transaction); src/Check/Recorder.php :: record() |
| AC10 | tests/Integration/EvidenceTest.php :: AC10: access follows the media item; AC10: a role another module adds is not allowed the evidence controller | Module.php :: onBootstrap(); src/Omeka/EvidenceController.php :: readMedia(); src/Omeka/EvidenceControllerFactory.php |
| AC11 | tests/Integration/EvidenceTest.php :: AC11: an unknown or malformed address is 404, with nothing inside | config/module.config.php (the route constraints); src/Omeka/EvidenceController.php :: visibleRow() |
| AC12 | tests/Integration/EvidenceTest.php :: AC12: deleting a media item, and uninstalling; tests/Integration/InstallAndUninstallTest.php :: AC1 (SPEC-001 amendment 3) | src/Omeka/Schema.php :: create(), drop() |
