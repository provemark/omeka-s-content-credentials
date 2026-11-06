# Content Credentials (C2PA) for Omeka S

An Omeka S module that verifies the Content Credentials (C2PA) of every
uploaded media file, stores the verdict with the media, and shows it in
the admin interface. It only checks: it never signs, never blocks an
upload and opens no network connection. Verification is done by
[`provemark/c2pa-verifier`](https://github.com/provemark/c2pa-verifier),
in pure PHP.

It also keeps the evidence behind each verdict, the certificates and
time-stamps it rested on and the settings it was judged with, so that what
held when a file came in stays on record after a certificate has expired.

## Status

In development; nothing has been released. Milestone M1 is done: every
new JPEG, PNG and WebP media item is verified once, after it is saved,
and the verdict is stored with it (SPEC-002). Milestone M2 is done: the
admin media page and its Details panel show the verdict, the media browse
has a column "Content Credentials" and a filter by verdict (SPEC-003).
Administrators who have chosen their own browse columns do not get the
column until they add it, as with Omeka's own optional columns.
Milestone M3 is done: uploads are checked against the C2PA trust lists
bundled in [`trust/`](trust/README.md) (CC BY 4.0, © C2PA), with or
without DigiCert timestamps, or against custom trust settings, all on
the module's configure page (SPEC-004). Milestone M4 is done: a global
administrator can check existing media again from the configure page,
all of them or those without a result, as a background job that Omeka's
job page can stop (SPEC-005). Milestone M5 is done: every check with
Content Credentials keeps the verifier's evidence document, never changed
afterwards, which whoever can see the media item can view and download
from the admin interface (SPEC-006). Deleting the media item or
uninstalling the module deletes its evidence. Milestone M6 is done: a file
that verifies and says it was made or edited with generative AI shows
"AI-generated (signed)" or "AI-edited (signed)", and the media browse can
filter on either (SPEC-007). The label repeats a signed statement in the file;
it is not a detection. A file without that statement says nothing either way,
and a statement further down the file's history is only shown when each step
to it holds a recorded result that the verifier agrees with, so a file can
carry a statement the label does not show. Milestone M7 is under way: there is a
release build, tested in a clean Omeka S, not yet a release (SPEC-008). The plan is in [`docs/milestones.md`](docs/milestones.md),
the decisions are in [`NOTES.md`](NOTES.md).

## Requirements (planned)

- Omeka S 4.2.x
- PHP 8.3 or later (the verifier's floor; Omeka S itself runs from 8.1)

## Building the zip

`tools/build.sh` makes `build/ContentCredentials-<version>.zip` from the committed HEAD, with the
verifier prefixed under `ContentCredentials\Vendor\`, so that another module's copy cannot reach it
(ADR-0006). It needs PHP, Composer, `zip`, and once the network, to fetch a Strauss checked by its
SHA-256. Unpack the zip in Omeka S's `modules/` and install the module from the modules page.

Uninstalling deletes every verdict and evidence document the module has stored, its settings and
the rows of its jobs in Omeka's job list, and ends a run that is going on. Back up the database
first if you want to keep them.

## How this is built

This module is built with Claude Code; [`AI-LOG.md`](AI-LOG.md) records
what the assistant produced and what was decided by the maintainer.

## Licence

GPL-3.0-or-later, see [`LICENSE`](LICENSE). The bundled verifier is MIT.
