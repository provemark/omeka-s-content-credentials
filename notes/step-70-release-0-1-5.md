# Step 70 (M8.12): release 0.1.5

2026-11-13, at Maurice's go ("bereid omeka 0.1.5 voor"), after the verifier bump of M8.11
(`7b39512`, CI green on run 43405489315).

## What was published

- `ba50b06`, "Version 0.1.5": `config/module.ini` at 0.1.5, `CHANGELOG.md`'s "Unreleased" made
  "0.1.5 (2026-11-13)" with a summary. Pushed at Maurice's go ("push"); CI run 43411631346,
  9 of 9 green.
- The annotated tag `v0.1.5` on `ba50b06`, at Maurice's go ("tag en release v0.1.5 maar").
- The GitHub release
  [`v0.1.5`](https://github.com/provemark/omeka-s-content-credentials/releases/tag/v0.1.5), not a
  draft and not a pre-release, with one asset, `ContentCredentials-0.1.5.zip`, SHA-256
  `5d0b8151940f88b3d138d1c2495676c78dfbefb008cc561ccc08246ef90c18c4`, which the release text gives.

## The zips (measured)

The zip the upgrade below was measured with was built from `ba50b06` (`2e585e5a…`); it carries
`version = "0.1.5"` and verifier `0.11.0` (`JpegBoxMap.php` present). After the tag it was built
again from `v0.1.5` (`5d0b8151…`): the hashes differ because a build stamps the time into the zip,
the files do not (`diff -r` of the two unpacked is empty). The released zip is the second one.
`gh release download v0.1.5` and an anonymous `curl -L` of the download address at 11:00:31 UTC:
both `5d0b8151…`, the anonymous one HTTP 200.

## The upgrade from 0.1.4 (measured)

Measured in a clean Omeka S 4.2.1 on PHP 8.3. The environment was `tools/env/compose.release.yaml`
run as its own project, `content-credentials-trial`, on `127.0.0.1:8095`, with volumes removed before
and after.

1. **Install 0.1.4.** The published 0.1.4 zip, downloaded with `gh release download v0.1.4`
   (`209f6070…`, the hash the release text gives), was unpacked in `modules/` and installed
   (HTTP 302). Three uploads went through the admin item form, all with verifier `0.10.1`:

   | Media | File | Verdict | Codes | Page reads |
   |---|---|---|---|---|
   | 2 | the Pixel photo | `Trusted` | | "Verified: trusted signer" |
   | 4 | the Amazon Titan PNG | `Valid` | | "Intact: signer not trusted / AI-generated (signed)" |
   | 6 | the verifier's `box-hash/written.jpg` | `Invalid` | `["signingCredential.untrusted","general.error"]` | "Does not verify" |

2. **Upgrade.** The folder was replaced with the 0.1.5 build, and the upgrade on the modules page
   returned HTTP 302. The stored version became 0.1.5.
3. **After the upgrade.** `SELECT *` of the verdict and the evidence table was byte-identical to
   before (`cmp`), and the three media pages read the same. So entries checked under 0.10.1 keep
   their verdict and evidence, including the `Invalid` one for the box-hash file.
4. **A new upload.** `written.jpg` uploaded again became media 8: `Valid`, verifier `0.11.0`, no
   codes, and its page reads "Intact: signer not trusted". This is the change 0.1.5 ships
   (step 69). The earlier evidence rows were unchanged after it.

The module has no `upgrade()` method: no table changes between 0.1.4 and 0.1.5.

## The check (measured)

`tools/env/reset.sh`, then `composer check` on `ba50b06`, exit 0:

- spec-check (10 specs), phpstan, and deptrac (0 violations);
- unit 357, integration 152, release 24.

## Afterwards

The trial environment is down. omeka.org picks up a release that is not a draft (step 66); not
checked here.
