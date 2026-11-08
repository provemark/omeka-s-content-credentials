# Step 56 (M7.26): release 0.1.1

2026-11-08, at Maurice's go ("ok, go voor release 0.1.1 en ruim de testomgeving op"), after CI was
green on `8f5b2ed` (steps 54 and 55).

## What was published

- `a57383e`, "Version 0.1.1": `config/module.ini` at 0.1.1, `CHANGELOG.md`'s "Unreleased" made
  "0.1.1 (2026-11-08)" with a summary.
- The annotated tag `v0.1.1` on `a57383e`.
- The GitHub release
  [`v0.1.1`](https://github.com/provemark/omeka-s-content-credentials/releases/tag/v0.1.1), not a
  draft and not a pre-release, with one asset, `ContentCredentials-0.1.1.zip`, SHA-256
  `51515d3ef83ae5a2c61bbee5c4de4880d4dc4730846961836c07db163b25970a`, which the release text gives.
  The text says how to upgrade from 0.1.0.

## The upgrade from 0.1.0 (measured)

The first release an installation can upgrade to, so measured before the tag, in a clean
Omeka S 4.2.1 on PHP 8.3 (the trial compose project of step 48, its volumes removed first):

1. The published 0.1.0 zip (`gh release download v0.1.0`, `9ebec7dc…`) unpacked in `modules/`; the
   module installed; the Pixel photo and the Amazon Titan PNG uploaded through the admin form.
   Stored: media 2 `Trusted`, media 4 `Valid` with `generated`; two evidence documents.
2. SHA-256 of `SELECT *` on `content_credentials_verdict` and on `content_credentials_evidence`:
   `55c19331…` and `37c07a22…`.
3. The folder replaced with a 0.1.1 build of `a57383e` (`2df8782d…`). Omeka's modules page offered
   Upgrade (`<form method="POST" action="/admin/module/upgrade?id=ContentCredentials">`, seen after
   setting the stored version back to 0.1.0, since the first check looked for the wrong string);
   the upgrade answered 302 and the stored version became 0.1.1.
4. The same two hashes after the upgrade, for the same rows: `55c19331…` and `37c07a22…`. The browse
   showed the label under the verdict. A new upload (`fixture-signed.webp`) was verified: `Valid`.

The module has no `upgrade()` method: no table changes between 0.1.0 and 0.1.1.

## The zip (measured)

`composer check` on `a57383e` (green: 349 unit, 145 integration and 23 release tests)
rebuilt the zip, so the released zip (`51515d3e…`) is not the measured one (`2df8782d…`); unpacked,
the two are identical (`diff -r`). The build is not byte-reproducible (step 49).

## Download (measured)

`gh release download v0.1.1` and an anonymous `curl -L` of the download address at 15:14:10 UTC:
both HTTP 200 and `51515d3e…`.

## Afterwards

The trial and test environments were taken down with their volumes, at Maurice's request. The
registration on omeka.org is his.
