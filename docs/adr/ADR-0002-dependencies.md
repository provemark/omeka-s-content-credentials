# ADR-0002: Dependencies

| Field    | Value            |
|----------|------------------|
| Status   | accepted         |
| Date     | 2026-11-03       |
| Decided  | Maurice van Loon |

## Context

No dependency enters this repository without an ADR. This one records the
first set: what the module runs with, what it is checked with, and where
Omeka S's own classes come from for static analysis.

Measured 2026-11-03 (`notes/step-04-dependencies.md`):

| package | version | requires PHP | note |
|---|---|---|---|
| `provemark/c2pa-verifier` | 0.10.0 (2026-10-29) | `^8.3` | `ext-openssl`, `ext-mbstring`; `ext-sodium` suggested, needed on 8.3 for Ed25519 |
| `pestphp/pest` | 4.7.8 | `^8.3.0` | 5.3.5 is the latest, but requires `^8.4` |
| `laravel/pint` | 1.32.1 | `^8.3.0` | |
| `phpstan/phpstan` | 2.3.5 | `^7.4\|^8.0` | |
| `deptrac/deptrac` | 4.7.2 | `^8.2` | |

All five resolve together with the platform set to PHP 8.3.0, and each tool
starts on PHP 8.3, 8.4 and 8.5 (official `php:*-cli` images).

`omeka/omeka-s` v4.2.1 is on Packagist, but cannot be required: it
requires `omeka-s-themes/default` at `dev-develop`, which Composer cannot
find.

## Decision

**Runtime (`require`):**

- `provemark/c2pa-verifier` `^0.10`: the verification itself. Same
  maintainer; pure PHP, no network, no `exec`. Updated deliberately, one
  minor release at a time (in 0.x a minor release may break).
  *2026-11-13 (step 69):* `^0.11`, verifier 0.11.0 (the general box hash
  in a JPEG); measured first, no module code changed.

**Development (`require-dev`):** the tools of `composer check`, the same
as in the sister projects:

- `pestphp/pest` `^4.7` (not 5, which would raise the development floor to
  8.4);
- `laravel/pint` `^1.32`;
- `phpstan/phpstan` `^2.3`;
- `deptrac/deptrac` `^4.7`.

**Omeka S for static analysis:** not a Composer dependency. PHPStan reads
Omeka S's classes from the same release zip the integration image is built
from (v4.2.1, SHA-256 `cc27d1c7…de348e82`), fetched and checked by a
script into an ignored directory. How PHPStan is pointed at it is settled
in M0.7, with that step.

**Not now:** Strauss, to prefix the verifier in the release zip (as
Tracefern's SPEC-009) belongs to the release build (M7) and gets its
amendment to this ADR then. Done in a file of its own:
[ADR-0006](ADR-0006-prefixing-the-verifier.md) (accepted 2026-11-06).

## Consequences

- One runtime dependency, and it has no dependencies of its own.
- Composer runs on the host for development; the integration environment
  loads the module from the mounted repository, including its `vendor/`.
  Whether Omeka S's autoloader and this `vendor/` coexist in one process
  is measured with the skeleton (M0.5); prefixing is the answer if not.
- Moving to Pest 5 is an amendment, once the floor is 8.4.
