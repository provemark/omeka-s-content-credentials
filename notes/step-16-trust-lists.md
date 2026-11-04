# Step 16 (M3.2): the verifier with the bundled trust lists

2026-11-04. M3 bundles the C2PA trust lists and the DigiCert root
(`NOTES.md` §4). Before a spec rests on them: whether the copy is current
and unchanged, which verdicts they give on files a test can use, whether
the library inside Omeka gives the CLI's verdict with the same settings,
what building the settings costs per upload, and which verdicts change
with time.

Everything here uses provemark/c2pa-verifier v0.10.0 (the copy in
`vendor/`). The scripts are in `tools/probes/TrustSettings/`, and the
full result of the main run is `result-2026-11-04.txt` there.

## The copy of the lists

- Tracefern's `trust/` (copied from `c2pa-org/conformance-public`
  commit `47009ee`, 2026-10-17) was compared with upstream. I fetched
  `trust-list/C2PA-TRUST-LIST.pem` and `C2PA-TSA-TRUST-LIST.pem` at
  `47009ee` and at the newest commit touching `trust-list/`, `d6dec63`
  (2026-10-26, "Automated Sync: Update trust lists"), with
  `gh api …/contents/…?ref=`. All three copies have the same SHA-256:
  `b3949271…421af8ae` (32 certificates) and `7182cb22…c2cd2c675`
  (23 certificates). `d6dec63` changed other files in that directory,
  not these two.
- `DigiCertTrustedRootG4.crt.pem` from Tracefern: `openssl x509
  -fingerprint -sha256` gives `55:2F:7B:DC:…:C8:99:88`, the fingerprint
  the verifier's `docs/trust-settings.md` publishes; valid until
  2038-01-15.

## Library against CLI, four settings, thirteen files

The settings are built with the verifier's recipe (`docs/trust-settings.md`):
- `c2pa`: the two C2PA lists as separate `manifest` and `tsa` entries;
- `c2pa+digicert`: the same, plus the DigiCert root as `tsa`;
- `custom-test-root`: only `c2pa-rs-test-trust-anchors.pem`, the public
  test roots our `fixture-signed.*` chain ends in;
- no settings at all.

For each file and each setting, `Verifier::verify()` (macOS host,
PHP 8.4) was compared with `vendor/bin/c2pa-verify [--settings]`. **All
52 pairs gave the same state and the same status codes.** The states, on
2026-11-04:

| file (licence) | none | c2pa | c2pa+digicert | custom-test-root |
|---|---|---|---|---|
| our `fixture-signed.jpg` / `.png` / `.webp` (MIT) | Valid | Valid | Valid | **Trusted** |
| our `fixture-unsigned.jpg` (MIT) | none | none | none | none |
| `google-20250919-pixel10-npld-picnic-table.jpg` (public domain, 5.8 MB) | Invalid (expired, untrusted) | **Trusted** | **Trusted** | Invalid |
| `openai-20260826-c2pa_2x.png` (MIT, 2.2 MB) | Valid | **Trusted** | **Trusted** | Valid |
| `amazon-20240925-titan-g1.png` (Apache-2.0, 1.9 MB) | Invalid | Invalid | **Valid** | Invalid |
| `adobe-20260425-lightroom-classic-church.jpg` (CC BY-SA 4.0, 1.1 MB) | Invalid | Invalid | **Valid** | Invalid |
| `c2pa-rs-ocsp.jpg` (Apache-2.0 OR MIT, 286 KB) | Invalid | Invalid | **Valid** | Invalid |
| `adobe-20260304-photoshop-remote-manifest.jpg` (MIT, 479 KB) | none | none | none | none |
| `truepic-20230212-camera.jpg` (CC BY-SA 4.0) | Invalid (also `assertion.dataHash.mismatch`) | Invalid | Invalid | Invalid |
| `c2pa-rs-no_alg.jpg` (Apache-2.0 OR MIT) | Invalid | Invalid | Invalid | Invalid |
| `c2pa-verifier-ai-parent-chain.png` (MIT) | Valid | Valid | Valid | Valid |

The files and licences are those listed in Tracefern's
`tests/Fixtures/README.md`; none has been copied into this repository yet.

What the table shows:
- `Trusted` is reached by default (C2PA lists) on the Pixel 10 photo and
  the OpenAI image, and with custom settings on our own fixtures.
- **DigiCert decides** for three files (Amazon Titan, Lightroom,
  `c2pa-rs-ocsp`): `Valid` with the DigiCert root, `Invalid` without it.
  Their signers have expired, and a DigiCert timestamp vouches for when
  they signed.
- Custom settings replace the lists: with only the test root, the Pixel
  10 photo is no longer `Trusted`.

## Inside Omeka

In the PHP 8.3 container (PHP 8.3.36), with only the module's
`VerifierLoader` (no Composer autoloader), `tools/probes/TrustSettings/in-omeka.php`
gave:
- the same states: Pixel 10 `Trusted` with both setting files, Amazon
  Titan `Valid` with DigiCert and `Invalid` without it;
- the Photoshop file `none` with its `remote_manifest`
  (`https://cai-manifests.adobe.com/manifests/urn-c2pa-…-adobe`), never
  fetched.

Apart from PHP's own `Random\*` classes, no class outside the verifier's
namespace was declared, and peak memory was 6.0 MB.

## Cost per upload

| what | host (PHP 8.4) | container (PHP 8.3) |
|---|---|---|
| `TrustSettings::fromJson()`, `c2pa+digicert` (73 692 bytes) | 12.8 ms (mean of 50) | 14.6 ms (once) |
| the same, `c2pa` only | — | 9.4 ms (once) |
| `fromJson()`, custom test root (3 331 bytes) | 0.5 ms | — |
| `verify()` of the Pixel 10 photo (5.8 MB) with settings | 18.6 ms | 52.5 ms (first verify in the process) |

So building the settings on every upload costs about 15 ms. That is
smaller than verifying a large photo, and it needs no cache.

## Verdicts change with time

`verify()` takes a validation time (`--at` in the CLI). With the default
settings, each file was checked at several dates:

| file | 2026-11-04 | 2027-06-01 | 2028-01-01 | 2030-01-01 | 2035-01-01 |
|---|---|---|---|---|---|
| Pixel 10 (`c2pa`) | Trusted | Trusted | Trusted | Trusted | Trusted |
| OpenAI (`c2pa`) | Trusted | **Invalid** | Invalid | Invalid | Invalid |
| Amazon Titan (`c2pa+digicert`) | Valid | Valid | Valid | Valid | Valid |
| Lightroom (`c2pa+digicert`) | Valid | Valid | Valid | Valid | Valid |
| `c2pa-rs-ocsp` (`c2pa+digicert`) | Valid | Valid | Valid | Valid | Valid |

A binary search puts the OpenAI image at `Trusted` until
2027-04-23T15:51:04Z, and `Invalid` (`signingCredential.expired`) from
the next second: its signer expires and the file has no timestamp. An
upload in Omeka is checked at the current time, so an integration test
built on the OpenAI image would break in April 2027.

## What follows for M3 (reasoned)

- The bundled lists can be the copy Tracefern ships, which is unchanged
  upstream as of `d6dec63`. Which date the settings page shows (the
  commit that last changed the files, or the day the copy was last
  compared) is for ADR-0004.
- Fixtures for the M3 tests, the smallest that do the job and stay
  stable:
  - Pixel 10 for `Trusted` by default (public domain, but 5.8 MB);
  - `c2pa-rs-ocsp.jpg` for the DigiCert option (286 KB, MIT/Apache,
    instead of Amazon Titan's 1.9 MB);
  - `c2pa-rs-test-trust-anchors.pem` for custom settings (our
    `fixture-signed.*` become `Trusted`);
  - the Photoshop file for a real remote manifest URL.
  
  Not the OpenAI image, whose verdict changes in April 2027, and not the
  CC BY-SA files, which are not needed.
- Building the settings on each upload (about 15 ms) is fine; no cache.

## Not measured

- The verifier's limit on the number of certificates
  (`TrustSettings::DEFAULT_MAX_CERTIFICATES`) against a large custom file.
- WebP files from real writers (none among the candidates).
- `verify()` timing beyond the one large file.
