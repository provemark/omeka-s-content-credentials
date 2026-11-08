# Step 49 (M7.19): release 0.1.0

2026-11-08, at Maurice's go ("ok, go voor de tag en de release"), after CI was green on `8957f6b`.

## What was published

- The annotated tag `v0.1.0` on `8957f6b`.
- The GitHub release
  [`v0.1.0`](https://github.com/provemark/omeka-s-content-credentials/releases/tag/v0.1.0), not a
  draft and not a pre-release, with one asset, `ContentCredentials-0.1.0.zip`, SHA-256
  `9ebec7dc8085883df9e7178901f49aecec6aa4751f90df5a9a4282e2b24f03ff`, which the release text gives.

## The zip (measured)

Built by `tools/build.sh` from `8957f6b`. Unpacked, it differs from the zip of Maurice's trial
(step 48, `17c50aa`) in one line, `CHANGELOG.md`'s "not yet released" now "2026-11-08"
(`diff -r`). Two builds of the same commit gave different bytes (`4f8f…` and `9ebe…`), probably
the timestamps inside the zip; the build is not byte-reproducible, so the hash belongs to this zip,
not to the commit. Making it reproducible is left for later.

## Download (measured)

- `gh release download v0.1.0`: SHA-256 `9ebec7dc…`, equal to the uploaded file.
- Without logging in (`curl -L` of the release's download address): HTTP 504 from GitHub
  (`x-github-request-id` present) at 09:46, 09:47:03, :23 and :43; 200 with `9ebec7dc…` at 09:48:04.
  GitHub's status page said "All Systems Operational". So for about two minutes after the release
  was made, the asset could not be fetched anonymously; why, is not known.

## What M7 still holds

The registration on omeka.org, which is Maurice's decision.
