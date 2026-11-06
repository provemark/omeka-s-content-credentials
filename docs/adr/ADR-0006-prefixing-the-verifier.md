# ADR-0006: Prefixing the verifier in the release build

| Field    | Value            |
|----------|------------------|
| Status   | proposed         |
| Date     | 2026-11-06       |
| Decided  | —                |

## Context

No dependency enters this repository without an ADR. ADR-0002 left Strauss for the
release build: "belongs to the release build (M7) and gets its amendment to this ADR
then". This ADR does that in a file of its own, because it decides more than a
line in ADR-0002's list: also what the zip holds.

Omeka S loads every active module into one PHP process. Measured with verifier 0.10.0
on Omeka S 4.2.1 (`notes/step-11-loading-the-verifier.md`,
`notes/step-28-the-release-build.md`):

- **The clash is real.** With a second module that autoloads its own unprefixed
  copy of the verifier, this module's verdict was written by the other copy, and the
  record named that copy's version (`0.0.1-other`). The module's own
  `VerifierLoader` lost silently. The prefixed zip kept `0.10.0`.
- **Strauss does the prefixing.** Under `ContentCredentials\Vendor\` the verifier
  differs from the original only in that prefix (and the `src/Cli` the build removes).
  The module's `src/` changes in its `use` lines and one namespace string, not in
  its paths.
- **The result holds.** On a clean Omeka S the zip installs, refuses PHP 8.2, agrees
  with the unprefixed verifier on 10 of 10 fixtures with byte-identical documents, and
  passes 105 of the 106 integration tests. The other one, SPEC-002 AC13, names the
  folder `vendor/`.
- **The version hardly matters here.** Strauss 0.30.0 and 0.31.1 give identical
  trees. 0.31.1's release notes name one change, on packages that `provide` or
  `replace` another (read); the verifier has no dependencies.
- **No Composer autoloader is needed, and none may load** (step 11). The zip works
  without `vendor/`, without `vendor-prefixed/autoload.php` and without the module's
  `composer.json`.

Read, not measured here: Strauss is MIT licensed (GitHub's API, 2026-11-06). In
Tracefern (its SPEC-009), a Composer-installed Strauss could not run inside the build
folder, as its bin script found the plugin's `vendor/autoload.php` first; the
release's `strauss.phar` could.

## Decision

1. **Strauss prefixes the verifier, in the release build only.** The repository,
   the development and test environments and `composer check` keep the namespace
   `Provemark\C2paVerifier`.
2. **A pinned phar, not a Composer dependency.** `strauss.phar` **0.31.1**, SHA-256
   `17dc2baf27b9a3f52834e3db729c8365d8915a80e37a087d253cf705387aa887`, fetched by the
   build from the project's GitHub release into `build/.tools/` (ignored), checked
   before use, never shipped. A different hash stops the build.
3. **The settings:** prefix `ContentCredentials\Vendor\`, target `vendor-prefixed`, the
   one package `provemark/c2pa-verifier`, call sites `src`, in `extra.strauss` of
   `composer.json`, as Strauss documents and Tracefern does.
4. **What the zip holds,** under the top directory `ContentCredentials/`: `Module.php`,
   `LICENSE`, `README.md`, `CHANGELOG.md`, `config/`, `src/`, `trust/`, `view/`, and
   `vendor-prefixed/provemark/c2pa-verifier/` with only `src/` (without `Cli/`),
   `LICENSE` and `composer.json`. So there is no `vendor/`, no Composer autoloader and no
   `composer.json` or `composer.lock` of the module. The verifier's `LICENSE` stays, as
   its MIT licence requires.
5. **The update.** A newer Strauss is a change to the pin in one commit, with the
   trees compared and the tests on the built zip run. A new verifier needs nothing
   here beyond those tests.

How the module finds the folder, and how the build is tested, are SPEC-008's (M7.4).

## Alternatives

- **No prefix.** Refused by the measurement above: another module could decide
  which verifier writes this module's record.
- **Only the module's loader, registered first.** Modules load in an order this
  module does not choose, and a class already loaded cannot be replaced. Reasoned, not
  measured, apart from the case above, where the loader lost.
- **Strauss in `require-dev`.** It would bring its dependencies into
  `composer check`'s tree and `composer.lock`. Together with Tracefern's finding on its
  bin script, this has nothing to offer over a phar with a hash.
- **Strauss 0.30.0, as Tracefern.** The same tree. Taking the newer one is the
  smaller debt for a release; nothing here depends on the choice.
- **PHP-Scoper.** Not measured. Strauss is measured here and known from the sister
  project; there is no shortcoming that would justify measuring another.
- **Rewriting the namespace with `sed` in the build.** Nothing measured asks for a tool of
  our own, and it would have to handle every form Strauss already handles.
- **Keeping `vendor/` or the autoloaders in the zip.** Nothing loads them, and step 11
  measured that loading one kills PHP 8.2. M7's row says "prefixed `vendor/`"; this
  folder is what it comes to.

## Consequences

- **The build needs the network once,** to fetch the phar; the module still needs
  none. The hash limits what that download can bring in.
- **`composer.lock` changes,** as `extra` counts for its content hash (read); it is
  refreshed in the commit that adds the settings.
- **The static checks do not see the zip.** PHPStan and Deptrac read the unprefixed
  source, so only a test on the built zip can catch what the build breaks. A forgotten
  path shows only at an upload (step 28).
- **Class names in a stack trace or log carry the prefix.** Reasoned; no document or verdict
  holds them (the documents were byte-identical).
- **A build on Linux or on another PHP than 8.5.12 is not measured yet**, nor the zip on
  PHP 8.4 and 8.5. M7.5 has to, if CI builds the zip.
- **SPEC-002 AC13 needs an amendment,** as it names `vendor/`. SPEC-008 proposes it.
