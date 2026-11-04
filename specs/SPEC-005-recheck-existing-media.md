# SPEC-005: Check existing media again

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

SPEC-002 checks a media item once, when it is created. Media that came
in before the module was installed have no verdict, and so do media
whose check never ran. A verdict also goes stale: certificates expire,
and the trust settings of SPEC-004 change. This is M4 of
`docs/milestones.md`. A global administrator starts a run from the admin
interface. The run checks existing media in the background, can be
stopped, and gives the same result when it is run again.

It rests on:
- `notes/step-18-jobs.md`, measured on Omeka S 4.2.1:
  - `Omeka\Job\Dispatcher` with its default strategy (`PhpCli`) runs a
    job as its own PHP process;
  - a job that throws, or has a fatal error, ends `error`, and Omeka
    writes the exception's message, paths and stack trace into the job
    log, which site administrators can read;
  - Stop on the job page only sets `stopping`, and the job must ask
    `shouldStop()`;
  - a job that cannot start (a wrong PHP path) is `error` at once, while
    the page that started it reports success;
  - the configure page is for global administrators only and is checked
    for CSRF, and `handleConfigForm()` can dispatch a job;
- `notes/step-20-recheck-at-scale.md`, measured with the module's own
  check as a job:
  - 3 097 media in 14.9 s, with memory flat at 18 MB;
  - building the trust settings once per run instead of per item takes
    a third of the time;
  - a stopped run keeps every verdict it stored;
  - two runs at once, and an upload during a run, do no harm;
  - a re-check replaces the row (`INSERT … ON DUPLICATE KEY UPDATE`);
- SPEC-002: which media are checked (JPEG, PNG and WebP with an
  original), the verdict row, and the `error` reasons;
- SPEC-004: the trust settings and the trust source column;
- the Omeka S 4.2.1 source: `Omeka\Job\AbstractJob`, `Dispatcher`,
  `DispatchStrategy\PhpCli` and `Synchronous`, `Omeka\Entity\Job`,
  `Omeka\Controller\Admin\JobController`.

## Scope

**In scope**

- A job class, `ContentCredentials\Omeka\RecheckJob`, dispatched through
  Omeka's `Omeka\Job\Dispatcher` with Omeka's configured strategy. It
  checks media with the same `MediaCheck` and `Recorder` as an upload.
- A run takes either all checkable media or only those without a verdict
  (open question 1). It goes by ascending media id, reads the ids in
  batches, and builds the trust settings once, at its start.
- Each check replaces the media's row, as an upload does (open question
  2).
- A "Check existing media" section on the module's configure page (open
  question 3), with:
  - a start button for each kind of run;
  - the latest run's status, with its start and end and a link to Omeka's
    job page;
  - while a run is `starting`, `in_progress` or `stopping`, no start
    buttons.
- Progress lines and a summary in the job log, in the module's own words
  and numbers only (open question 4).
- Stopping through Omeka's own Stop button on the job page.

**Out of scope** (each needs its own spec before it may be built)

- Keeping the earlier verdict, or a history of verdicts (M5, evidence).
- Runs on a schedule, or started from the command line or the API.
- Choosing media by item set, site, date or verifier version.
- Carrying on a stopped run from where it stopped (open question 5).
- A page of the module's own, outside the configure page.
- Formats other than JPEG, PNG and WebP; derivatives.

## Behavior

Acceptance criteria as Given/When/Then, tagged `->group('SPEC-005')`.
They run in `tests/Integration` against the Docker environment (Omeka S
4.2.1, PHP 8.3, MariaDB 11.4), with the job dispatched through Omeka's
default strategy (`PhpCli`). The exceptions are AC13 and the throwing
case of AC7: they need a stand-in, which cannot reach a `PhpCli`
process, so they run in `tests/Unit` against the job's per-item step. A *checkable* media item is a JPEG, PNG
or WebP with an original (SPEC-002). Text that the page shows is
escaped with `escapeHtml`. That covers the job's status, which Omeka
writes, and nothing read from a file is shown.

- **AC1 — a run checks every checkable media item**
  - Given checkable media with no row, with a row, and with a row whose
    state and `checked_at` the test has changed, and a GIF and a PDF
    media item (SPEC-002 AC6)
  - When a global administrator starts "Check all media" and the job ends
  - Then the job is `completed`; every checkable media item has a row
    equal to the one an upload of the same file gets now (SPEC-002 AC1,
    without `checked_at`), with a newer `checked_at`; the GIF and the PDF
    have no row

- **AC2 — only media without a verdict**
  - Given checkable media with a row and without one
  - When "Check media without a result" runs to its end
  - Then the media without a row have one, and the rows that were there
    are unchanged, `checked_at` included

- **AC3 — the trust settings of the moment the run starts**
  - Given the bundled lists with DigiCert off, and the fixture
    `c2pa-rs-ocsp.jpg` (`Invalid` without DigiCert, `Valid` with it,
    SPEC-004)
  - When a run starts
  - Then its row is `Invalid` with trust source `c2pa-2026-10-17`; after
    DigiCert is switched on and a second run has ended, it is `Valid`
    with `c2pa-2026-10-17+digicert`

- **AC4 — running it again changes nothing but the time**
  - Given a completed run
  - When the same run runs again
  - Then every row is the same except `checked_at`, and the number of
    rows is the same

- **AC5 — stopping a run** *(interruption)*
  - Given a run over enough media that it is still `in_progress` after it
    has stored some rows (the test sets the media)
  - When Stop is pressed on Omeka's job page
  - Then the job ends `stopped`, every row it stored is complete, rows it
    had not reached are as they were, and the configure page offers the
    start buttons again; a new run of "Check all media" then completes as
    in AC1

- **AC6 — an unreadable original: `error`, the run goes on** *(error
  path)*
  - Given a checkable media item whose original the test has removed from
    `files/original`, among readable ones
  - When a run ends
  - Then that row is `error` with reason `unreadable` (SPEC-002 AC8), the
    others are checked, and the job is `completed`

- **AC7 — nothing from a file or an exception in the job log** *(error
  path)*
  - Given the media of AC6 and custom trust settings that cannot be built
    (SPEC-004 AC6) in a real run; and, in `tests/Unit`, a check whose
    store throws an exception with a path in its message
  - When the run ends, and when the unit's step has run
  - Then the job log holds only the module's own lines (open question
    4): no path, no exception message or class, no stack trace and no
    text from a file or from the settings

- **AC8 — a run that cannot start is shown as failed** *(error path)*
  - Given `cli.phpcli_path` set to a path where no PHP is, in the test
    container's `local.config.php` (restored afterwards)
  - When a run is started
  - Then the configure page shows the latest run as failed, with a link
    to its job page, and offers the start buttons again; no row changed,
    and an upload still gets its verdict

- **AC9 — one run at a time**
  - Given a run that is `starting`, `in_progress` or `stopping`
  - When the configure page is opened, and when its start field is posted
    anyway
  - Then the page shows the run in progress and no start buttons, and the
    post dispatches no second job and says so

- **AC10 — global administrators only**
  - Given a site administrator, an editor and an author
  - When each opens the configure page, or posts its start field
  - Then none of them can start a run (Omeka refuses the page, as in
    SPEC-004), and no job is dispatched

- **AC11 — starting a run does not save the trust settings**
  - Given stored trust settings, and a configure form whose settings
    fields the test has changed
  - When the start button is pressed in that form
  - Then the stored settings are unchanged, and the run uses them

- **AC12 — uploads during a run**
  - Given a run `in_progress`
  - When a file is uploaded through the admin form
  - Then the upload proceeds and gets its verdict as in SPEC-002, and the
    run completes

- **AC13 — PHP below 8.3 in the job process** *(error path)*
  - Given a job process whose `PHP_VERSION` is below 8.3 (the unit test's
    version stand-in of SPEC-002 AC12; the PHP 8.2 container's PHP is not
    the one Omeka's CLI uses there)
  - When the job checks a media item
  - Then it loads no verifier and stores `error` with reason
    `requirements`, as an upload would

## References

- Specification: the Omeka S developer documentation (`Jobs`, under
  `omeka.org/s/docs/developer/`); the Omeka S 4.2.1 source:
  `application/src/Job/` (`AbstractJob`, `Dispatcher`,
  `DispatchStrategy/PhpCli.php`, `DispatchStrategy/Synchronous.php`),
  `application/data/scripts/perform-job.php`,
  `application/src/Controller/Admin/JobController.php`,
  `application/src/Entity/Job.php`.
- Oracle: provemark/c2pa-verifier v0.10.0 through `vendor/bin/c2pa-verify
  [--settings f]` on the same fixtures, as in SPEC-002 and SPEC-004; the
  upload path of SPEC-002 for the expected row; Omeka S 4.2.1 on PHP
  8.3.36.
- Reasoned:
  - that a run's time grows with the size of the originals rather than
    their number (not measured one by one);
  - that the job process's PHP can differ from the web server's (that
    is why AC13 exists).

## API sketch

```php
namespace ContentCredentials\Omeka;

final class RecheckJob extends \Omeka\Job\AbstractJob
{
    public const ALL = 'all';
    public const WITHOUT_RESULT = 'without_result';

    // args: ['media' => self::ALL | self::WITHOUT_RESULT]
    public function perform(): void;   // never throws for one media item
}

final class RecheckRuns          // the configure page's section
{
    public static function create(ServiceLocatorInterface $services): self;
    public function latest(): ?\Omeka\Entity\Job;          // by class
    public function start(string $media): ?string;          // null, or why not (AC9)
    public function html(PhpRenderer $view): string;
}
```

`Module::handleConfigForm()` sees the start field
(`content_credentials_recheck`) and starts a run instead of saving the
settings (AC11).

## Open questions

Each with my recommendation and the trade-off. Maurice decides.

1. **Which media a run takes.** Recommended: two buttons, "Check all
   media" and "Check media without a result". All media is the re-check
   after a settings change or with time; without a result is the catch-up
   after install. Rejected options:
   - checking only media checked with another verifier version or trust
     source: the version alone says nothing about expired certificates;
   - checking only media whose file changed: Omeka does not change an
     original.

   Trade-off: two buttons instead of one.
   *Status 2026-11-04:* answered, the recommendation approved by Maurice
   van Loon with the spec.
2. **Replace or keep the earlier verdict.** Recommended: replace, as an
   upload does (measured: the upsert). The row then shows what holds now.
   What held at upload is lost until M5 keeps evidence. Keeping the first
   verdict would make a re-check useless after a settings change.
   *Status 2026-11-04:* answered, the recommendation approved by Maurice
   van Loon with the spec.
3. **Who starts a run, and where.** Recommended: global administrators,
   on the module's configure page. That page is measured as global-admin
   only and CSRF-checked, and Omeka links to it since SPEC-004
   amendment 1. A page of the module's own needs a route, a controller
   and ACL rules, none of which are measured. Trade-off: the settings
   and the runs share one form, so AC11 is needed.
   *Status 2026-11-04:* answered, the recommendation approved by Maurice
   van Loon with the spec.
4. **How progress shows.** Recommended:
   - the configure page shows the latest run (status, start, end, a link
     to Omeka's job page);
   - the job log gets a line every 500 media, "Checked N of M media.",
     and an end line, "Checked N media: A Trusted, B Valid, C Invalid,
     D none, E error.";
   - a media item that could not be stored gets "The check of media N
     was not stored.", without the exception's class.

   Omeka's job page then shows status and the log with nothing of our
   own. Trade-off: no live progress bar.
   *Status 2026-11-04:* answered, the recommendation approved by Maurice
   van Loon with the spec.
5. **After a stop.** Recommended: a new run starts again from the first
   media item. At the measured rate this costs seconds to minutes, and
   it keeps the run free of state of its own. "Check media without a
   result" catches up after a stop that came before the first rows,
   not one that replaced old rows. Carrying on from the last id is
   possible (measured) but needs that id stored somewhere; out of scope.
   *Status 2026-11-04:* answered, the recommendation approved by Maurice
   van Loon with the spec.

## Traceability

Filled when status becomes `implemented`.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1 | `tests/Integration/RecheckTest.php` :: AC1 | `Omeka\RecheckJob`, `Check\Recheck`, `Omeka\RecheckRuns::start()` |
| AC2 | `tests/Integration/RecheckTest.php` :: AC2 | `Omeka\RecheckJob::run()` (`without_result`) |
| AC3 | `tests/Integration/RecheckTest.php` :: AC3 | `Omeka\RecheckJob::run()` (trust built once) |
| AC4 | `tests/Integration/RecheckTest.php` :: AC4 | `Check\Recheck`, `Omeka\DbalVerdictStore` (upsert) |
| AC5 | `tests/Integration/RecheckTest.php` :: AC5 | `Check\Recheck::run()` (`STOP_EVERY`), `Display\RecheckSection` |
| AC6 | `tests/Integration/RecheckTest.php` :: AC6 | `Check\MediaCheck`, `Omeka\RecheckJob::run()` (paths) |
| AC7 | `tests/Integration/RecheckTest.php` :: AC7; `tests/Unit/RecheckTest.php` :: AC7 | `Check\Recheck`, `Check\RecheckCounts`, `Omeka\RecheckJob::perform()` |
| AC8 | `tests/Integration/RecheckTest.php` :: AC8 | `Omeka\RecheckRuns::start()`, `Display\RecheckSection` |
| AC9 | `tests/Integration/RecheckTest.php` :: AC9 | `Omeka\RecheckRuns::start()`, `Display\RecheckSection::RUNNING` |
| AC10 | `tests/Integration/RecheckTest.php` :: AC10 | Omeka's configure ACL; `Module::handleConfigForm()` |
| AC11 | `tests/Integration/RecheckTest.php` :: AC11 | `Module::handleConfigForm()` |
| AC12 | `tests/Integration/RecheckTest.php` :: AC12 | `Omeka\RecheckJob` |
| AC13 | `tests/Unit/RecheckTest.php` :: AC13 | `Check\MediaCheck` via `Check\Recheck::run($total, $phpVersion)` |
