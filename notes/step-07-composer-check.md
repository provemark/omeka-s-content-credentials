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
