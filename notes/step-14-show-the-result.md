# Step 14 (M2.3): SPEC-003, the verdict in the admin interface

2026-11-04. SPEC-003 (approved 2026-11-04) is implemented: the admin
media page and its Details panel show a block with the verdict, the
media browse has a column "Content Credentials", and the browse can be
filtered by stored state. Measured on Omeka S 4.2.1, PHP 8.3.36, in the
Docker environment of `tools/env/`.

## What was built

- `src/Display/` (new Deptrac layer `Display`, may use `Check` only, no
  Omeka S): `Presentation` (a row to a headline and lines, in the
  module's English source strings), `Text::visible()` (C0, DEL, C1 and the
  Unicode direction controls to U+FFFD; bytes that are not UTF-8 are
  scrubbed first), `FilterValue::parse()` (a state, no filter, or `false`
  for a value that must match nothing).
- `src/Omeka/`: `VerdictReader` (the row through the entity
  `StoredVerdict`; a row that cannot be read, or a representation id that
  is not a positive integer, comes back without a state and shows as
  "Result unreadable"), `ViewText` (the view's `translate` and
  `escapeHtml`, typed), `VerdictHtml` (the block in Omeka's own
  `meta-group` markup, and the cell), `StateColumn` and its factory,
  `AdminDisplay` (the five listeners), and
  `view/content-credentials/advanced-search.phtml`.
- `config/module.config.php`: `column_types`, `column_defaults.admin.media`,
  `view_manager.template_path_stack`. `Module.php` attaches the listeners
  lazily, as it does the upload listener.

## Escaping, as read in Omeka S 4.2.1 and relied on

- `Browse::renderHeaderRow()` escapes a column's header itself, so
  `StateColumn::renderHeader()` returns plain text.
  `Browse::renderContentRow()` outputs a cell as it is, so
  `renderContent()` returns markup in which everything is escaped.
- `view/common/search-filters.phtml` escapes filter labels and values, so
  the filter label and value are passed as plain text.
- The block and the advanced-search partial escape everything they write
  through the view's `escapeHtml`. No value from a row becomes an
  attribute or an `href`.

## Tests, red first

- Unit (`tests/Unit/ShowTheResultTest.php`), before `src/Display/` existed:
  `Tests: 41 failed (0 assertions)`, every one an `Error`:
  `Class "ContentCredentials\Display\Presentation" not found` (20),
  `…\Text" not found` (10), `…\FilterValue" not found` (11). After:
  41 passed.
- Integration (`tests/Integration/ShowTheResultTest.php`), against the
  module of M1: `Tests: 10 failed, 1 passed (32 assertions)`. AC2 to AC8
  failed with `No Content Credentials block on the page.`, AC1 and AC6
  with `Failed asserting that null is identical to 'Not checked'.`, AC9
  and AC10 with `Failed asserting that two arrays are identical.`
- AC11 passed before any code: it asserts that nothing changes outside
  the admin interface, which holds for a module that shows nothing. To
  see it fail, the `isAdminRequest()` guard in
  `AdminDisplay::searchQuery()` was removed: `Tests: 1 failed (6
  assertions)`, `Failed asserting that two strings are identical.` (the
  anonymous REST API result changed with the parameter). With the guard
  back: 1 passed. This also measures what SPEC-003 listed as reasoned:
  `Status::isAdminRequest()` is false for an anonymous `/api/media`
  request.
- `composer check`: green, with 88 unit tests and 31 integration tests.
  The new files lint on PHP 8.2.35 (the 8.2 container); CI lints them on
  8.1 and 8.2.

## Defects found on the way

- **Pest's `toContain()` takes several needles, not a message.** I had
  written `toContain('…', $path)` as if the second argument were a
  failure message, as it is for `toBe()`. It demanded that the block also
  contain the path and the reason, so AC2 and AC5 failed against a
  correct block. Removed from every `toContain` in the file.
- **Omeka echoes the query, escaped, in its own "Advanced search" link.**
  AC10's first form checked that `Maybe` appears nowhere on the page, and
  found it in `href="…content_credentials_state&#x3D;Maybe…"`, a link
  Omeka builds from the whole query. That is Omeka's output, escaped
  as an attribute, and not the module's. The test now checks the active
  filters, where the module writes; the criterion's "the value itself is
  never echoed" is read as "by the module".
- PHPStan level max found 17 errors in the first version, among them a
  representation id typed `mixed` (now `VerdictReader::rowFor()`, which
  refuses anything but a positive integer) and a view plugin that might
  not be callable (now checked). No ignore was added.
  `Presentation::of()` accepts any array, since it is a parser for an
  untrusted row.

## Not measured

- The browse's cost: the column reads one row per media item through
  `EntityManager::find()`, so a page of 25 media costs 25 small primary
  key queries. Reasoned acceptable, not timed.
- Whether the column appears in the user's column picker
  (`renderDataForm()` returns nothing), and how it looks there.
- The look of the block and the column in a browser; the tests read the
  HTML.
