# SPEC-003: Show the result in the admin interface

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | implemented                                       |
| Author     | Maurice van Loon                                  |
| Approved   | Maurice van Loon, 2026-11-04                      |
| Supersedes | —                                                 |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

SPEC-002 stores a verdict for every new JPEG, PNG and WebP, but nobody can
see it without a database query. This spec is M2 of `docs/milestones.md`:
show the verdict to administrators on the media page, in the media
browse, and let them filter the browse by it.

It rests on `notes/step-13-admin-views.md` (Omeka S 4.2.1, measured):
`view.show.sidebar` and `view.details` fire on the admin media page and
its Details panel with the media at hand; a module's column type added to
`column_defaults.admin.media` shows in the browse without user settings;
`view.advanced_search`, `view.search.filters` and `api.search.query`
together give a working filter; text through `escapeHtml` arrives
escaped; a `sort_by` the adapter does not know is silently ignored. And on
`notes/step-10-storage-and-after-flush.md`: the module's entity can be
read and joined in DQL.

Everything shown from a row came from the file and is untrusted text
(SPEC-002). The vocabulary stays the verifier's: the C2PA 2.4 §15 codes
are shown verbatim; only the headlines below are the module's words.

## Scope

**In scope**

- A block in the admin media page's sidebar (`view.show.sidebar`) and in
  the browse's Details panel (`view.details`).
- A column type `content_credentials` for the admin media browse, added to
  `column_defaults.admin.media`, showing the headline; `getSortBy()`
  returns `null` (no sorting).
- A filter by stored state on the admin media browse: a field in the
  advanced search, a label among the active filters, and the query.
- Reading rows through the entity `ContentCredentials\Entity\StoredVerdict`.
- The English source strings below, passed through Omeka's `translate`;
  no translation files.

**Out of scope** (each needs its own spec before it may be built)

- Sorting by verdict; a filter for "not checked".
- The public site, the item page, and the REST API's representation of
  media.
- Trust settings and the `Trusted` state's reachability (M3); re-checking
  (M4); the evidence document (M5); the AI label (M6).

## Behavior

Acceptance criteria as Given/When/Then. Each is individually testable and will be
covered by a Pest test tagged `->group('SPEC-003')`, in `tests/Unit` when it
needs no Omeka S and in `tests/Integration` (the Docker environment of
`tools/env/`) when it does. At least one criterion MUST be an error /
malformed-input path. This project fails closed: it never shows a verdict
the verifier did not give, a failure is stored as state `error` with a
reason, and the upload always proceeds. Everything read from a file is
untrusted text; a criterion that displays it says how it is escaped (the
Laminas `escapeHtml` view helper, or which other).

Wording (English source strings; the column shows the headline, the block
shows the headline and the lines below it):

| stored | headline | lines |
|---|---|---|
| `Trusted` | Verified: trusted signer | "Signed by {signer_common_name} ({signer_issuer})" when either is present; "Signed at {signed_at}" when present |
| `Valid` | Intact: signer not trusted | the same lines |
| `Invalid` | Does not verify | "Codes:" and each code of `codes`, in stored order; the signer lines when present |
| `none` | No Content Credentials | with a `remote_manifest_url`: "Refers to Content Credentials elsewhere (not checked): {remote_manifest_url}" |
| `error` | Could not be checked | "Reason: " and the reason in words: `interrupted` → "the check did not finish", `unreadable` → "the file could not be read", `exception` → "the verifier failed", `requirements` → "this server's PHP is older than 8.3" |
| no row | Not checked | — |
| unreadable row | Result unreadable | — |

A row is *unreadable* when its `state` is none of the five above, or its
`codes` is not a JSON list of strings, or its `reason` (for `error`) is
none of the four above. Every block that has them ends with "Checked
{checked_at} UTC with c2pa-verifier {verifier}". The block's heading is
"Content Credentials", as are the column header and the filter's label.

Every value in `{}` from a row passes through one function before output:
C0 and C1 control characters and the Unicode direction controls (U+200E,
U+200F, U+202A–U+202E, U+2066–U+2069) are each replaced by U+FFFD, and the
result is escaped with the view's `escapeHtml`. The module's own strings
pass through `translate` and then `escapeHtml`. Nothing from a row is
output unescaped, and no URL from a row becomes an `href`.

- **AC1 — the column: one headline per state**
  - Given media whose rows are `Valid`, `Invalid`, `none`, `error`, a
    row the test sets to `Trusted`, and a media item without a row
  - When an administrator without own column settings opens
    `/admin/media`
  - Then the browse has the column "Content Credentials", and each media
    item's cell shows its headline from the table and no other headline

- **AC2 — the media page: a signed file**
  - Given `fixture-signed.jpg` uploaded with the module active
  - When an administrator opens its media page
  - Then the sidebar holds the block with "Intact: signer not trusted",
    "Signed by C2PA Signer (C2PA Test Signing Cert)" and "Checked … UTC with
    c2pa-verifier 0.10.0" (the installed `Verifier::VERSION`); the Details
    panel (`/admin/media/{id}/show-details`) holds the same block

- **AC3 — an invalid file: its codes**
  - Given the altered JPEG of SPEC-002 AC4, uploaded
  - When its media page is opened
  - Then the block shows "Does not verify" and the codes
    `signingCredential.untrusted` and `assertion.dataHash.mismatch`, in
    that order

- **AC4 — a manifest by URL: text, never a link**
  - Given a `none` row whose `remote_manifest_url` the test sets to
    `https://example.com/manifest.c2pa`
  - When its media page is opened
  - Then the URL appears as escaped text after "Refers to Content
    Credentials elsewhere (not checked):", and the block contains no `<a`
    and no `href`

- **AC5 — an error in words**
  - Given `error` rows with each of the four reasons
  - When their blocks are rendered
  - Then each shows "Could not be checked" and its reason in words

- **AC6 — no row, and an unreadable row, show no verdict** *(error path)*
  - Given a media item without a row, and rows with `state` `Maybe`,
    `codes` `not json`, `codes` `[1]`, and `state` `error` with `reason`
    `whatever`
  - When the column and the block are rendered
  - Then the first shows "Not checked" and the others "Result
    unreadable", and none shows one of the five state headlines

- **AC7 — hostile text is escaped** *(error path)*
  - Given a `Valid` row whose signer columns and `signed_at`, an
    `Invalid` row whose codes, and a `none` row whose URL each hold
    `<script>alert(1)</script>`, `"><img src=x onerror=alert(1)>` and `'`
  - When the column, the block on the media page and the Details panel
    are rendered
  - Then the page contains none of `<script>alert`, `<img src=x`,
    `onerror=` as markup, and the text appears escaped
    (`&lt;script&gt;alert(1)&lt;/script&gt;`)

- **AC8 — control and direction characters are made visible** *(error path)*
  - Given a signer name holding `\x00`, `\x1B`, `\u{85}` and `\u{202E}`
  - When the block is rendered
  - Then each of those characters is replaced by U+FFFD and none of them
    remains in the output

- **AC9 — the filter**
  - Given media with rows in several states
  - When an administrator opens `/admin/media?content_credentials_state=Valid`
    (and likewise for `Trusted`, `Invalid`, `none`, `error`)
  - Then the browse lists exactly the media whose row has that state, the
    active filters show "Content Credentials: {headline}", and the
    advanced search (`/admin/media/search`) offers the field with the five
    headlines

- **AC10 — a filter value that is not a state matches nothing** *(error path)*
  - Given `content_credentials_state` set to `Maybe`, to an empty string,
    and to an array
  - When the browse is opened
  - Then an empty string means no filter; `Maybe` and the array list no
    media and the active filters show "Content Credentials: (unknown
    value)"; the value itself is never echoed

- **AC11 — only the admin side**
  - Given the module active and a public site holding a checked media item
  - When the media is opened on the public site, and
    `/api/media?content_credentials_state=Valid` is requested without a
    key
  - Then the public page shows no "Content Credentials" block, and the
    API's result is the same as without the parameter

## References

- Specification: C2PA Technical Specification 2.4, §15 (status codes,
  shown verbatim). Omeka S 4.2.1 source: `view/omeka/admin/media/show.phtml`,
  `show-details.phtml`, `browse.phtml`, `view/common/advanced-search.phtml`;
  `Omeka\View\Helper\Trigger`, `Browse`, `SearchFilters`;
  `Omeka\ColumnType\ColumnTypeInterface`; `Omeka\Mvc\Status`.
- Oracle: the rows SPEC-002 stores for the fixtures in
  `tests/Fixtures/c2pa/` (their values from `vendor/bin/c2pa-verify`,
  provemark/c2pa-verifier v0.10.0), and rows the tests set directly in the
  database for states and text no fixture produces; measured on
  Omeka S 4.2.1, PHP 8.3, in the Docker environment.
- Reasoned: that `Omeka\Mvc\Status::isAdminRequest()` (it exists in
  4.2.1, next to `isApiRequest()` and `isSiteRequest()`) tells the admin
  interface from the site and the API; read, not yet measured, AC11
  measures it. The test environment has no site (measured: 0 rows in
  `site`); AC11's test creates one.

## API sketch

Illustrative only.

```php
namespace ContentCredentials\Display;

/** What a row says, in the module's words; no Omeka S. */
final class Presentation
{
    public static function of(?array $row): self;   // null: no row
    public function headline(): string;
    /** @return list<string> lines, the row's values made visible, not yet escaped */
    public function lines(): array;
}

final class Text { public static function visible(string $untrusted): string; } // U+FFFD for controls

namespace ContentCredentials\Omeka;

final class VerdictBlock { /* view.show.sidebar, view.details: renders through escapeHtml */ }
final class StateColumn implements \Omeka\ColumnType\ColumnTypeInterface { /* getSortBy(): null */ }
final class StateFilter { /* view.advanced_search, view.search.filters, api.search.query */ }
```

Deptrac gets a layer `Display` (no Omeka S, may use nothing but
`Check`'s constants) before its first file; `Omeka` may use it.

## Open questions

- **The filter outside the admin interface.** `api.search.query` fires
  for every caller: the admin browse, the public site, the REST API.
  Applying the filter everywhere would let anyone ask which public media
  verify. Recommendation: apply it only on admin requests
  (`Status::isAdminRequest()`), as AC11 states; an API filter for
  integrations can come with its own spec. *Blocker for approval.*
  *Status 2026-11-04:* answered, admin requests only, the recommendation
  approved by Maurice van Loon with the spec.
- **The column for administrators with their own columns.** They do not
  get it until they add it (step 13). Recommendation: accept, it is how
  Omeka's own optional columns behave; say so in the README.
  *Non-blocker.*
  *Status 2026-11-04:* answered, accepted and to be said in the README,
  approved by Maurice van Loon with the spec.

## Amendments

- **Amendment 1 (2026-11-04, approved by Maurice van Loon with SPEC-004):**
  the block gains SPEC-004 AC7's trust line after the "Checked …" line,
  and a row whose `trust` is not NULL and not one of
  `c2pa-YYYY-MM-DD+digicert`, `c2pa-YYYY-MM-DD`, `custom`, `none` is
  unreadable. Was: no trust line. Tested under SPEC-004 AC7.
- **Amendment 2 (2026-11-05, approved by Maurice van Loon with SPEC-006):**
  the block gains, as its last line, SPEC-006 AC6's evidence line when
  the media item has at least one evidence row: a link, the block's
  first. AC4 still holds, since a `none` check keeps no evidence
  (SPEC-006 AC3). Was: no evidence line. Tested under SPEC-006 AC6.
- **Amendment 3 (2026-11-05, approved by Maurice van Loon with SPEC-007):**
  on a `Trusted` or `Valid` row with an `ai_statement`, the block gains the
  label "AI-generated (signed)" or "AI-edited (signed)" as its first line
  under the headline, and the column as a second line under the headline.
  The advanced search gains a second filter field, and the active filters
  its label. A row whose `ai_statement` is not NULL, `generated` or `edited`
  is unreadable. Was: the headline alone, one filter. The "AI label (M6)"
  line under Out of scope is met by SPEC-007. Tested under SPEC-007.

## Traceability

Filled when status becomes `implemented`. Every acceptance criterion maps to at
least one test; every source file maps back to this spec. All tests carry
`->group('SPEC-003')`; the record is `notes/step-14-show-the-result.md`.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1 | `tests/Unit/ShowTheResultTest.php` :: AC1; `tests/Integration/ShowTheResultTest.php` :: AC1 | `Display\Presentation::of()`, `headline()`; `Omeka\StateColumn`, `StateColumnFactory`; `config/module.config.php` (`column_types`, `column_defaults`) |
| AC2 | `tests/Unit/ShowTheResultTest.php` :: AC2; `tests/Integration/ShowTheResultTest.php` :: AC2 | `Omeka\AdminDisplay::sidebar()`, `details()`; `Omeka\VerdictHtml::block()`; `Omeka\VerdictReader`; `Omeka\ViewText`; `Module::attachListeners()` |
| AC3 | `tests/Unit/ShowTheResultTest.php` :: AC3; `tests/Integration/ShowTheResultTest.php` :: AC3 | `Display\Presentation::of()`, `codes()` |
| AC4 | `tests/Unit/ShowTheResultTest.php` :: AC4; `tests/Integration/ShowTheResultTest.php` :: AC4 | `Display\Presentation::of()`; `Omeka\VerdictHtml::block()` |
| AC5 | `tests/Unit/ShowTheResultTest.php` :: AC5; `tests/Integration/ShowTheResultTest.php` :: AC5 | `Display\Presentation::REASONS` |
| AC6 | `tests/Unit/ShowTheResultTest.php` :: AC6; `tests/Integration/ShowTheResultTest.php` :: AC6 | `Display\Presentation::of()`; `Omeka\VerdictReader::row()`, `rowFor()` |
| AC7 | `tests/Integration/ShowTheResultTest.php` :: AC7 | `Omeka\ViewText::escape()`, `line()`; `Omeka\VerdictHtml`; `Omeka\StateColumn::renderHeader()` |
| AC8 | `tests/Unit/ShowTheResultTest.php` :: AC8 (three tests); `tests/Integration/ShowTheResultTest.php` :: AC8 | `Display\Text::visible()` |
| AC9 | `tests/Unit/ShowTheResultTest.php` :: AC10; `tests/Integration/ShowTheResultTest.php` :: AC9 | `Omeka\AdminDisplay::advancedSearch()`, `searchFilters()`, `searchQuery()`; `view/content-credentials/advanced-search.phtml`; `Display\FilterValue` |
| AC10 | `tests/Unit/ShowTheResultTest.php` :: AC10; `tests/Integration/ShowTheResultTest.php` :: AC10 | `Display\FilterValue::parse()`; `Omeka\AdminDisplay::searchFilters()`, `searchQuery()` |
| AC11 | `tests/Integration/ShowTheResultTest.php` :: AC11 | `Omeka\AdminDisplay::searchQuery()` (`Status::isAdminRequest()`); listeners on `Omeka\Controller\Admin\Media` only |
