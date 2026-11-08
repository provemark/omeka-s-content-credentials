# Step 62 (M7.32): release 0.1.2

2026-11-08, at Maurice's go ("ok, go voor release 0.1.2"), after CI was green on `2bb56cd`
(steps 59 to 61).

## What was published

- `88ac321`, "Version 0.1.2": `config/module.ini` at 0.1.2, `CHANGELOG.md`'s "Unreleased" made
  "0.1.2 (2026-11-08)" with a summary.
- The annotated tag `v0.1.2` on `88ac321`, after CI was green on that commit (all nine jobs).
- The GitHub release
  [`v0.1.2`](https://github.com/provemark/omeka-s-content-credentials/releases/tag/v0.1.2), not a
  draft and not a pre-release, with one asset, `ContentCredentials-0.1.2.zip`, SHA-256
  `662f093fc91242ce4de3d64f318619c343998be188e532b2da0b2fd47dfe3e15`, which the release text gives.
  The text names the fix (SPEC-006 amendment 1, step 59) and says how to upgrade from 0.1.1 or 0.1.0.

## The upgrade from 0.1.1 (measured)

In a clean Omeka S 4.2.1 on PHP 8.3 (the trial compose project of step 48, its volumes removed
first):

1. The published 0.1.1 zip (`gh release download v0.1.1`, `51515d3e…`) unpacked in `modules/`; the
   module installed; the Pixel photo and the Amazon Titan PNG uploaded through the admin form.
   Stored: media 2 `Trusted`, media 4 `Valid` with `generated`; two evidence documents.
2. SHA-256 of the `mariadb -N` output of `SELECT *` on `content_credentials_verdict` and on
   `content_credentials_evidence`: `f197b0be…` and `6f695251…`. The ACL probe of step 59 (Omeka
   bootstrapped, a role `guest` added, `isAllowed(…, 'show')` on the evidence controller):
   `guest` true, as in 0.1.1.
3. The folder replaced with the 0.1.2 build of `88ac321` (`fc48bda7…`). The upgrade answered 302
   and the stored version became 0.1.2. Omeka's modules page offers Upgrade for it
   (`<form method="POST" action="/admin/module/upgrade?id=ContentCredentials">`); as in step 56, the
   script's own check looked for the wrong string, so this was seen by setting the stored version
   back to 0.1.1 after the upgrade, and set to 0.1.2 again.
4. The same two hashes after the upgrade, for the same rows (media 2 and 4): `f197b0be…` and
   `6f695251…`. A new upload (`fixture-signed.webp`) was verified: `Valid`. The ACL probe: `guest`
   false, the six Omeka roles true.

The module has no `upgrade()` method: no table changes between 0.1.1 and 0.1.2.

## The zip (measured)

`composer check` on `88ac321`, green: 349 unit, 146 integration and 23 release tests. The released
zip was built again from `88ac321` after CI (`662f093f…`); unpacked, it is identical to the measured
one (`diff -r`). The build is not byte-reproducible (step 49).

## Download (measured)

`gh release download v0.1.2` and an anonymous `curl -L` of the download address at 18:02:47 UTC:
both HTTP 200 and `662f093f…`.

## Afterwards

The trial and test environments were taken down with their volumes. The registration on omeka.org
is Maurice's.
