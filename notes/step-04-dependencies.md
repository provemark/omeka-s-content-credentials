# Step 04: the PHP floor and the first dependencies (M0.4)

2026-11-03. Two ADRs, both `proposed` until Maurice accepts them, and the
`composer.json` they describe:

- [`docs/adr/ADR-0001-php-floor.md`](../docs/adr/ADR-0001-php-floor.md):
  PHP 8.3 or later; Omeka S on 8.1 or 8.2 is excluded.
- [`docs/adr/ADR-0002-dependencies.md`](../docs/adr/ADR-0002-dependencies.md):
  the verifier at runtime; Pest, Pint, PHPStan and Deptrac for
  development; Omeka S's classes for static analysis from the pinned
  release zip, not from Composer.

## Measured

Host: macOS (arm64), PHP 8.5.12, Composer 2.10.2, Docker 29.6.2.

1. **Latest stable versions** (Packagist `p2` metadata, 2026-11-03):
   `provemark/c2pa-verifier` 0.10.0 (`^8.3`), `pestphp/pest` 5.3.5
   (`^8.4`) and 4.7.8 (`^8.3.0`), `laravel/pint` 1.32.1 (`^8.3.0`),
   `phpstan/phpstan` 2.3.5 (`^7.4|^8.0`), `deptrac/deptrac` 4.7.2
   (`^8.2`).
2. **`omeka/omeka-s` cannot be required.** In a scratch project,
   `composer update` with `"omeka/omeka-s": "4.2.1"` fails: "omeka/omeka-s
   v4.2.1 requires omeka-s-themes/default dev-develop -> could not be
   found in any version".
3. **The tools resolve and run.** Without Omeka S, the five packages
   resolve with the platform at PHP 8.3.0. In `php:8.3-cli` (8.3.33),
   `php:8.4-cli` (8.4.26) and `php:8.5-cli` (8.5.11), each prints its
   version: Pest 4.7.8, PHPStan 2.3.5, Pint 1.32.1, Deptrac 4.7.2, and the
   verifier's `Verifier::VERSION` is 0.10.0. (Pest refuses to start without
   a `tests/` directory.)
4. **Omeka S 4.2.1's own PHP ranges.** For each package in the release's
   `composer.lock`, `Composer\Semver\Semver::satisfies()` (from the
   release's own `vendor/`) against its `require.php`: PHP 8.3.0 is
   excluded by 0 of 81 packages, 8.4.0 by 4, and 8.5.0 by 29, all Laminas
   (the list is in ADR-0001). Declared ranges; Omeka S was not run on 8.4
   or 8.5.
5. **This repository.** `composer validate --strict`: valid.
   `composer install`: the five direct packages at the versions above;
   `composer audit`: no advisories. `vendor/` is ignored by git.
6. **The verifier in the integration container** (PHP 8.3.36, through the
   read-only mount): the verifier's `requirements.php` returns
   `{"supported":true,"missing":[],"ed25519":true}`; `vendor/bin/c2pa-verify`
   on Omeka S's `application/asset/img/theme.jpg` prints a report with
   `"active_manifest": null` (a file without Content Credentials).

## Reasoned, not measured

- That Omeka S's autoloader and this module's `vendor/` coexist in one
  process: only the verifier on its own was run in the container. To
  measure with the skeleton (M0.5).
- That `config/module.ini` can state a PHP requirement: to measure in M0.5.
- The composer package name `mauricevanloon/omeka-s-content-credentials`
  follows Tracefern's; it is not published and can change with the
  ProveMark renaming.
