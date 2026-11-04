# Step 07: `composer check` (M0.7)

2026-11-04. The whole of `composer check`, in the brief's order:
spec-check, Pint, PHPStan at level max, Deptrac, Pest. The integration
tests follow in M0.7b, with SPEC-001: a test needs a spec group, and
installing and uninstalling the module is behaviour, so it gets a spec
first.

## What was built

| file | what |
|---|---|
| `pint.json` | the verifier's: preset `laravel`, `declare_strict_types`; excludes `vendor`, `build`, `tests/Fixtures` |
| `phpstan.neon` | level max over `Module.php`, `config`, `src`, `bin`, `tests`, `docker/omeka/install.php`; Omeka S's classes by `scanDirectories`; one excluded path, `tests/Fixtures` (SPEC-000) |
| `tools/fetch-omeka.sh` | unpacks the pinned Omeka S release into `build/omeka-s/`, version and SHA-256 read from `docker/omeka/Dockerfile` |
| `deptrac.yaml` | `src/`, no layers yet |
| `composer.json` | `check`: `@spec-check`, `@lint`, `@analyse` (fetch, then PHPStan), `@deptrac`, `@test` |

Changed to pass: `docker/omeka/install.php` (Pint's style; real types:
`instanceof` checks on `Omeka\Mvc\Status` and `Omeka\Installation\Installer`,
and Omeka S's directory from `OMEKA_ROOT`, now set in `compose.yaml`);
`Module::getConfig()` (throws when the config file does not return an
array); SPEC-000's AC10 test (narrows the decoded `composer.json` before
reading it). `tests/Fixtures` is excluded from Pint and PHPStan because its
files are SPEC-000's inputs, and the AC11 and AC12 tests name their line
numbers.

## Measured

Host PHP 8.5.12; Pint 1.32.1, PHPStan 2.3.5, Deptrac 4.7.2, Pest 4.7.8.

1. **Pint** on the tree: one file, `docker/omeka/install.php` (six fixers);
   after `pint` on that file, `pint --test` passes.
2. **PHPStan** first run: 19 errors (`Module.php` 1, `install.php` 13,
   `SpecCheckTest.php` 5). One more round after the type fixes: 2 errors,
   "Path in require() "/var/www/html/bootstrap.php" is not a file": PHPStan
   resolves a constant path and checks it on the host. With the path from
   the environment: no errors.
3. **Omeka S's classes come from the scan.** Falsified with a copy of the
   configuration without `scanDirectories`: "Class
   ContentCredentials\Module extends unknown class", "Class
   Omeka\Mvc\Status not found", "Class Omeka\Installation\Installer not
   found". `Laminas\Mvc\Application` is not in `vendor/laminas` of the
   release: Omeka S ships its own fork, `vendor/omeka/sanimal-mvc`; so the
   scan covers `application/src`, `vendor/laminas` and `vendor/omeka`, not
   the whole `vendor/`.
4. **`tools/fetch-omeka.sh`**: first run unpacks ("Omeka S 4.2.1 unpacked
   in build/omeka-s/"), the second is silent; `build/` is ignored.
5. **Deptrac** on the empty `src/`: 0 violations, exit 0. A probe class
   `src/Probe.php` in no layer, with `--fail-on-uncovered`: still
   "Uncovered 0", exit 0. Deptrac's "uncovered" counts dependencies, not
   files; it does not enforce that every file is in a layer. The probe was
   removed.
6. **`composer check`**: every step passes, exit 0 (spec-check "OK: 1
   spec(s), 1 test file(s)", Pint passed, PHPStan no errors, Deptrac 0
   violations, 16 tests passed).
7. **The environment again**, because `install.php`, `compose.yaml` and
   `Module.php` changed: `tools/env/reset.sh` installs Omeka S ("Omeka S
   installed."), a second `up.sh` leaves it; the module installs, `/admin`
   answers 200 and it uninstalls, with the database as before except for a
   session row (the procedure of step 05).

## Open

- "One Deptrac layer per milestone, added before its first file" is not
  enforced by Deptrac (item 5). Options: a check in a spec of its own, or a
  rule kept by review. To decide with M1's first layer.
- The Omeka S scan follows the release's directory layout; a new pin may
  need other directories. Noted for the next pin.

# M0.7b: SPEC-001, install and uninstall

2026-11-04. SPEC-001 approved by Maurice van Loon with both proposals of
its open questions, and now `implemented`.

## What was built

| file | what |
|---|---|
| `deptrac.yaml` | the first layer, `Requirements` (M0, SPEC-001), depending on nothing, added with the first file in `src/` |
| `src/Requirements.php` | `phpProblem()`: null from PHP 8.3.0, else the message; what is not `major.minor.patch` is refused |
| `Module.php` | `install()` throws `ModuleCannotInstallException` with that message; loads `src/Requirements.php` itself (below) |
| `tests/Unit/RequirementsTest.php` | AC5, 13 data sets |
| `tests/Integration/InstallAndUninstallTest.php` | AC1–AC4 on PHP 8.3, AC6 on PHP 8.2 |
| `tests/Integration/Support/` | `OmekaInstallation` (the admin interface over HTTP with a cookie jar, the database through `docker compose exec`), `HttpResponse` |
| `docker/omeka/Dockerfile` | the base image as a build argument, `BASE_IMAGE` |
| `compose.yaml` | a second installation, `omeka-php82` on `php:8.2-apache` (pinned by digest) with `db-php82`, port 8089 |
| `tools/env/up.sh` | installs and checks both installations |
| `composer.json`, `phpunit.xml` | the `Integration` suite; `test:integration` (`up.sh`, then Pest) as the last step of `check`; `autoload-dev` for `tests/` |

`8.3` (without a patch number) is refused as "not a version": PHP_VERSION
always has three parts, so a shorter string is not something PHP gives.

## Seen red

1. AC5: all 13 data sets failed, the class not existing yet.
2. AC6: failed on PHP 8.2 with "The module was successfully installed"
   instead of the refusal; the module had really installed there, so AC6
   was given the same "uninstalled first" starting state as the others.
3. AC1 failed first on a fault in the test, not the module: the expected
   snapshot did not count the `module` table going from 0 to 1 rows.
   Corrected; then green against the skeleton.
4. AC1–AC4 passed against the skeleton of step 05, which already behaved
   so. To see them red, a temporary `Module.php` (never committed) wrote a
   setting in `install()`, counted every request in a setting, and threw on
   `/admin/item`: all four failed. `Module.php` was restored with
   `git checkout`, and the environment reset to remove the two settings.

## Measured

Host PHP 8.5.12; the environment of step 03 with the second installation:
PHP 8.2.35 (`php:8.2-apache`, digest `89e5f7bd…`, Debian 13).

1. `tools/env/reset.sh` with both installations: exit 0 in 24.7 s,
   including building the PHP 8.2 image; "Omeka S installed." twice.
2. **A module's `src/` is not autoloaded while it is installed.** With the
   first implementation, installing on PHP 8.3 answered HTTP 500. In the
   container, after `Omeka\Mvc\Application::init()`, with the module not
   installed: `class_exists('ContentCredentials\Module')` true,
   `class_exists('ContentCredentials\Requirements')` false. Read in
   `Application::init()`: only active modules are loaded, and the `src/`
   autoloader comes with loading. So `install()` requires
   `src/Requirements.php` itself. Every later `install()` or `uninstall()`
   must do the same for what it uses from `src/`.
3. `composer check` after `reset.sh`: exit 0 in 29.9 s; spec-check "OK: 2
   spec(s), 3 test file(s)", Pint, PHPStan, Deptrac pass, 29 unit tests and
   5 integration tests pass.
4. The unit suite in `php:8.3-cli` and `php:8.4-cli`: 29 passed on both.
5. The page on PHP 8.2 after the refused install:
   `<ul class="messages"><li class="error">Content Credentials (C2PA) needs
   PHP 8.3 or later; this server runs PHP 8.2.35.</li></ul>`, and no
   `module` row.

## Reasoned, not measured

- That the message is escaped: read in `Omeka\View\Helper\Messages`; the
  message holds no character that escaping changes, so the page cannot show
  it.
- The integration tests ran only from the host's PHP 8.5; their support
  code avoids `http_get_last_response_headers()` (8.4+) and
  `$http_response_header` (deprecated in 8.5), but it was not run on 8.3.
- SPEC-000's AC8 test asserted exactly one test file; it now counts the
  files outside `tests/Fixtures` itself. A test made less brittle, not a
  change of the criterion.
