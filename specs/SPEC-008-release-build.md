# SPEC-008: The release build, and an uninstall that removes everything

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | implemented                                       |
| Author     | Maurice van Loon                                  |
| Approved   | Maurice van Loon, 2026-11-06                      |
| Supersedes | —                                                 |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

An administrator cannot install the module yet: there is no zip. The repository
runs on a development `vendor/` that Omeka S must never autoload, and with the
verifier's own namespace another module's copy can decide which verifier writes this
module's record. And uninstalling leaves the module's job rows behind, says nothing about
the evidence it deletes, and lets a run that is going on fill the tables of a later
install.

This spec is M7 of `docs/milestones.md`, without the release itself (M7.6). It rests on:

- [ADR-0006](../docs/adr/ADR-0006-prefixing-the-verifier.md), which decides the tool, the
  pin, the prefix and the zip's contents;
- `notes/step-28-the-release-build.md`, measured on a build copy: the zip installs on a clean
  Omeka S 4.2.1, refuses PHP 8.2, agrees with the unprefixed verifier on 10 of 10 fixtures
  with byte-identical documents, and passes 105 of the 106 integration tests;
- `notes/step-29-uninstall.md`, measured on that zip: only the job rows are left; nothing
  refers to them; a run goes on under an uninstall and into a reinstall, unless its row is
  deleted; a `module.ini` description shows on the confirmation in every state.

## Scope

**In scope**

- `tools/build.sh`, which makes the zip from the committed HEAD as ADR-0006 says.
- One way for the module to find the verifier in both layouts.
- `uninstall()` deleting the module's job rows.
- The warning about the evidence, in the module's description.
- Tests on the built zip in a clean Omeka S, in `composer check` and CI.
- SPEC-002 AC13 following the folder (amendment below).

**Out of scope** (each needs its own spec before it may be built)

- Publishing: a GitHub release, a public repository, omeka.org (M7.6, Maurice's go).
- The version number; `config/module.ini` stays 0.1.0 until M7.6.
- Upgrades between versions (`upgrade()`, SPEC-001).
- Refusing or delaying an uninstall during a run.
- A warning in another place than the description (below).
- Removing the module's folder from disk, which Omeka S does not do either.
- A signed zip or a checksum file.

## Choices

Proposed with recommendations; Maurice approved all six recommendations on 2026-11-06. The
criteria below follow them.

1. **Job rows: delete in `uninstall()`** (recommended), before the tables are dropped.
   This reaches M7's aim, and also ends a run at once, so that a reinstall starts empty
   (step 29). The cost is the history of the runs, in which there are only counts and media
   ids. Against: leave them, as Omeka leaves its own, and say so. A run then goes on into
   a reinstall and ends `completed` with thousands of "not stored".
2. **The warning: one sentence in `description`** (recommended). It shows in every state
   and needs no code. `view.details` shows only for an active module. Both land below the
   button (step 29). The sentence also appears on the module list. Proposed text at the
   end of the current description: "Uninstalling deletes every verdict and evidence
   document it has stored."
3. **The folder: the loader looks** (recommended). `VerifierLoader::register()` takes the
   module's directory, and uses `vendor-prefixed/provemark/c2pa-verifier/src` when that
   folder exists, otherwise `vendor/provemark/c2pa-verifier/src`. So the build changes no
   source beyond what Strauss does, and needs no `sed`. Against: a constant the build
   rewrites, which keeps the source literal but makes the build edit it.
4. **What ships: a list in `tools/build.sh`** (recommended), not `export-ignore` in
   `.gitattributes`, so that a new file at the root does not ship by accident. AC3 checks
   the result. This departs from Tracefern and from M7.5's wording.
5. **The zip's name: `ContentCredentials-<version>.zip`** (recommended), the version read from
   `config/module.ini`, in `build/`. What omeka.org asks of the name is read in M7.6.
6. **Where the tests run:**
   - `composer check` builds the zip and runs `tests/Release` against it, in an environment
     rebuilt each time (recommended). This closes the blind spot of ADR-0006, at a cost
     not measured yet (estimated a few minutes).
   - CI does this at every push, on Linux and PHP 8.3, and also runs the whole of
     `tests/Integration` against the zip (recommended; 178 s on the Mac in step 28). So a
     build on Linux gets measured, which ADR-0006 leaves open.
   - Against: only before a release. Cheaper, but a build that broke is found late.

## Behavior

Criteria on the zip are in `tests/Release`, those on the module's source in `tests/Unit` or
`tests/Integration`. All are tagged `->group('SPEC-008')`.

### The build

- **AC1 — the zip comes from the committed HEAD**
  - Given a working tree with an uncommitted change in `src/` and an untracked file at the root
  - When `tools/build.sh` runs
  - Then it exits 0, and `build/ContentCredentials-<version>.zip` holds HEAD's `src/`, without
    the change and the untracked file; the working tree and its `vendor/` are unchanged

- **AC2 — a phar that is not the pinned one stops the build** *(error path)*
  - Given `build/.tools/strauss-0.31.1.phar` with other bytes
  - When `tools/build.sh` runs
  - Then it exits non-zero, names the file and both hashes, leaves no zip, and does not run the phar

- **AC3 — the zip holds what ADR-0006 lists, and nothing else**
  - Given the built zip
  - When its entries are listed
  - Then every entry is under `ContentCredentials/`, and the top level is exactly `Module.php`,
    `LICENSE`, `README.md`, `CHANGELOG.md`, `config/`, `src/`, `trust/`, `view/` and
    `vendor-prefixed/`; `vendor-prefixed/` holds only `provemark/c2pa-verifier/` with `src/`,
    `LICENSE` and `composer.json`; no entry matches `Cli/`, `.key`, `tests/`, `notes/`, `specs/`,
    `tools/`, `.git`, `CLAUDE`, `.DS_Store`, `composer.lock` or `autoload`

- **AC4 — nothing but the prefix changed**
  - Given the built zip and the repository at HEAD
  - When the verifier in the zip, with `ContentCredentials\Vendor\` taken out, is compared with
    `vendor/provemark/c2pa-verifier/src`, and `trust/`, `view/`, `config/` and `Module.php` with HEAD's
  - Then the verifier is identical apart from the missing `Cli/`, its `LICENSE` is identical,
    the others are identical byte for byte, and no file in the zip's `src/` or verifier
    names `Provemark\C2paVerifier` without the prefix

### The zip in a clean Omeka S

- **AC5 — it installs, and gives the verifier's verdicts**
  - Given a clean Omeka S 4.2.1 on PHP 8.3 with only the zip unpacked in `modules/`
  - When the module is installed and every JPEG, PNG and WebP fixture is uploaded through the admin form
  - Then the module is active with the version of `config/module.ini`, and each verdict
    equals the unprefixed verifier's on the same bytes (`none` for a file without a manifest,
    SPEC-002), its `verifier` column holds the unprefixed verifier's version, and each stored
    document is byte-identical to `evidenceToJson()` as SPEC-006 AC1 compares it

- **AC6 — it refuses PHP 8.2** *(error path)*
  - Given a clean Omeka S 4.2.1 on PHP 8.2 with only the zip unpacked
  - When the module is installed through the modules page
  - Then the page shows SPEC-001 AC6's message, no module row exists, and the snapshot is unchanged

- **AC7 — another module's copy does not reach it**
  - Given the zip active next to a module that autoloads its own unprefixed copy of the
    verifier with another `VERSION`, from before this module's loader is registered
  - When a signed JPEG is uploaded
  - Then the verdict is `Valid` and its `verifier` is the zip's version, not the other copy's

- **AC8 — the integration tests hold on the zip** *(in CI)*
  - Given the zip unpacked, with `tests/Fixtures` and `tests/Integration/Support` added beside it
  - When `tests/Integration` runs against that installation
  - Then every test passes, SPEC-002 AC13 included

### Finding the verifier

- **AC9 — both layouts**
  - Given a module directory holding only `vendor-prefixed/provemark/c2pa-verifier/src`, only
    `vendor/provemark/c2pa-verifier/src`, or both
  - When the loader picks the source directory
  - Then it is the prefixed one, the other one, and the prefixed one

- **AC10 — no verifier at all** *(error path)*
  - Given the installed module with neither folder present
  - When a signed JPEG is uploaded
  - Then the upload succeeds, the verdict is `error` with reason `exception`, no evidence row
    is written, and the media page and configure page answer 200

### Uninstall

- **AC11 — uninstalling after use leaves nothing of the module**
  - Given a snapshot of the installation, then the module installed, files uploaded, the
    settings saved and a run completed
  - When the items are deleted and the module is uninstalled
  - Then the snapshot equals the one before, in schema and rows of every table but `session` (the
    measuring helper's own API key apart)

- **AC12 — only the module's job rows go**
  - Given job rows of this module and of another class, in any status
  - When the module is uninstalled
  - Then the rows whose class is `ContentCredentials\Omeka\RecheckJob` are gone, the others are
    unchanged, and `/admin/job` answers 200

- **AC13 — from a deactivated module too**
  - Given the module deactivated, with job rows
  - When it is uninstalled
  - Then the tables, settings and job rows are gone (its `src/` is not on the autoloader then)

- **AC14 — uninstalling during a run** *(error path)*
  - Given a run in progress over thousands of media
  - When the module is uninstalled
  - Then the uninstall redirects with its success message, the job's process ends within
    30 seconds, no row of the job and no table of the module remain, and admin pages answer 200;
    installing afterwards gives empty tables that stay empty

- **AC15 — the warning**
  - Given the module active, and then deactivated
  - When the modules page and `/admin/module/uninstall-confirm?id=ContentCredentials` are opened
  - Then both show the sentence of choice 2, from `config/module.ini`, escaped by Omeka S
    (`escapeHtml` in `show-details.phtml`)

## References

- Specification: Omeka S 4.2.1 source: `Omeka\Module\Manager::uninstall()`,
  `Omeka\Controller\Admin\ModuleController::uninstallConfirmAction()`,
  `application/view/omeka/admin/module/uninstall-confirm.phtml` and `show-details.phtml`,
  `Omeka\Job\AbstractJob::shouldStop()`, `Omeka\Job\DispatchStrategy\Synchronous`.
- Oracle: `provemark/c2pa-verifier` v0.10.0 in `vendor/`, unprefixed, on the same bytes; Strauss
  0.31.1 (ADR-0006); `OmekaInstallation::snapshot()`; Omeka S 4.2.1 on PHP 8.3.36 and 8.2.35,
  MariaDB 11.4 (steps 28, 29).
- Reasoned:
  - Why a run ends when its row goes (read, not captured; the job's output goes to `/dev/null`
    in `PhpCli`): `shouldStop()`, called every 50 media, uses `getSingleScalarResult()`, which throws
    `NoResultException` without a row. Nothing in `Recheck` catches it, so the process ends
    on a fatal error. `handleFatalError()` then writes to a row that is gone.
  - A run ending at the very moment of the uninstall could write its row back:
    `Synchronous::send()` ends with `merge()` and `flush()`. See the limits below.
  - The cost of the build and of `tests/Release` in `composer check`.

## API sketch

```php
// namespace ContentCredentials\Omeka;
final class VerifierLoader
{
    /** Which source directory the module runs on: the prefixed one when it exists. */
    public static function sourceDirectory(string $moduleDirectory): string;

    public static function register(string $moduleDirectory): void; // now takes the module's directory
}
```

```php
// Module.php, parseable on PHP 8.1; src/ is not on the autoloader for an inactive module,
// so the class name is written out, as the setting keys are.
$connection->executeStatement('DELETE FROM job WHERE class = ?', ['ContentCredentials\Omeka\RecheckJob']);
$connection->executeStatement(Schema::drop());
```

```json
"extra": { "strauss": {
    "target_directory": "vendor-prefixed",
    "namespace_prefix": "ContentCredentials\\Vendor\\",
    "packages": ["provemark/c2pa-verifier"],
    "update_call_sites": ["src"],
    "delete_vendor_packages": true
} }
```

## Amendments to other specs (proposed with this one)

- **SPEC-002 AC13:** "no file of the module's `vendor/` outside
  `vendor/provemark/c2pa-verifier/src/`" becomes: of the folder the module runs on, `vendor/` or
  `vendor-prefixed/`, only `provemark/c2pa-verifier/src/` is included, and at least one file from
  there. Omeka's `LoggerInterface` as before.
- **SPEC-001:** none. AC4 stands; AC11 here is the "own uninstall criterion" SPEC-001 asks of
  later specs.
- **SPEC-005:** none; it says nothing about job rows after an uninstall
  (`notes/step-21-recheck.md` left it to M7).
- **SPEC-006:** none; its Out of scope already leaves the warning to M7.

## Known limits

- **A race at the end of a run:** a run finishing in the moment of an uninstall could write
  back its job row (reasoned, not measured). It would be one row of counts.
- **In the first moments after the uninstall** a run checks for its row only every 50 media.
  A reinstall within that time could take some rows from it. Step 29 measured none, with
  a reinstall by script at once.
- **What the zip does not prove:** PHP 8.4 and 8.5 stay unmeasured on the zip unless CI's matrix
  is widened, and the zip's first look in a browser is Maurice's.

## Amendments

- **Amendment 1 (2026-11-06, approved by Maurice van Loon):** `tools/build.sh` takes an optional
  tree-ish, HEAD by default, and `tools/env/release.sh` gives it a tree of the working tree, through
  a temporary index. Otherwise `composer check` before a commit would test the previous commit, and
  a new build would never be tested. Was: no argument. AC1 is unchanged.
- **Amendment 2 (2026-11-06, approved by Maurice van Loon):** AC4's last clause holds for the PHP
  source: no file in the zip's `src/` or the verifier's `src/`. The verifier's `composer.json`, which
  ADR-0006 keeps and nothing loads, still names its namespace. Was: "no file in the zip's `src/` or
  verifier".
- **Amendment 3 (2026-11-06, approved by Maurice van Loon):** AC11 and AC14 are in `tests/Release`,
  as they compare every row and start thousands of checks, which takes a clean installation. AC11
  leaves the whole of `api_key` out, the helper's table, whose last-used time moves with each call.
  Was: criteria on the module's source in `tests/Unit` or `tests/Integration`.

## Open questions

- All six choices above: blockers, for Maurice.
  *Status 2026-11-06:* answered: Maurice approved all six recommendations with the spec.

## Traceability

Filled when status becomes `implemented`.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1 | tests/Release/BuildTest.php :: AC1: the zip comes from the committed HEAD / SPEC-008 | tools/build.sh |
| AC2 | tests/Release/BuildTest.php :: AC2: a phar that is not the pinned one stops the build / SPEC-008 | tools/build.sh |
| AC3 | tests/Release/BuildTest.php :: AC3: the zip holds what ADR-0006 lists, and nothing else / SPEC-008 | tools/build.sh; composer.json :: extra.strauss |
| AC4 | tests/Release/BuildTest.php :: AC4: nothing but the prefix changed / SPEC-008 | tools/build.sh; composer.json :: extra.strauss |
| AC5 | tests/Release/ZipInOmekaTest.php :: AC5: it installs, and gives the verifier's verdicts / SPEC-008 | tools/build.sh; tools/env/release.sh; src/Omeka/VerifierLoader.php |
| AC6 | tests/Release/ZipInOmekaTest.php :: AC6: it refuses PHP 8.2 / SPEC-008 | Module.php :: install(); src/Requirements.php |
| AC7 | tests/Release/ZipInOmekaTest.php :: AC7: another module's copy does not reach it / SPEC-008 | tools/build.sh; composer.json :: extra.strauss |
| AC8 | tests/Release/BuildTest.php :: AC8: the integration tests hold on the zip / SPEC-008; the run: .github/workflows/ci.yml :: release | tools/env/release.sh --integration; tests/Integration/Support/included-files.php |
| AC9 | tests/Unit/VerifierLoaderTest.php :: AC9: both layouts / SPEC-008 | src/Omeka/VerifierLoader.php :: sourceDirectory() |
| AC10 | tests/Release/ZipInOmekaTest.php :: AC10: no verifier at all / SPEC-008 | src/Omeka/VerifierLoader.php :: register() |
| AC11 | tests/Release/UninstallTest.php :: AC11: uninstalling after use leaves nothing of the module / SPEC-008 | Module.php :: uninstall(); src/Omeka/Schema.php |
| AC12 | tests/Integration/UninstallJobsTest.php :: AC12: only the module's job rows go / SPEC-008 | src/Omeka/Schema.php :: DELETE_JOBS, JOB_CLASS |
| AC13 | tests/Integration/UninstallJobsTest.php :: AC13: from a deactivated module too; tests/Unit/VerifierLoaderTest.php :: AC13: from a deactivated module too: the class written out is the job / SPEC-008 | Module.php :: uninstall(); src/Omeka/Schema.php :: JOB_CLASS |
| AC14 | tests/Release/UninstallTest.php :: AC14: uninstalling during a run / SPEC-008 | Module.php :: uninstall() |
| AC15 | tests/Integration/UninstallJobsTest.php :: AC15: the warning / SPEC-008 | config/module.ini :: description |
