# SPEC-007: The AI label

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

An archive wants to know whether a file says it was made or edited with
generative AI. A C2PA manifest can say so: an action in its actions assertion
carries an IPTC digital source type. That statement is only worth showing when
the file verifies. On an `Invalid` file it is a claim nobody can stand behind,
and showing it would be a verdict the verifier did not give.

This spec is M6 of `docs/milestones.md`. It rests on
`notes/step-26-ai-statement.md` (verifier 0.10.0, measured outside Omeka):

- The statement is in `VerificationReport::toArray()`:
  `manifests[<label>].assertions[]`, labelled `c2pa.actions` or
  `c2pa.actions.v2`, `data.actions[].digitalSourceType`.
- A tampered image keeps its statement in `toArray()` while its verdict is
  `Invalid`, so the verdict must gate the label.
- Reading only the active manifest misses two shapes that are `Valid`: a
  statement further down a `parentOf` chain, and an edit with AI
  (`compositeWithTrainedAlgorithmicMedia`).
- An ingredient's recorded results are what its signer wrote, not what the
  verifier found. Only the verifier's delta says the latter.
- `evidenceToJson()` holds no assertions, so the statement cannot be read back
  from the evidence (SPEC-006). It needs a column of its own.

The milestone's row names only "AI-generated (signed)". This spec widens it
with "AI-edited (signed)"; see Open questions, 1.

## Scope

**In scope**

- `ContentCredentials\Check\AiStatement`, which reads a `toArray()` array and
  answers `generated`, `edited` or null, by the rule below.
- A column `ai_statement VARCHAR(16) DEFAULT NULL` in
  `content_credentials_verdict`, after `trust`. It is set only when the state is
  `Trusted` or `Valid`, and NULL otherwise.
- The labels "AI-generated (signed)" and "AI-edited (signed)" in the block
  (SPEC-003) and in the browse column, shown only when the state is `Trusted` or
  `Valid`.
- A second browse filter, `content_credentials_ai`, with the values `generated`
  and `edited`, on admin requests only, as SPEC-003's filter.
- Two fixtures with their licences: Amazon Titan and parent-chain.
- A paragraph in the README on what the label does and does not say.

**The rule.** Only these exact URIs count:

- `http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia`
  (*trained*);
- `http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia`
  (*composite*).

The walk starts at the `active_manifest` and follows the `active_manifest` of
each ingredient. An ingredient is followed only when both of these are present,
and each is empty or holds only `signingCredential.untrusted`:

- its recorded `validation_results.activeManifest.failure`;
- the failure codes of the verifier's delta for it: the entry of
  `validation_results.ingredientDeltas` whose `ingredientAssertionURI` is
  `self#jumbf=/c2pa/<manifest label>/c2pa.assertions/<ingredient label>`.

There are two walks, each reading a manifest at most once:

1. **Generated:** *trained* in the active manifest or in a manifest reached
   through `parentOf` ingredients only.
2. **Edited:** *composite* in any reached manifest, or *trained* in a manifest
   reached through any relationship.

Generated wins over edited. So a manifest reached first as a component and also
as a parent counts as generated.

**Out of scope** (each needs its own spec before it may be built)

- Other source types, among them `algorithmicMedia`, `compositeSynthetic` and
  `composite`; see Open questions, 5.
- A statement in an ingredient assertion's own `digitalSourceType` field, without
  a manifest behind it.
- The statement in the evidence table or document. The document stays
  `evidenceToJson()`, unchanged (SPEC-006).
- Sorting by the label; the label on the public site or in the REST API.
- Any AI detection that is not a signed statement in the file.
- An `upgrade()` step: the module is unreleased at 0.1.0. A development
  installation is reinstalled, as with SPEC-004's and SPEC-006's table changes.

**Amendments to other specs, approved with this spec**

- **SPEC-002 amendment 2:** the table gains `ai_statement` (this spec's rule and
  gate), so the `CREATE TABLE` statement changes, with no `upgrade()`. Its
  criteria are unchanged. Was: no such column.
- **SPEC-003 amendment 3:**
  - the block gains the label as the first line under the headline, and the
    column as a second line under the headline;
  - the advanced search gains the second filter field, and the active filters its
    label;
  - a row whose `ai_statement` is not NULL, `generated` or `edited` is unreadable.

  Was: the headline alone, one filter. SPEC-003's "AI label (M6)" out-of-scope
  line is met by this spec.

SPEC-001, SPEC-004, SPEC-005 and SPEC-006 keep their text. SPEC-005's re-check
writes through the same store, so it sets the column too (AC8).

## Behavior

Acceptance criteria as Given/When/Then. Each is individually testable and will be
covered by a Pest test tagged `->group('SPEC-007')`, in `tests/Unit` when it
needs no Omeka S and in `tests/Integration` (the Docker environment of
`tools/env/`) when it does. At least one criterion MUST be an error /
malformed-input path. This project fails closed: it never shows a verdict
the verifier did not give, a failure is stored as state `error` with a
reason, and the upload always proceeds. Everything read from a file is
untrusted text; a criterion that displays it says how it is escaped (the
Laminas `escapeHtml` view helper, or which other).

The labels and filter names below are the module's constants. No text from a
file or a row reaches them: an unknown value is never echoed.

AC2 to AC6 are tested on arrays in the shape of `toArray()`, built in the test,
and AC1, AC7 and AC8 on real files, with the module's default settings unless
they say otherwise.

- **AC1 — the real files**
  - Given these fixtures, uploaded:

    | fixture | state, as `vendor/bin/c2pa-verify` says | `ai_statement` | label |
    |---|---|---|---|
    | `amazon-20240925-titan-g1.png` | `Valid` | `generated` | AI-generated (signed) |
    | `c2pa-verifier-ai-parent-chain.png` | `Valid` | `generated` | AI-generated (signed) |
    | `c2pa-rs-ocsp.jpg` | `Valid` | `edited` | AI-edited (signed) |
    | `fixture-signed.jpg` (`algorithmicMedia`) | `Valid` | NULL | none |
    | `google-20250919-pixel10-npld-picnic-table.jpg` (`computationalCapture`) | `Trusted` | NULL | none |
    | `fixture-unsigned.png` | `none` | NULL | none |

  - When the browse and the media page are opened
  - Then each row, and what the column and the block show, is as in the table,
    and no page holds both labels

- **AC2 — generated, in the active manifest and down a parent chain**
  - Given a report with *trained* on `c2pa.created` in the active manifest; one
    with *trained* on another action; and one where the active manifest's
    `parentOf` ingredient leads to a manifest with a `parentOf` ingredient that
    leads to *trained*, every gate open
  - When `AiStatement` reads them
  - Then each answers `generated`

- **AC3 — edited**
  - Given a report with *composite* in the active manifest; one with *composite*
    in a `parentOf` manifest; one with *trained* behind a `componentOf`
    ingredient; and one with *trained* behind an `inputTo` ingredient
  - When `AiStatement` reads them
  - Then each answers `edited`

- **AC4 — generated wins, and needs the parent line**
  - Given a report with *composite* in the active manifest and *trained* in a
    `parentOf` manifest; one where one manifest with *trained* is reached first
    through `componentOf` and also through `parentOf`; and one with *trained*
    reached through `componentOf` and then `parentOf`
  - When `AiStatement` reads them
  - Then they answer `generated`, `generated` and `edited`

- **AC5 — an ingredient that is not followed** *(error path)*
  - Given the parent chain of AC2, with its first ingredient changed in turn:
    - a recorded failure `assertion.dataHash.mismatch`;
    - recorded `signingCredential.untrusted` and `claimSignature.mismatch`;
    - no `validation_results` (the shape of `c2pasign-logo.png`,
      `notes/step-26-ai-statement.md`);
    - recorded empty, with the delta holding `claimSignature.mismatch`;
    - recorded empty, with no delta for it
  - When `AiStatement` reads them
  - Then each answers null, and nothing below that ingredient is read
  - And with only `signingCredential.untrusted`, recorded and in the delta, the
    chain is followed and the answer is `generated`

- **AC6 — source types that do not count, and malformed reports** *(error path)*
  - Given reports whose actions carry `algorithmicMedia`, `digitalCapture`,
    `compositeSynthetic`, *trained* with `https://`, with a leading space, or in
    another case, and a `digitalSourceType` that is an integer, an array or null
  - And reports where: there is no `active_manifest`, or it is not a string or an
    integer; `manifests`, `assertions`, `data`, `actions`, `ingredients`, an
    ingredient or `validation_results` has the wrong type; an ingredient names a
    label not in `manifests`; `relationship` is missing or an integer; two
    manifests name each other (a cycle); a manifest names itself
  - When `AiStatement` reads them
  - Then each answers null where nothing else counts, with no exception and no
    warning, and the cycle ends
  - And a report whose manifest labels are digits only, which PHP makes integer
    keys, answers as the same report with other labels does

- **AC7 — a file that does not verify has no label** *(error path)*
  - Given `amazon-20240925-titan-g1.png` checked with the DigiCert option off;
    `c2pa-verifier-ai-parent-chain.png` and `c2pa-rs-ocsp.jpg` with one bit of
    image data changed, made by the test
  - When they are uploaded
  - Then each state is `Invalid` with the codes `vendor/bin/c2pa-verify` gives
    for the same file and settings, `ai_statement` is NULL, and "AI-generated"
    and "AI-edited" appear in neither the column nor the block

- **AC8 — a re-check sets the column, either way**
  - Given a media item of `fixture-signed.jpg` whose row was set by hand to
    `generated`, and one of Titan whose `ai_statement` was set to NULL
  - When "Check all media" runs (SPEC-005)
  - Then the first is NULL and the second `generated`
  - And each evidence document is still `evidenceToJson()`, with no key of this
    module's (SPEC-006)

- **AC9 — the label follows the state** *(error path)*
  - Given rows set by hand with `ai_statement` `generated` and `edited` and each
    state `Trusted`, `Valid`, `Invalid`, `none`, `error`
  - When the column and the block are rendered
  - Then only `Trusted` and `Valid` show the label, the block with the label as
    its first line under the headline
  - And given a row whose `ai_statement` is `true`, an empty string or
    `<script>alert(1)</script>`, each state shows "Result unreadable", and the
    value appears nowhere in the page, escaped or not

- **AC10 — the filter**
  - Given media with rows of both labels and without
  - When an administrator opens `/admin/media?content_credentials_ai=generated`,
    and likewise `edited`
  - Then the browse lists exactly the media that show that label; the active
    filters show "Content Credentials: AI-generated (signed)" or "… AI-edited
    (signed)"; and the advanced search offers the field with both labels
  - And with `content_credentials_state=Valid` as well, it lists only those that
    match both

- **AC11 — the filter follows the state too** *(error path)*
  - Given a row set by hand to `Invalid` with `generated`
  - When the browse is filtered on `generated`
  - Then that media item is not listed

- **AC12 — a filter value that is not a label** *(error path)*
  - Given `content_credentials_ai` set to `Maybe`, to an empty string and to an
    array
  - When the browse is opened
  - Then an empty string means no filter; `Maybe` and the array list no media and
    the active filters show "Content Credentials: (unknown value)"; the value
    itself is never echoed

- **AC13 — only the admin side**
  - Given the parameter `content_credentials_ai=generated`
  - When the public site's media browse and the REST API's `/api/media` are asked
  - Then the result is the same as without it

- **AC14 — only the public report**
  - Given the module's source
  - Then none of it reads the verifier's `->store` (`@internal`); the statement
    comes from `toArray()`

- **AC15 — an instance label reads as its base label** *(added by amendment 3)*
  - Given a report whose actions assertion is labelled `c2pa.actions__1` or
    `c2pa.actions.v2__1`, with *trained* or *composite*, in the active manifest or
    behind an ingredient that is followed
  - When `AiStatement` reads it
  - Then it answers as the same report without `__1` does
  - And given labels `c2pa.actions__`, `c2pa.actions__1a`, `c2pa.actions.v2__1__2`,
    `c2pa.actions__1` followed by a newline, `c2pa.actions.v3__1`, `C2PA.ACTIONS__1`,
    or a label that is not a string, it answers null, with no exception and no
    warning

## References

- **Specification:**
  - C2PA Technical Specification 2.4: ingredients (§11.1, the `relationship`
    values), the actions assertion (`digitalSourceType`), ingredient validation
    (§15.11). Read through Tracefern's `specs/SPEC-027-ai-in-the-history.md`, not
    opened again here.
  - IPTC Digital Source Type vocabulary, `https://cv.iptc.org/newscodes/digitalsourcetype/`,
    read 2026-11-05 through a summarising fetch; to be checked word for word
    before the README quotes it:
    - `trainedAlgorithmicMedia`, "Created using Generative AI";
    - `compositeWithTrainedAlgorithmicMedia`, "Edited using Generative AI";
    - `algorithmicMedia`, "Pure algorithmic media", not based on sampled training
      data;
    - `compositeSynthetic`, "Composite including generative AI elements".
- **Oracle:** `provemark/c2pa-verifier` v0.10.0, `vendor/bin/c2pa-verify` with
  `--settings` for the module's settings, on the fixtures above.
- **Measured** (`notes/step-26-ai-statement.md`, `tools/probes/AiProbe/`,
  PHP 8.5.12 and 8.3.36, outside Omeka; not yet inside Omeka):
  - the verdicts, the source types and how long each verdict holds, for each file
    in AC1 and AC7;
  - Titan and `c2pa-rs-ocsp.jpg` hold to at least 2045, parent-chain until
    2036-09-25, `fixture-signed` until 2030-08-26;
  - the delta's URI matches for several ingredients in one manifest
    (`c2pa.ingredient.v3__1`): 125 of 132 ingredients naming a manifest, in 668
    files of the verifier's fixtures. The 7 others include manifests not reached
    from the active one; no delta means not followed, which fails closed.
- **Reasoned:**
  - that *trained* anywhere on the `parentOf` line makes the image AI-generated,
    however it was processed later, and that `componentOf` and `inputTo` make it
    AI-edited (from Tracefern's SPEC-027);
  - that `signingCredential.untrusted` is the only failure on an ingredient that
    a `Valid` active manifest would also tolerate;
  - that a second alias on the verdict table in `api.search.query` combines with
    SPEC-003's filter: to be shown by AC10.

## API sketch

Illustrative only — not binding implementation. Parseable on PHP 8.1, as
`Verdict` is.

```php
namespace ContentCredentials\Check;

final class AiStatement
{
    public const GENERATED = 'generated';
    public const EDITED = 'edited';

    /** @param array<mixed> $report toArray()'s shape, read as untrusted */
    public static function read(array $report): ?string;
}

// Verdict::fromReport(): $aiStatement = in_array($state, ['Trusted', 'Valid'], true)
//     ? AiStatement::read($report->toArray()) : null;

namespace ContentCredentials\Display;

final class Presentation
{
    public function aiLabel(): ?string; // null, 'AI-generated (signed)' or 'AI-edited (signed)'
}

final class AiFilterValue
{
    public const PARAMETER = 'content_credentials_ai';

    public static function parse(mixed $value): string|false|null;
}
```

## Open questions

1. **Which rule.** Only the active manifest, as the milestone's row says, or also
   the chain and "AI-edited (signed)". Recommendation: the chain and both labels,
   as written here. The narrow rule is simpler, but it shows nothing on an AI image
   that was processed afterwards, and an archive relying on the label is misled
   there. *Blocker.*
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
2. **The filter.** Recommendation: yes, for both labels, as written. The filter
   machinery exists, and "show me everything that says it is AI" is what an archive
   would ask. Tracefern filters on "generated" only. *Blocker.*
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
3. **The gate on ingredients.** `c2pasign-logo.png` is `Valid`, its chain says
   *trained*, and its ingredients record no results, so it gets no label (AC5). The
   verifier's delta alone would let it through, since that is what the verifier
   found itself. Recommendation: keep both conditions, as Tracefern does: the
   difference is a missing label, and the other way round is a label resting on less
   than was measured on any fixture. Say the limit in the README. *Blocker.*
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
4. **What the column holds on a file that does not verify.** Tracefern stores the
   flag for every state and gates at display. Recommendation: NULL unless `Trusted`
   or `Valid`, and gate at display and in the filter as well. The table then never
   holds a claim nobody can stand behind, and a re-check recomputes it anyway. The
   price: "`Invalid` and claims AI" cannot be asked of the table later without a
   re-check. *Blocker.*
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
5. **`compositeSynthetic`.** IPTC calls it "Composite including generative AI
   elements", yet no fixture measured carries it (step 26). Recommendation: leave it
   out until a file that verifies shows it: no rule without a measurement. An
   archive then sees no label on such a file. *Non-blocker.*
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
6. **The fixtures.** Recommendation: copy Titan (1.8 MB, Apache-2.0, adds a licence
   file) and parent-chain (60 KB, the verifier's MIT), not OpenAI, which turns
   `Invalid` on 2027-04-23. `Trusted` with a label is then tested on a row set by
   hand (AC9), since no measured file holds that for long. *Blocker.*
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.
7. **The wording.** "AI-generated (signed)" and "AI-edited (signed)", as in
   Tracefern. "(signed)" says it is the file's signed statement, not the module's
   finding. Recommendation: keep. *Blocker.*
   *Status 2026-11-05:* answered, the recommendation approved by Maurice
   van Loon with the spec.

## Amendments

Both approved by Maurice van Loon on 2026-11-05, while building. Each one is
something the approved text asked for that the running system refuses. The
criteria above keep their original words; these amendments say how they now read.

1. **AC9, the hostile value.** `<script>alert(1)</script>` is 25 characters and
   `ai_statement` is `VARCHAR(16)`: the test's `UPDATE` exits with an error, so such
   a row cannot exist. AC9 uses `<i>spec007</i>` instead. And "appears nowhere in
   the page" cannot hold for `true`, a word Omeka's own page contains: `true` and the
   empty string are checked in the block and the column, the hostile value in the
   whole page as well. `Presentation` is also tested with the original 25
   characters, as a unit, for every state.
2. **AC13, the public page.** `/s/{slug}/media` answers 404 in Omeka S 4.2.1: there
   is no public media browse. AC13 asks for the public media page
   `/s/{slug}/media/{id}` of a labelled media item instead, which must not show the
   label, and for the REST API as written.

Approved by Maurice van Loon on 2026-11-07:

3. **AC15, instance labels.** From finding 3 of the code review
   (`notes/step-35-the-code-review.md`). The rule compares an assertion's label
   after removing a final `__n`, as the verifier does, and then exactly. Was: the
   exact label only. Known limit: tested on constructed reports, as AC2 to AC6 are;
   no real file with such a label was found in 802 files.

## Traceability

Every acceptance criterion maps to at least one test; every source file maps
back to this spec. All tests carry `->group('SPEC-007')`. The record is
`notes/step-27-the-ai-label.md`.

| Acceptance criterion | Test (file :: name) | Source (file/symbol) |
|----------------------|---------------------|----------------------|
| AC1 | tests/Integration/AiLabelTest.php :: AC1: the real files | src/Check/AiStatement.php :: read(); src/Check/Verdict.php :: fromReport(); src/Omeka/Schema.php; src/Omeka/DbalVerdictStore.php; src/Entity/StoredVerdict.php; src/Omeka/VerdictReader.php; src/Omeka/VerdictHtml.php |
| AC2 | tests/Unit/AiStatementTest.php :: AC2: generated, in the active manifest and down a parent chain | src/Check/AiStatement.php :: read(), sourceTypes(), typesIn() |
| AC3 | tests/Unit/AiStatementTest.php :: AC3: edited; AC3: a relationship the rule does not name counts as any other than a parent | src/Check/AiStatement.php :: read() |
| AC4 | tests/Unit/AiStatementTest.php :: AC4: generated wins, and needs the parent line | src/Check/AiStatement.php :: read() (two walks) |
| AC5 | tests/Unit/AiStatementTest.php :: AC5: an ingredient that is not followed; AC5: a branch that is not followed leaves another branch open; AC5: an unknown signer on an ingredient is followed | src/Check/AiStatement.php :: followed(), tolerable() |
| AC6 | tests/Unit/AiStatementTest.php :: AC6: source types that do not count; AC6: malformed reports read as nothing, without a warning; AC6: a relationship that is missing or not a string is not followed; AC6: a cycle ends; AC6: labels of digits only read as any other label | src/Check/AiStatement.php :: sourceTypes(), listAt(), label() |
| AC7 | tests/Integration/AiLabelTest.php :: AC7: a file that does not verify has no label | src/Check/Verdict.php :: fromReport() (the gate at writing) |
| AC8 | tests/Integration/AiLabelTest.php :: AC8: a re-check sets the column, either way, and leaves the evidence the verifier's | src/Omeka/DbalVerdictStore.php :: upsert() |
| AC9 | tests/Integration/AiLabelTest.php :: AC9: the label follows the state on every page; AC9: a value the module never writes is unreadable and never echoed; tests/Unit/AiStatementTest.php :: AC9: the label follows the state; AC9: a value the module never writes makes the row unreadable, whatever its state; AC9: a row without the key has no label | src/Display/Presentation.php :: of(), aiLabel(), AI_LABELS; src/Omeka/VerdictHtml.php :: block(), cell() |
| AC10 | tests/Integration/AiLabelTest.php :: AC10: the filter | src/Omeka/AdminDisplay.php :: searchQuery(), searchFilters(); view/content-credentials/advanced-search.phtml |
| AC11 | tests/Integration/AiLabelTest.php :: AC11: the filter follows the state too | src/Omeka/AdminDisplay.php :: searchQuery() (the gate in the filter) |
| AC12 | tests/Integration/AiLabelTest.php :: AC12: a filter value that is not a label matches nothing; tests/Unit/AiStatementTest.php :: AC12: what a filter value means | src/Display/AiFilterValue.php :: parse() |
| AC13 | tests/Integration/AiLabelTest.php :: AC13: only the admin side | src/Omeka/AdminDisplay.php :: searchQuery() (`isAdminRequest()`) |
| AC14 | tests/Unit/AiStatementTest.php :: AC14: the module reads only the public report | all of src/; src/Check/AiStatement.php reads `toArray()` only |
| AC15 | tests/Unit/AiStatementTest.php :: AC15: an instance label reads as its base label; AC15: an instance next to the base label is read too; AC15: a label that only looks like an instance is not read | src/Check/AiStatement.php :: typesIn(), isActions() |
