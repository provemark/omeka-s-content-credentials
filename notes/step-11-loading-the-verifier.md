# Step 11 (M1.3): loading the verifier inside Omeka S

2026-11-04. Step 05 concluded, by reasoning, that the module must never
load its whole `vendor/`. Before the M1 spec rests on a way of loading
`provemark/c2pa-verifier`, this step measures three ways: the module's
whole development `vendor/autoload.php`; an autoloader for the verifier's
one namespace; and a Strauss-prefixed build copy, as Tracefern ships
(its SPEC-009).

## Read

- The verifier (v0.10.0, `vendor/provemark/c2pa-verifier/composer.json`)
  requires only PHP `^8.3`, `ext-openssl` and `ext-mbstring` (`ext-sodium`
  suggested); no other package. Its autoload is one PSR-4 namespace,
  `Provemark\C2paVerifier\` → `src/`, no `files`. Its source does not use
  `Composer\InstalledVersions`; it reports its version through the
  constant `Verifier::VERSION` (`'0.10.0'`), a typed class constant, which
  PHP 8.2 cannot parse.
- `bin/c2pa-verify` prints `VerificationReport::toJson()` and one newline
  (`src/Cli/Command.php`).
- The three extensions are present in the test environment's PHP 8.3
  image (`php -m`).

## Measured

### A. The module's whole development vendor/

Thirteen packages are in both the module's development `vendor/` and
Omeka S 4.2.1's, every one at another version (from both
`installed.json`), among them `psr/container` 2.0.2 against 1.1.2,
`psr/log` 3.0.2 against 1.1.4, `webmozart/assert` 2.5.0 against 1.12.1,
`symfony/console` 7.4 against 6.4. The module's `platform_check.php`
requires `PHP_VERSION_ID >= 80300`.

A measurement-only module, `tools/probes/AutoloadProbe/` (never shipped),
requires `ContentCredentials/vendor/autoload.php` at the top of its
`Module.php`:

- **PHP 8.2** (`omeka-php82`): the modules page still answered 200;
  installing the probe gave **HTTP 500** with Composer's "Your Composer
  dependencies require a PHP version ">= 8.3.0"", and no module row.
- **PHP 8.3**: installed; `/admin`, `/admin/item`, `/admin/media`,
  `/admin/module`, `/admin/item/add`, `/admin/user`, `/admin/job`,
  `/admin/setting` and `/` answered 200. On those pages
  `Psr\Container\ContainerInterface` came from Omeka's `vendor/` (loaded
  before the module), and `Psr\Log\LoggerInterface`, `Webmozart\Assert`
  and Symfony Console were not loaded at all.
- **The conflict is latent, not absent.** Omeka booted from PHP in the same
  container with the probe active: `Psr\Log\LoggerInterface` and
  `Webmozart\Assert\Assert` resolved to the module's `vendor/` (Composer
  registers its loader in front), and loading Omeka's own
  `Laminas\Log\PsrLoggerAdapter` ended in a fatal error: "Declaration of
  Laminas\Log\PsrLoggerAdapter::log(...) must be compatible with
  Psr\Log\AbstractLogger::log(...)".

The probe was uninstalled and removed from both containers.

### B. An autoloader for the verifier's namespace only

A script (`docker compose exec ... php`, PHP 8.3.36) boots Omeka S with
ContentCredentials active, registers an `spl_autoload_register` callback
for `Provemark\C2paVerifier\` only, mapped to
`modules/ContentCredentials/vendor/provemark/c2pa-verifier/src/`, and
verifies 16 fixtures from the verifier's `tests/Fixtures/`: the signed and
unsigned JPEG, PNG and WebP, and ten damaged ones (`jpeg/`: lbox-differs,
not-a-jpeg, swapped-pieces, truncated-in-piece-2; `png/`: cabx-empty,
crc-wrong, lbox-differs; `webp/`: c2pa-empty, chunk-overruns-file,
lbox-differs).

The reference: `vendor/bin/c2pa-verify` on the host (PHP 8.5.12), and the
same command in the container (PHP 8.3.36, outside Omeka). Compared: the
`validation_state` and the SHA-256 of the whole JSON.

- All 16 identical, in Omeka and in both CLI runs: signed `Valid`, the
  other 13 `Invalid` (the unsigned files included: that is the verifier's
  verdict).
- Afterwards Omeka's `PsrLoggerAdapter` loaded, and
  `Psr\Log\LoggerInterface` came from Omeka's `vendor/`.

### C. A Strauss-prefixed build copy

`strauss.phar` 0.30.0 (SHA-256 `08c1a8e5…38c66c96`, equal to Tracefern's
pin; 0.31.1 of 2026-10-08 is the latest release) on a copy of HEAD in
`build/` after `composer install --no-dev`, with Tracefern's settings and
the prefix `ContentCredentials\Vendor\` (set in the copy only):

- The verifier moved to `vendor-prefixed/` under
  `ContentCredentials\Vendor\Provemark\C2paVerifier\`.
- Booted Omeka, required the copy's `vendor-prefixed/autoload.php`,
  verified the 16 fixtures with the prefixed class: identical to the CLI;
  the original class did not exist; `PsrLoggerAdapter` loaded.
- `vendor-prefixed/autoload.php`, like `vendor/autoload.php`, is a
  Composer autoloader with a `platform_check.php` for PHP 8.3. Required on
  the PHP 8.2 container: fatal `RuntimeException`, "Composer detected
  issues in your platform".
- In call sites (`src`), Strauss rewrote `use` lines, fully qualified
  names, and namespace strings in single and double quotes
  (`'Provemark\\C2paVerifier\\'` became
  `'ContentCredentials\\Vendor\\Provemark\\C2paVerifier\\'`), but not the
  path string `'vendor/provemark/c2pa-verifier/src/'`.

The build copies were removed and the test environment reset.

## Conclusions for the M1 spec (reasoned from the above)

- No Composer `autoload.php` of the module, development or release, may
  be loaded in Omeka S: the development one breaks Omeka's logger, and
  every one of them kills PHP 8.2 before the module can refuse (which also
  covers an installation whose PHP is downgraded after install).
- An autoloader for the verifier's one namespace, owned by the module and
  registered only when the requirements hold, gives the CLI's verdict in
  Omeka. Its namespace string follows Strauss's prefixing by itself; its
  path must point at `vendor-prefixed/` in the release, which the build
  (M7) has to arrange.
- Strauss itself is a build tool for M7 and enters with its own ADR then;
  M1 needs nothing new.

## Not measured

- A request that actually goes through Omeka's PSR logger with the whole
  vendor loaded (the fatal was forced by loading the class).
- Strauss 0.31.1.
