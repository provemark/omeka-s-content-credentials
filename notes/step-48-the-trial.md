# Step 48 (M7.18): Maurice's trial of the zip

2026-11-08. Before the release, Maurice installed the zip himself and used it in a browser: the one
check no test replaces (step 31, "Not done").

## The environment

A clean Omeka S 4.2.1 on PHP 8.3.36 with MariaDB 11.4, from the same pinned images as
`tools/env/compose.release.yaml`, as a separate Docker project on `127.0.0.1:8095` that was kept
running. The zip was `ContentCredentials-0.1.0.zip` built from `17c50aa`, SHA-256
`8d6834814874dd8166e5e2767a4e7206e28baaddd7fffbf70abd447db383cf7b`, unpacked in `modules/` by the
assistant. Installing it, and everything after, Maurice did in the admin interface.

Before it, `composer check` ran green on `17c50aa`: 347 unit, 144 integration and 23 release tests.
A first attempt that morning failed with 30 and 36 integration failures: the assistant had started a
second `composer check` while the first was still running (it could not see the first one), and the
two runs shared one test installation. Run alone, it was green.

## What was done (measured in the trial's database afterwards)

- The module: `ContentCredentials`, active, version 0.1.0; tables `content_credentials_verdict` and
  `content_credentials_evidence` present.
- Four uploads:

| file | state | signer | AI statement | evidence |
|---|---|---|---|---|
| a JPEG of Maurice's own | `none` | | | no |
| a PNG of Maurice's own | `none` | | | no |
| `google-20250919-pixel10-npld-picnic-table.jpg` (a fixture) | `Trusted` | Pixel Camera (Google LLC) | | 8,412 bytes |
| `amazon-20240925-titan-g1.png` (a fixture) | `Valid` | Amazon Web Services, Inc. | `generated` | 22,220 bytes |

These are the verdicts the integration tests expect for the two fixtures.

## What Maurice said

"het werkt" after the first two uploads, and "ik heb het gedaan en het werkt" after the two
fixtures. What he looked at on the pages, and whether he tried a re-check or uninstalling, was not
recorded; the database shows no re-check job.

## Not tried

- An installation other than the assistant's Docker image: another web server, another database, a
  server of an institution.
- A file of his own with Content Credentials; both signed files were fixtures.
