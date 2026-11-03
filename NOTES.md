# Notes

Decisions and open questions for Content Credentials (C2PA), a module for
Omeka S. Each entry says what is measured (a command was run) and what is
reasoned (read or concluded). The record of each step is in `notes/`; this
file holds the decisions and an index.

What the module does: it verifies the Content Credentials (C2PA) of every
uploaded media file with `provemark/c2pa-verifier`, stores the verdict with
the media and shows it in the admin interface. It only checks: it never
signs, never blocks an upload and opens no network connection. Later
(milestone M5) it keeps the verifier's evidence document behind each
verdict, so that what held when a file came in stays on record after a
certificate has expired.

## Decisions (2026-11-03)

### 1. Name, module directory, namespace

| what | value |
|---|---|
| display name | Content Credentials (C2PA) |
| module directory | `ContentCredentials` |
| PHP namespace | `ContentCredentials` |

Descriptive, as Omeka S modules usually are (`FileSideload`,
`ImageServer`), and the same name as the Drupal module
`content_credentials`. It does not depend on the ProveMark name, which may
change.

Measured 2026-11-03: the module list on `omeka.org/s/modules/` (189
modules, one page, fetched with `curl`) has no module whose name or text
contains "C2PA", "Content Credentials", "provenance" or "Tracefern".
`gh search repos` finds nothing for "ContentCredentials omeka", "c2pa
omeka" or "omeka content credentials".

Reasoned, not measured: that the namespace must equal the module
directory. To measure in M0 against the pinned Omeka S.

### 2. Licence: GPL-3.0-or-later

The licence of Omeka S and of the modules in `omeka-s-modules`. The bundled
verifier is MIT, which combines with it.

Measured 2026-11-03 (`gh api repos/<repo>/license`): `omeka/omeka-s`,
`omeka-s-modules/FileSideload`, `CSVImport` and `Mapping` are GPL-3.0;
`Daniel-KM/Omeka-S-module-Common` is CeCILL-2.1. The page "Register an
add-on" on omeka.org states no licence requirement (it asks for a release
zip and a directory name that matches).

Reasoned, not measured: whether Omeka S itself says "only" or "or later";
the module says "or later" either way.

### 3. Storing the verdict: a module-owned table (direction; ADR to follow)

A Doctrine entity and table owned by the module, with a foreign key to the
media (on delete cascade), as the official Mapping module keeps its own
entities. Users cannot edit it, the browse filter (M2) and the re-check
(M4) can query it, and uninstall removes it.

Rejected: Omeka values on the media (editable, so a shown verdict need not
come from the verifier; and it mixes untrusted text from the file into the
catalogue's metadata); the media's `data` column (owned by the ingester,
possibly overwritten on update, and hard to filter on).

Reasoned, not measured: all of the above. The ADR is written after the
mechanism (entity registration, install and uninstall SQL, migrations) has
been measured against the pinned Omeka S.

### 4. Trust lists: as Tracefern

- The module bundles `C2PA-TRUST-LIST.pem` (trust kind `manifest`) and
  `C2PA-TSA-TRUST-LIST.pem` (trust kind `tsa`) from
  [c2pa-org/conformance-public](https://github.com/c2pa-org/conformance-public/tree/main/trust-list)
  (CC BY 4.0), and shows the date of the copy in the settings.
- An administrator can paste a trust-settings JSON (the verifier's format).
  It **replaces** the bundled lists; nothing is merged.
- DigiCert Trusted Root G4 is a separate `tsa` anchor behind its own
  option, **on by default**. Turning it on means accepting the time
  DigiCert's timestamp authorities put on a signature.
- The settings warn when the bundled copy is older than about six months.
  The module never fetches a list itself.

Read, not measured here: the verifier's `docs/trust-settings.md` (measured
there on 2026-09-25): without the DigiCert anchor, every Adobe Firefly or
Microsoft Bing file whose signer has expired is `Invalid`. Built in M3.

### 5. Evidence: in the database, its own milestone (M5)

The evidence document (`evidenceToJson()`, format version 1) is stored in a
module-owned table of its own, one row per check, never updated, with the
check's date and the verifier's version. A re-check (M4) therefore never
overwrites what held when the file came in. It travels with the database
backup an institution already makes. Built in M5; the table design of M1
leaves room for it.

Read in the verifier's `docs/evidence-format.md`, not measured:
`evidenceToJson()` writes every character outside ASCII as `\uXXXX`, so
the document is ASCII and a text column can keep it byte-identical
whatever the character set.

Rejected: writing it in M1 already (M1 grows, and without M5's viewing and
downloading it is not usable); a file next to the original through Omeka's
file store (its store API takes a local source path, so a temporary file,
to measure; files can be orphaned from their row; no filtering or counting
without the database).

### 6. Distribution

Local until M0 is done; then a private GitHub repository, so CI (PHP 8.3,
8.4, 8.5) runs from M1 on. Its owner and name are asked for at that moment,
with a view to the renaming of ProveMark. Public, and registration on
omeka.org, only with Maurice's go.

## To measure before a spec rests on it

From the brief, each to become a measured note against the pinned
Omeka S 4.2.x:

- the module skeleton (`config/module.ini`, `Module.php`,
  `config/module.config.php`), and whether the namespace must equal the
  directory;
- where the original lives, and whether it is byte-identical to the upload
  on every route (browser upload, URL, sideload, CSV Import, API);
- which event fires once per new media item with the file available, and
  which on replace and delete;
- background jobs, and whether a failing job can break an upload;
- storage: entity registration, install and uninstall SQL, migrations
  (decision 3);
- display: view events or helpers for the media page and the browse;
- the PHP floor: Omeka S 4.2 from PHP 8.1, the verifier `^8.3` (an ADR);
- bundling: Strauss prefixing of the verifier, as Tracefern's SPEC-009.
