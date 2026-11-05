# ADR-0005: The evidence table

| Field    | Value            |
|----------|------------------|
| Status   | proposed         |
| Date     | 2026-11-05       |
| Decided  | (Maurice van Loon, pending) |

## Context

Decision 5 (`NOTES.md`, 2026-11-03) chose where the evidence goes:
- the verifier's evidence document (`evidenceToJson()`, format version 1);
- a table of the module's own, one row per check, never updated;
- with the check's date and the verifier's version.

M5's aim (`docs/milestones.md`) is that the stored document is
byte-identical to `evidenceToJson()` and survives a verifier update. This
ADR records how, on the measurements of
`notes/step-23-evidence-document.md` and
`notes/step-24-evidence-in-omeka.md` (verifier 0.10.0, Omeka S 4.2.1):

- **Every check that reaches the verifier gives a document.** That
  includes empty, truncated and noise files. A check that ends in `error`
  never reaches a report.
- **The documents are ASCII and stay byte-identical.** They run from 194
  bytes to 35 KB in the fixtures, with a 5 MiB test as well. A
  `utf8mb4` `LONGTEXT` gave all 3 303 written documents back byte for
  byte, and so did a download.
- **The document holds no hash of the file**; saying which file it
  belongs to is the archive's task (the verifier's
  `docs/evidence-format.md`).
- **The trust lists.** With a settings document (`fromJson()`, which
  SPEC-004 uses) `trust_lists` is `[]`. `fromTrustLists()` names the two
  lists by their published hashes and gives the same verdicts. With the
  DigiCert root joined to the TSA list, though, it names a list that
  nobody published.
- **Cost.** Writing a row costs about 0.3 ms. Appending at every check
  grows the table by about 6 MB on disk per run over 1 100 media.
- **Admin route.** A route of the module's own needs its own
  `__NAMESPACE__`, or it ends in a 404 that says nothing. Omeka registers
  the controller as an ACL resource.
- **Access.** Reading the media item through Omeka's API before serving
  gives exactly the media page's access. An ACL rule alone does not: it
  shuts out editors and reviewers, or opens every document to every
  logged-in user.
- **Deletion and uninstall.** A foreign key `ON DELETE CASCADE` removes
  the evidence with its media item, also when the item is deleted. A
  table dropped in `uninstall()` leaves the schema as it was (ADR-0003).

## Decision

1. **A second table, `content_credentials_evidence`**, created in
   `install()` and dropped in `uninstall()`, next to
   `content_credentials_verdict` (ADR-0003). Its columns:

   | column | type | holds |
   |---|---|---|
   | `id` | `INT AUTO_INCREMENT`, primary key | the order of the checks |
   | `media_id` | `INT NOT NULL`, foreign key to `media(id)` `ON DELETE CASCADE` | the media item |
   | `checked_at` | `DATETIME NOT NULL`, UTC | when the check ran |
   | `checked_sha256` | `CHAR(64) NOT NULL` | the SHA-256 of the original as it was checked: which file the document is about |
   | `verifier` | `VARCHAR(32) NOT NULL` | the verifier's version (also inside the document) |
   | `trust` | `VARCHAR(64) DEFAULT NULL` | which settings judged it, in the vocabulary of the verdict's `trust` column (SPEC-004) |
   | `trust_sha256` | `CHAR(64) DEFAULT NULL` | the SHA-256 of the exact settings text given to `TrustSettings::fromJson()`, bundled or custom; null without settings |
   | `sha256` | `CHAR(64) NOT NULL` | the SHA-256 of `document`, computed before the insert |
   | `document` | `LONGTEXT NOT NULL` | `evidenceToJson()`, unchanged |

   With an index on `(media_id, id)`, `utf8mb4_unicode_ci` and InnoDB,
   as the verdict table has.
2. **Append-only.** The module only inserts. It never updates a row and
   deletes rows only through the foreign key and `uninstall()`.
3. **Plain DBAL, no Doctrine entity.** Nothing needs the table in DQL;
   the verdict table keeps its entity for the browse filter.
4. **Written where the verdict is written.** That is the `postFlush`
   listener on upload and the run's job on a re-check. The insert and the
   verdict's final upsert go in one transaction, so a verdict and its
   evidence are stored together or not at all. A failure is handled as a
   failed write is today (SPEC-002, SPEC-005). A check that ends in
   `error` has no document and writes no row.
5. **Keep `TrustSettings::fromJson()`** for the bundled lists, as SPEC-004
   does. Name the lists through `trust` and `trust_sha256` instead of
   through `fromTrustLists()`. No SPEC-004 amendment.
6. **Deleting a media item deletes its evidence** (the foreign key), as
   it deletes its verdict.
7. **Uninstall drops the table**, as it drops the verdict table. M7's aim
   is that uninstall removes everything.
8. **Viewing and downloading go through an admin route of the module's
   own.** It has its own `__NAMESPACE__`. Its controller reads the media
   item through Omeka's API before it serves anything, whatever further
   limit SPEC-006 chooses. The download serves `document` unchanged.
9. **No `upgrade()` step.** The module is unreleased at 0.1.0. A
   development installation is reinstalled, as with SPEC-004's table
   change.

## Alternatives

- **The document in a column of the verdict table.** That would be
  overwritten by every re-check, which decision 5 rules out.
- **Files next to the original.** Refused in decision 5: not in the
  database backup, and the original's directory belongs to Omeka.
- **`fromTrustLists()`, so the document names the lists itself.**
  - Without DigiCert this is exact, but then `c2pa-rs-ocsp.jpg` and the
    Amazon Titan and Lightroom files of step 16 lose the trusted
    time-stamp they now get.
  - With DigiCert joined to the TSA list, the document carries a hash no
    one can look up.
  - Either way it is a SPEC-004 amendment.
  - `trust_sha256` pins the same thing for both bundled and custom
    settings. For the bundled lists, the file hashes of each release are
    in `BundledLists::FILES` and in ADR-0004.
- **No foreign key, so evidence outlives its media item.**
  - The rows would point at a media id that no longer exists.
  - No page could show them, because access rests on reading the media
    item (point 8).
  - Removing them would need a tool of their own.
  - An archive that must keep evidence of deleted media keeps it in the
    database backup it already makes.
- **Keeping the table on uninstall.** This leaves Omeka's schema
  changed after the module is gone, against M7's aim and against what
  ADR-0003 does for the verdict.

## Consequences

- Each check adds a few KB: about 6 MB on disk per run over 1 100 media,
  measured; about 18 MB per run over 3 000 media, reasoned. Whether every
  re-check appends, or only one whose document differs, is SPEC-006's
  choice. 79% of the documents measured differed at every run.
- **Deleting media or uninstalling the module ends the evidence.** The
  uninstall confirmation does not say so unless the module adds the
  warning; SPEC-006 or M7 decides.
- **For a file without a manifest the document says `Invalid` where the
  module shows `none`** (step 23). Whether such checks get a row, and
  how it is shown, is SPEC-006's choice. This ADR allows both.
- **Who may see and download the evidence** is SPEC-006's choice: whoever
  can see the media item, or admins only. Both rest on point 8.
- **Deptrac.** The new classes fit the existing `Omeka` and `Check`
  layers. No new layer is needed unless SPEC-006 adds a directory.
- **`X-Content-Type-Options: nosniff`** is not sent by Omeka. The
  download sets it.
