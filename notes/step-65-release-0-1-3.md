# Step 65 (M8.6): release 0.1.3

2026-11-08, at Maurice's go ("ok, doe de README-alinea en daarna release 0.1.3"), after the README
section of M8.5 (`d60bd74`).

## What was published

- `2bcb83b`, "Version 0.1.3": `config/module.ini` at 0.1.3, `CHANGELOG.md`'s "Unreleased" made
  "0.1.3 (2026-11-08)" with a summary.
- The annotated tag `v0.1.3` on `2bcb83b`, after `composer check` and CI were green on it.
- The GitHub release
  [`v0.1.3`](https://github.com/provemark/omeka-s-content-credentials/releases/tag/v0.1.3), not a
  draft and not a pre-release, with one asset, `ContentCredentials-0.1.3.zip`, SHA-256
  `e7743e85018e30f91058a91dfb30fd2f76732f6a3945a966bc7986ed1ac01935`, which the release text gives.

## The upgrade from 0.1.2 (measured)

In a clean Omeka S 4.2.1 on PHP 8.3 (the trial compose project of step 48, its volumes removed
first):

1. The published 0.1.2 zip (`662f093f…`) installed; the Pixel photo and the Amazon Titan PNG uploaded
   through the admin form: media 2 `Trusted`, media 4 `Valid`. Hashes of the `mariadb -N` output of
   `SELECT *` on the verdict and evidence tables: `a7f7e012…` and `4f057240…`. The module's parts of
   the two media pages, the browse column and the configure page saved as one file (3209 bytes,
   `234445bc…`).
2. The folder replaced with the 0.1.3 build of `2bcb83b` (`78db5da6…`). The modules page offered
   Upgrade (`<form method="POST" action="/admin/module/upgrade?id=ContentCredentials">`); the upgrade
   answered 302 and the stored version became 0.1.3.
3. After it: the same two table hashes, and the same 3209 bytes of the module's English pages
   (`cmp`: identical). A new upload (`fixture-signed.webp`) was verified: `Valid`.
4. A pseudo translation (SPEC-009's, made from the zip's `template.pot`) copied into the unpacked
   zip's `language/nl_NL.mo`, with locale `nl_NL`: the browse cells read "⟦Verified: trusted signer⟧"
   and "⟦Intact: signer not trusted⟧ / ⟦AI-generated (signed)⟧". So a `.mo` an administrator adds
   works on the released layout, not only in the test environment.

The module has no `upgrade()` method: no table changes between 0.1.2 and 0.1.3.

## The first check was red

The first `composer check` on `2bcb83b` failed: 136 of 152 integration tests, every one that needs
the module ("Failed asserting that null is identical to 'Valid'"). The test installation's database
still held the module at 0.1.2 (`SELECT id, version FROM module`: `ContentCredentials 0.1.2`),
because `tools/env/down.sh` keeps it, and the working tree said 0.1.3: Omeka S then waits for an
upgrade and does not load the module. Not a fault of the code. After `tools/env/reset.sh` (a fresh
database) the check was green: 357 unit, 152 integration and 24 release tests. In 0.1.1 and 0.1.2
the environment had been removed with its volumes before the version changed, which is why it did not
show then.

The version commit was pushed before the second check, by a command that pushed regardless of the
first check's result. It reached no tag or release before both the second check and CI were green.

## The zip and the download (measured)

The released zip was built again from `2bcb83b` after CI (`e7743e85…`); unpacked, it is identical to
the measured one (`diff -r`). `gh release download v0.1.3` and an anonymous `curl -L` of the download
address at 19:36:17 UTC: both HTTP 200 and `e7743e85…`.

## Afterwards

The trial and test environments are down. The registration on omeka.org is Maurice's.
