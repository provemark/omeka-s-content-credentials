# Step 28 (M7.1): the release build, measured on a copy

2026-11-05. Step 11 prefixed the verifier once, on a copy of M1. This step
builds a candidate zip of the module as it is now, and asks whether it installs
on a clean Omeka S and behaves as the development layout does. Nothing in the
module changed; the build is a probe.

## Setup (measured)

- `tools/probes/BuildProbe/build.sh <strauss-version> <label>`, never shipped, on
  HEAD `c8dc019`, host PHP 8.5.12, Composer 2.10.2. As Tracefern's `tools/build.sh`:
  1. `git archive HEAD` of `Module.php LICENSE README.md CHANGELOG.md composer.json
     composer.lock config src trust view`;
  2. `composer install --no-dev`, the verifier's `src/Cli` removed;
  3. Strauss, with prefix `ContentCredentials\Vendor\`, target `vendor-prefixed`,
     call sites `src`;
  4. of the verifier only `src/`, `LICENSE` and `composer.json` kept; `vendor/`,
     Composer's autoload files, and the module's `composer.json` and `.lock`
     removed;
  5. the path in `TrustConfig`, `RecheckJob` and `AfterFlushListener` changed with
     `sed` to `vendor-prefixed/…`;
  6. `zip -qr -X`, with `ContentCredentials/` as the top directory.
- `tools/probes/BuildProbe/compose.yaml`: its own project
  (`content-credentials-zip`), volumes and ports 8090 and 8091; the pinned images
  of Omeka S 4.2.1 on PHP 8.3.36 and 8.2.35, MariaDB 11.4; **no module mounted**.
  The zip was copied in and unpacked in `modules/`, as an administrator does.
- `tools/probes/BuildProbe/scripts/measure.php`: `refuse`, `install`, `fixtures`,
  `rogue`, `one`, with the test suite's `OmekaInstallation`, pointed at this
  environment through `COMPOSE_FILE` and `CC_TEST_PORT`.
- `tools/probes/BuildProbe/RogueVerifier/`: a second module with its own unprefixed
  copy of the verifier, `VERSION` changed to `0.0.1-other`, on its own autoloader.

## Measured

### The build

| case | result |
|---|---|
| Strauss 0.30.0 (SHA-256 `08c1a8e5…38c66c96`) against 0.31.1 (`17dc2baf…7aa887`) | `diff -r` of the two trees empty; the zips' CRCs equal; the zips' own hashes differ (not reproducible byte for byte; timestamps, reasoned) |
| the zip | 388 640 bytes; 162 files; 1 400 KB unpacked, of which the verifier 1 076 KB |
| every file mode | `-rw-r--r--`, directories `drwxr-xr-x` |
| files in the zip matching `.key`, `CLAUDE`, `.git`, `tests/`, `notes/`, `specs/`, `tools/`, `Cli/`, `.DS_Store` | none |
| the verifier, prefix undone with `sed`, against the original | `diff -r`: only `src/Cli` is gone. `LICENSE` identical. Strauss added nothing else, no header either |
| the module's `src/` against the original | only `use` lines, the one namespace string in `VerifierLoader`, and the three paths of step 5 |
| a search in the module and prefixed verifier for `exec`, `shell_exec`, `proc_open`, `popen`, `passthru`, `system`, `curl_`, `fsockopen`, `stream_socket`, `tempnam`, `tmpfile` | nothing |

### The zip on a clean Omeka S

Without anything added to the module's folder:

| case | result |
|---|---|
| install on PHP 8.2 | refused: "Content Credentials (C2PA) needs PHP 8.3 or later; this server runs PHP 8.2.35."; no module row; `snapshot()` unchanged; admin 200 |
| install on PHP 8.3 | 302, active at 0.1.0; both tables; configure page 200 |
| 10 fixtures (every JPEG, PNG, WebP) uploaded through the admin form, against the unprefixed verifier on the host | 10 of 10 agree: 1 `Trusted`, 6 `Valid`, 3 `none` where the CLI says `Invalid` (a file without a manifest, SPEC-002). All 7 stored documents are byte-identical to `evidenceToJson()`, the clock-judged ones compared as SPEC-006's test does. Verifier column `0.10.0` |

**The integration suite against the zip.** `pest --testsuite=Integration` from the
host, with `tests/Fixtures` and `tests/Integration/Support` copied into the unpacked
module, because the fixture server and AC13's helper run from there; everything else
is the zip (after the run, the SHA-256 of every shipped file in the container still
matched the build):

```
Tests:    1 failed, 105 passed (816 assertions)
Duration: 178.10s
```

The failure is SPEC-002 AC13, "only the verifier's own source is loaded from
vendor/". It looks in `vendor/`, where the zip has none: `verifier_files` is 0. The
same helper with `vendor-prefixed/` gives 77 verifier files, nothing else from that
folder, `LoggerInterface` from Omeka's `vendor/`, state `Valid`. So the test needs a
change for a zip; the module does not.

### Next to another copy of the verifier

The same upload with `RogueVerifier` active:

| layout | verdict | verifier column |
|---|---|---|
| development (control, port 8088), unprefixed | `Valid` | **`0.0.1-other`** |
| the zip | `Valid` | `0.10.0` |

Without a prefix the module silently uses the other module's copy, and writes that
version in its record. With the prefix it does not. In both, `RogueVerifier`'s module
row came and went with install and uninstall.

### A build that forgets the path

The zip with the three paths put back to `vendor/`: the upload 302, state `error`,
reason `exception`, verifier null, no evidence row, media and configure pages 200.
Restored: `Valid`, `0.10.0`, one evidence row. So it fails closed, but on every
upload. The configure page still answers 200; what it says in this state was not
read, and installing in this state was not tried.

## The assistant's own errors in this step

- The first `RogueVerifier` run gave `0.10.0` in both layouts: the `sed` for the
  version looked for `const VERSION`, and the verifier has `const string VERSION`.
  That run said nothing. The control showed it; the table above is the second run.
- The first `fixtures` run showed one document as different, and "6 of 10": the
  probe looked for `time_source` `current_time` (it is `clock`), and did not count
  `none` for the unsigned files. Corrected to SPEC-006's own comparison.
- It also compared the `codes` column with the CLI's codes for `Valid` files, where
  the column is empty by SPEC-002 (it holds `Invalid`'s failures). That column of
  the probe was removed; the suite's AC4 compares the codes on an altered file.

## What follows (reasoned)

- **The zip works.** M2 to M6 hold under the prefix, except for the one test that
  names the folder. Nothing measured asks for a change to the verifier's use.
- **The prefix earns its place.** The control shows a real clash, not a theoretical
  one, and one that would falsify the record.
- **No `vendor/` in the zip at all.** M7's row says "prefixed `vendor/`"; the
  module loads the classes with its own loader, so `vendor-prefixed/` with
  three kept items per package is enough. Omeka installed it without
  `composer.json`. Whether omeka.org minds is for M7.6.
- **The path is written in three places, and forgetting it only shows at an upload.**
  One place in the source would mean one rewrite. A test that uploads to the built
  zip catches it.
- **The suite can run against the zip.** In a clean environment it took 178 s (429 s
  in the last run on port 8088; the difference was not investigated), which makes it
  feasible as a CI job. It needs the fixtures and helper beside the module, and AC13
  needs to know the folder.
- **Strauss version.** Both give the same tree, so the choice has no effect here;
  0.31.1 is the newer one, Tracefern pins 0.30.0. For the ADR (M7.3).

## Not measured

- The zip on PHP 8.4 and 8.5: there are no images for it here, and CI's integration
  job runs on 8.3 and 8.2.
- A build on Linux, or on another PHP than 8.5.12. `sed -i ''` in the probe is macOS only.
- A second copy of the verifier that gets loaded by Composer's autoloader, or one
  with a different API. `RogueVerifier` differs in its version only.
- Installing by other routes than unpacking in `modules/`, a web server other than
  Apache, Omeka S in a subdirectory.
- What an administrator sees: no page of the zip was looked at in a browser.
- Uninstalling the zip beyond what the suite's install tests do: that is M7.2.

Cleanup: the probe's project with its volumes taken down, `build/probe/` removed,
`RogueVerifier` removed from both installations, its two items on port 8088 deleted.
`build/.tools/strauss-0.31.1.phar` stays (ignored).
