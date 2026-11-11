# Step 68 (M8.10): release 0.1.4

2026-11-11, at Maurice's go ("bereid release 0.1.4 voor"), after the verifier bump of M8.9
(`df9ecea`, CI green on run 43031100036).

## What was published

- `74dc364`, "Version 0.1.4": `config/module.ini` at 0.1.4, `CHANGELOG.md`'s "Unreleased" made
  "0.1.4 (2026-11-11)" with a summary. Pushed at Maurice's go ("push maar"); CI run 43039398633,
  9 of 9 green.
- The annotated tag `v0.1.4` on `74dc364`, at Maurice's go ("tag en release v0.1.4 maar").
- The GitHub release
  [`v0.1.4`](https://github.com/provemark/omeka-s-content-credentials/releases/tag/v0.1.4), not a
  draft and not a pre-release, with one asset, `ContentCredentials-0.1.4.zip`, SHA-256
  `209f60702f2fc5b9f02233ad77b2206a63b859e6dc59f5af0f5d2ad80927de28`, which the release text gives.

## The zips (measured)

The zip the upgrade below was measured with was built from `74dc364` (`16a78058…`); it carries
`version = "0.1.4"` and verifier `0.10.1` (`IconReferenceCheck.php` present). The release suite of
the check rebuilt it from the same tree (`e56922da…`), and after the tag it was built again from
`v0.1.4` (`209f6070…`): the hashes differ because a build stamps the time into the zip, the files do
not — the last two unpacked are identical (`diff -r`). The released zip is the last one.
`gh release download v0.1.4` and an anonymous `curl -L` of the download address at 14:45:21 UTC:
both `209f6070…`, the anonymous one HTTP 200.

## The upgrade from 0.1.3 (measured)

In a clean Omeka S 4.2.1 on PHP 8.3 (`tools/env/compose.release.yaml` as its own project,
`content-credentials-trial`, on `127.0.0.1:8095`, volumes removed before and after):

1. The published 0.1.3 zip, downloaded with `gh release download v0.1.3` (`e7743e85…`, the hash the
   release text gives), unpacked in `modules/` and installed (HTTP 302). Three uploads through the
   admin item form: the Pixel photo (media 2) `Trusted`, the Amazon Titan PNG (media 4) `Valid`, and
   the verifier's `icons/agents-icon.png` (media 6) `Valid`, all with verifier `0.10.0`. The media
   pages read "Verified: trusted signer", "Intact: signer not trusted / AI-generated (signed)" and
   "Intact: signer not trusted".
2. The folder replaced with the 0.1.4 build. Upgrade on the modules page: HTTP 302; the stored version
   became 0.1.4.
3. After it: `SELECT *` of the verdict and the evidence table byte-identical to before (`cmp`), and
   the three media pages read the same. So entries checked under 0.10.0 keep their verdict and
   evidence.
4. A new upload of `agents-icon.png` (media 8): `Invalid`, verifier `0.10.1`, codes
   `["signingCredential.untrusted","hashedURI.missing"]`; its page reads "Does not verify". This is
   the change 0.1.4 ships (step 67).

The module has no `upgrade()` method: no table changes between 0.1.3 and 0.1.4.

## The check (measured)

`tools/env/reset.sh`, then `composer check` on `74dc364`, exit 0: spec-check (10 specs), phpstan,
deptrac (0 violations), unit 357, integration 152, release 24.

## Afterwards

The trial environment is down. omeka.org picks up a release that is not a draft (step 66); not
checked here.
