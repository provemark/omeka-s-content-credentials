# SPEC-001: Install and uninstall

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

An administrator installs a module from the modules page and expects two
things: that it changes nothing it does not need, and that uninstalling it
leaves the installation as it was. Milestone M0 is done only when the
module "installs and uninstalls cleanly in the pinned Omeka S"
(`docs/milestones.md`). That was measured by hand in step 05
(`notes/step-05-module-skeleton.md`); this spec makes it a test that runs
in `composer check`, so every later milestone keeps it true.

Second, the module needs PHP 8.3 (ADR-0001), while Omeka S 4.2 runs from
8.1. `config/module.ini` has no key for a PHP version (step 05, read in
the Omeka S 4.2.1 source). Without a check, a module installed on PHP 8.1
or 8.2 would install and then fail later, on the first upload, inside
Omeka S. Omeka S offers the way to refuse: a module's `install()` that
throws `Omeka\Module\Exception\ModuleCannotInstallException` makes the
modules page show the message, and no `module` row is written
(`Omeka\Module\Manager::install()`, `ModuleController::installAction()`).

## Scope

**In scope**

- Installing, deactivating, activating and uninstalling through the admin
  interface of the pinned Omeka S, as the test administrator of the Docker
  environment.
- What the database holds before installing and after uninstalling.
- Refusing to install on PHP below 8.3, with a message.
- The integration test suite: `tests/Integration`, run against the
  environment of `tools/env/`, as a step of `composer check`.

**Out of scope** (each needs its own spec before it may be built)

- Tables, settings or files of the module's own: there are none yet. Each
  spec that adds one adds its own uninstall criterion.
- Upgrades between module versions (`upgrade()`).
- Installing through anything other than the admin interface (there is no
  Omeka S command line for modules).
- What happens on PHP 8.1 or 8.2 after an install that bypassed the check.

## Behavior

Acceptance criteria as Given/When/Then. AC1–AC4 and AC6 run in
`tests/Integration` against the Docker environment (Omeka S 4.2.1, PHP
8.3, MariaDB 11.4); AC5 in `tests/Unit`. Each is tagged
`->group('SPEC-001')`. A *snapshot* of the database is its schema
(`mariadb-dump --no-data`, auto-increment values left out), the number of
rows of every table, every `setting` row, and the `module` table; the
`session` table's row count is left out, because logging in adds to it.

- **AC1 — installing adds one row and one empty table** *(amendment 1)*
  - Given a freshly reset environment, where the modules page lists
    ContentCredentials as not installed
  - When the administrator installs it through the modules page
  - Then the response is a redirect to the modules page with "The module
    was successfully installed", the `module` table holds
    `ContentCredentials`, active, with the version of `config/module.ini`,
    and the snapshot differs from the one before only in that row and in
    the table `content_credentials_verdict` (SPEC-002), with no rows

- **AC2 — an active module breaks no page**
  - Given the module installed and active
  - When the administrator opens `/admin`, `/admin/item`, `/admin/media`
    and `/admin/module`, and a visitor opens `/`
  - Then each answers HTTP 200

- **AC3 — deactivating and activating**
  - Given the module installed and active
  - When the administrator deactivates it, then activates it
  - Then `module.is_active` is 0 after the first and 1 after the second,
    and nothing else in the snapshot changes

- **AC4 — uninstalling leaves the installation as it was**
  - Given the snapshot taken before AC1's install
  - When the administrator uninstalls the module through the confirmation
    form
  - Then the response is a redirect with "The module was successfully
    uninstalled", and the snapshot equals the one before the install

- **AC5 — PHP below 8.3 is refused, with a message** *(required: error /
  malformed input)*
  - Given a PHP version string
  - When the module's requirement check reads it
  - Then `8.3.0` and later pass; `8.2.99`, `8.1.0` and anything that is
    not a version (empty, `abc`) fail closed, with the message
    "Content Credentials (C2PA) needs PHP 8.3 or later; this server runs
    PHP <version>." The version in the message is the string as given,
    which comes from PHP itself, not from a file or a user. The modules page
    shows it through Omeka S's messenger, which passes a plain string
    through the `escapeHtml` view helper (`Omeka\View\Helper\Messages`,
    read in the 4.2.1 source; AC6 sees it on the page)

- **AC6 — the refusal reaches the modules page and writes nothing**
  *(error path)*
  - Given the module and an Omeka S 4.2.1 running on PHP 8.2
  - When the administrator installs it through the modules page
  - Then the modules page shows AC5's message as an error, the `module`
    table has no row for ContentCredentials, and the snapshot is unchanged

## References

- Specification: Omeka S 4.2.1 source, `Omeka\Module\Manager::install()`
  and `uninstall()`, `Omeka\Controller\Admin\ModuleController`,
  `Omeka\Module\Exception\ModuleCannotInstallException`; the Omeka S
  developer documentation, "Modules"
  (`omeka.org/s/docs/developer/modules/`).
- Oracle: the procedure of step 05 (`notes/step-05-module-skeleton.md`),
  whose by-hand results AC1–AC4 make into tests: Omeka S 4.2.1 on PHP
  8.3.36 and MariaDB 11.4.13.
- Reasoned: that an install refused in `install()` writes no row (read in
  `Manager::install()`, where the row is persisted after the call; AC6
  measures it); that the refusal message is escaped (read in
  `Omeka\View\Helper\Messages`).

## API sketch

```php
// src/Requirements.php: parseable on PHP 8.1 (ADR-0001), no dependency on Omeka S.
namespace ContentCredentials;

final class Requirements
{
    public const MINIMUM_PHP = '8.3.0';

    /** The message to show when $phpVersion is too old or not a version; null when it passes. */
    public static function phpProblem(string $phpVersion): ?string;
}

// Module::install(): if (($problem = Requirements::phpProblem(PHP_VERSION)) !== null)
//     throw new ModuleCannotInstallException($problem);
```

The integration tests drive Omeka S as step 05 did: `curl`-like requests
from PHP with a cookie jar, the forms' CSRF tokens read from the pages,
and the snapshot taken through `docker compose exec db`. They never touch
anything outside the compose project `content-credentials-test`.

## Open questions

- Blocker: how AC6 gets an Omeka S on PHP 8.2. Proposed: the same image
  built a second time from `php:8.2-apache` (pinned by digest), as a second
  compose service on its own port and database, started only for this
  test. The alternative, testing only AC5 and reasoning the wiring, would
  leave the one path that protects old hosts unmeasured.
  *Status 2026-11-04:* answered, the proposal approved by Maurice van Loon
  with the spec.
- ~~Blocker: whether Omeka S's messenger escapes a message (AC5).~~
  Answered 2026-11-04 by reading `Omeka\View\Helper\Messages::__invoke()`
  (4.2.1, lines 39–55): a message that is not a `MessageInterface`, or one
  whose `escapeHtml()` is true, is escaped with `escapeHtml`;
  `ModuleController::installAction()` passes `$e->getMessage()`, a string.
- Non-blocker: `composer check` will need the Docker environment running
  (or start it, about 10 s when built). Proposed: the integration step
  runs `tools/env/up.sh` first, so `composer check` is one command.
  *Status 2026-11-04:* answered, the proposal approved by Maurice van Loon
  with the spec.

## Amendments

- **Amendment 1 (2026-11-04, approved by Maurice van Loon with SPEC-002):**
  AC1 allows the table `content_credentials_verdict` of SPEC-002, empty,
  next to the module row. Was: "the snapshot differs from the one before
  only in that row". AC4 is unchanged: uninstalling leaves the snapshot as
  it was before installing.

## Traceability

Filled when status becomes `implemented`. Every acceptance criterion maps to at
least one test; every source file maps back to this spec.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1 | tests/Integration/InstallAndUninstallTest.php :: AC1: installing adds one row and one empty table / SPEC-001 | Module.php :: Module (the skeleton); tests/Integration/Support/OmekaInstallation.php :: snapshot(), moduleAction() |
| AC2 | tests/Integration/InstallAndUninstallTest.php :: AC2: an active module breaks no page / SPEC-001 | Module.php :: Module::getConfig() |
| AC3 | tests/Integration/InstallAndUninstallTest.php :: AC3: deactivating and activating / SPEC-001 | Module.php :: Module |
| AC4 | tests/Integration/InstallAndUninstallTest.php :: AC4: uninstalling leaves the installation as it was / SPEC-001 | Module.php :: Module (inherits an empty uninstall()) |
| AC5 | tests/Unit/RequirementsTest.php :: AC5: PHP 8.3.0 and later pass; AC5: PHP below 8.3 is refused with a message; AC5: a string that is not a version fails closed / SPEC-001 | src/Requirements.php :: Requirements::phpProblem() |
| AC6 | tests/Integration/InstallAndUninstallTest.php :: AC6: the refusal on PHP 8.2 reaches the modules page and writes nothing / SPEC-001 | Module.php :: Module::install(); compose.yaml :: omeka-php82, db-php82; docker/omeka/Dockerfile :: BASE_IMAGE |
