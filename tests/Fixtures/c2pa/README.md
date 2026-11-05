# C2PA fixtures (SPEC-002, SPEC-004, SPEC-007)

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

## Added for SPEC-004 (trust settings)

Copied unchanged from `provemark/tracefern-image-check` `tests/Fixtures/`
(last changed there in commit `e81ce12`, 2026-09-26; checkout at `adcfd3c`);
SHA-256 compared with that copy on 2026-11-04. Tracefern's fixtures README
gives their origin; the short version:

| file | from | licence | used for |
|---|---|---|---|
| `google-20250919-pixel10-npld-picnic-table.jpg` | Wikimedia Commons, `File:NPLD 25 WW new picnic table (55032713763).jpg`, uploaded by the US Bureau of Land Management | public domain (US federal work) | `Trusted` with the bundled C2PA lists (SPEC-004 AC2, AC4) |
| `c2pa-rs-ocsp.jpg` | `contentauth/c2pa-rs` `sdk/tests/fixtures/ocsp.jpg` (58eac79) | Apache-2.0 OR MIT, © Adobe and the c2pa-rs contributors; see `LICENSE-c2pa-rs-APACHE` and `LICENSE-c2pa-rs-MIT` | an expired signer with a DigiCert timestamp: `Valid` with the DigiCert root, `Invalid` without (AC3) |
| `c2pa-rs-test-trust-anchors.pem` | the verifier's `trust/trust_anchors.pem`, byte-identical to `contentauth/c2patool` `sample/` | Apache-2.0 OR MIT (c2pa-rs / c2patool) | custom settings under which `fixture-signed.*` is `Trusted` (AC4); public certificates, no key |

Their verdicts are stable from 2026-11-04 to at least 2035-01-01
(`notes/step-16-trust-lists.md`, measured).

## Added for SPEC-007 (the AI label)

Copied unchanged from `provemark/tracefern-image-check` `tests/Fixtures/`
(checkout at `adcfd3c`); SHA-256 compared on 2026-11-05 with that copy's origin,
the verifier's `writers/amazon-20240925-titan-g1.png` and
`ai-history/parent-chain.png`.

| file | from | licence | used for |
|---|---|---|---|
| `amazon-20240925-titan-g1.png` | `TrustNXT/c2pa-ts`, `tests/fixtures/amazon-titan-g1.png` (14f8ad7), via the verifier's `writers/` | Apache-2.0, see `LICENSE-TrustNXT-c2pa-ts` | says `trainedAlgorithmicMedia` in the active manifest: `Valid` with DigiCert, `Invalid` without |
| `c2pa-verifier-ai-parent-chain.png` | the verifier's `ai-history/parent-chain.png` (its step 178, commit `0cf13e4`), renamed: signed with a throw-away key that was deleted | MIT (the verifier's) | says `trainedAlgorithmicMedia` only in a `parentOf` manifest: `Valid` |

Under the default settings, Titan holds to at least 2045 and parent-chain until
2036-09-25 (`notes/step-26-ai-statement.md`, measured). The OpenAI image is left
out on purpose: it turns `Invalid` on 2027-04-23.
