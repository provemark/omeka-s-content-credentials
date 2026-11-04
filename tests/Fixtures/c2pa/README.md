# C2PA fixtures (SPEC-002)

Copied unchanged from `provemark/c2pa-verifier` at tag v0.10.0 (commit
`4bcd6ae`), `tests/Fixtures/`; each file's SHA-256 was compared with
`git show v0.10.0:tests/Fixtures/<file>` on 2026-11-04. That package is MIT
licensed (Copyright (c) 2026 Maurice van Loon). Its fixtures README says the
signed assets are signed with the public c2pa-rs ES256 test certificates
(`contentauth/c2patool` `sample/`); no private key is involved here.

| file | from | used for |
|---|---|---|
| `fixture-signed.jpg`, `.png`, `.webp` | `tests/Fixtures/` | a manifest that verifies (`Valid` without trust settings) |
| `fixture-unsigned.jpg`, `.png`, `.webp` | `tests/Fixtures/` | no manifest |
| `auth-1-1.gif` | `tests/Fixtures/gif/` | a format this module leaves alone (SPEC-002 AC6) |

The altered JPEG (AC4) and the PDF (AC6) are made by the tests, not kept
here. The verdicts are never copied: the tests run `vendor/bin/c2pa-verify`
on the same bytes.
