# Step 41 (M7.14, finding 6): a bundled list that is not the shipped file

2026-11-07. Finding 6 of the review (step 35): a bundled list with invalid UTF-8 made
`TrustChoice::build()` throw a `JsonException`, which it did not catch. Measuring it added a worse
case: an empty list was accepted, and the verdict named "the C2PA trust list of 2026-11-04". SPEC-004
amendment 5 adds AC13, approved by Maurice with both choices: each file in use is compared with its
hash in `BundledLists::FILES`, and the warning names the bundled lists when no custom settings are
saved.

## What was measured before building

`TrustChoice::build(null, true, …)` on a copy of `trust/` with one file damaged, on PHP 8.3:

| content | result |
|---|---|
| invalid UTF-8 | `JsonException` |
| other text, or a truncated PEM | none, with the verifier's reason |
| empty | `c2pa-2026-11-04+digicert`, settings built, no problem |

In Omeka S 4.2.1, with the C2PA list damaged in the working tree: empty gave an upload stored with
`trust` `c2pa-2026-11-04+digicert`; invalid UTF-8 gave the configure page **500** (the review had
read this, not measured it). An upload went ahead in both, as `MediaCheck` catches everything.

## What was built

- `TrustChoice::bundledJson()` compares each file it reads with its hash (`hash_equals`), and
  throws "a bundled trust list is not the file this release ships (NAME)". Only the files in use are
  read: the DigiCert root only with the option on, none with custom settings.
- `TrustChoice::build()` also catches any `Throwable` from building the bundled JSON.
- `SettingsPage::html()` takes `customSaved`; `TrustConfig::form()` passes whether custom settings are
  saved, not the text given back after a refusal. Without them the warning reads "The bundled trust
  lists cannot be used; new uploads and runs are checked without a trust list."

## Tests seen red (measured)

```
vendor/bin/pest tests/Unit/TrustSettingsTest.php --filter=AC13
Tests:    13 failed, 2 passed (25 assertions)
vendor/bin/pest --testsuite=Integration --filter='AC13: a bundled list'
Tests:    2 failed
```

The 12 damaged copies (four kinds, three files) and the page test failed; the two that passed hold
what must not change (an untouched copy builds; a file not in use is not compared). Integration: empty
stored `c2pa-2026-11-04+digicert`, invalid UTF-8 answered 500.

**The bind mount answers late.** The first integration run showed `c2pa-2026-11-04+digicert` for the
invalid UTF-8 too. A probe printed both hashes: the host held the new content, the container still the
previous write, made a second earlier. The test now waits until `sha256sum` in the container matches
(`spec004SeenInContainer()`), after damaging and after putting back. A single write was seen within
0.03 s (measured); a quick second one was not.

## Falsified (measured)

Each a temporary change, restored after its run, on `tests/Unit/TrustSettingsTest.php` (54 tests) and
the integration filter `AC13: a bundled list|AC6: settings that cannot` (3 tests).

The same lag hit the first run of this table: a change that alters nothing failed an integration test,
because Apache still ran the previous change. The table is from a second run that waits after every
write until the container sees the file, and then 3 s for the opcache (`revalidate_freq` 2 s, as the
AC8 test of SPEC-005 does).

| change | unit | integration |
|---|---|---|
| no hash check | 12 | 2 |
| only the C2PA list compared | 8 | 0 |
| another hash function | 16 | 0 |
| every file compared, also those not in use | 1 | 0 |
| the reason without the file's name | 12 | 2 |
| always the custom warning | 1 | 2 |
| always the bundled warning | 2 | 1 |
| `customSaved` always true | 0 | 2 |
| `customSaved` from the text given back | 0 | **0** |
| the widened catch removed | **0** | **0** |

- **The text given back** survived, so a test was added: a damaged list and refused custom text. It
  passes on the built code and fails with this change (`1 failed`), measured with the same wait.
- **The widened catch** cannot be reached while every file matches its hash: invalid UTF-8 no longer
  gets to `json_encode()`. It stays as the class's promise that settings never throw (as decided with
  the amendment's proposal).

## Earlier falsifications, run again with the wait (measured)

Steps 39 and 40 changed `Module.php` and `TrustConfig.php` and ran integration tests at once, without
this wait, so a result could have come from the change before. At Maurice's request both were run
again with it, on the code as it stands after this step:

- **Step 39**, the same eleven changes on the same 12 tests: every one fails at least one test, as
  before. Two counts differ: the choice saved before the refusal 11 (was 9), the choice never saved 6
  (was 3).
- **Step 40**, all ten changes on the 11 faster tests plus the AC8 test (12): every one fails at least
  one test, as before. The two changes that first ran on the slower 19 tests now ran on these.

So no conclusion of steps 39 and 40 changes. The counts that differ show the lag did touch the first
runs.

## CI: the test damaged a list the zip did not read (measured)

The first push of this step failed in CI, in the three jobs that run the integration tests on the
release zip (PHP 8.3, 8.4 and 8.5): "The container does not see C2PA-TRUST-LIST.pem as written." There
the module is unzipped in the container, not mounted from the working tree, so the test damaged a file
the module does not read. The wait made it fail instead of passing on an untouched list.

The test now writes where the installed module reads: in the container when the file there is
writable (the zip), else in the working tree (mounted read-only). `sh tools/env/release.sh
--integration --php 8.3`: 144 passed (1118 assertions); in the development environment the AC13 tests
pass too.

## Not measured

- The page in a browser, by the assistant.
