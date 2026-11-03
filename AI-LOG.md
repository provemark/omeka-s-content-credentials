# AI log

This project is built with Claude Code (Anthropic), driven and reviewed by
Maurice van Loon. Every contribution the assistant produces is logged here,
newest at the bottom, in the same commit as the work it describes. What is
*measured* (a command was run) is kept apart from what is *reasoned* (a
conclusion from reading). Decisions are Maurice's; the assistant proposes.

The assistant is not listed as an author in commit metadata; this log and the
README are where the disclosure lives.

## 2026-11-03: Repository created, start-up decisions (M0.1)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: read the local project brief and the sister-project files it names,
  walk the open decisions one at a time, then set up the repository.
- Produced: `.gitignore` (the local brief on the first line, `vendor/`,
  Docker overrides, tool caches, build output, editor files, and `*.key`
  unconditionally); this `AI-LOG.md`; `NOTES.md` with the six decisions
  below and the facts still to be measured. One commit, no remote.
- Read: `provemark/c2pa-verifier` README ("Use", "Public API"),
  `docs/trust-settings.md`, `docs/evidence-format.md`;
  `provemark/tracefern-image-check` `specs/TEMPLATE.md`, `NOTES.md` (the
  decisions and the first open items), `tools/build.sh`.
- Measured: the module list on `omeka.org/s/modules/` (189 modules, `curl`)
  has no module matching "C2PA", "Content Credentials", "provenance" or
  "Tracefern"; `gh search repos` finds no Omeka module for C2PA or Content
  Credentials; `gh api repos/<repo>/license` gives GPL-3.0 for
  `omeka/omeka-s`, `omeka-s-modules/FileSideload`, `CSVImport` and
  `Mapping`, CeCILL-2.1 for `Daniel-KM/Omeka-S-module-Common`; the omeka.org
  page "Register an add-on" states no licence requirement. Local tools:
  PHP 8.5.12, Composer 2.10.2, Docker 29.6.2 with its daemon running.
- Reasoned, not measured: that an Omeka module's namespace must equal its
  directory; the storage options of decision 3 (Omeka values editable, the
  media `data` column owned by the ingester); that the file store's API
  needs a local source path; that `evidenceToJson()` output is ASCII (read
  in `docs/evidence-format.md`).
- Decided by Maurice: (1) name "Content Credentials (C2PA)", module
  directory and namespace `ContentCredentials`; (2) licence
  GPL-3.0-or-later; (3) the verdict in a module-owned table (direction; ADR
  after measurement); (4) trust lists as Tracefern: the two C2PA lists
  bundled with their date, replaceable by the administrator, DigiCert
  Trusted Root G4 behind its own option, on by default, a warning when the
  copy is old, never fetched; (5) evidence in its own database table, one
  row per check, never updated, built in M5; (6) local until M0 is done,
  then a private GitHub repository; public and omeka.org only with
  Maurice's go.

## 2026-11-03: The plan, the licence, a README (M0.2)

- Model: Claude Opus 5.5, Claude Code CLI
- Asked: M0.2 as proposed: `docs/milestones.md`, `LICENSE`, `README.md`,
  `CHANGELOG.md`.
- Produced: `docs/milestones.md` (M0 to M7 from the brief, still a draft
  for M1 to M7, and the M0 steps with their status); `LICENSE`, the GPL-3.0
  text from `gnu.org/licenses/gpl-3.0.txt` (SHA-256
  `3972dc97…36986`); `README.md` with the status and the disclosure of
  this log; `CHANGELOG.md`; in `NOTES.md` decision 2 the open point
  replaced by the measurement below.
- Measured: Omeka S's `LICENSE` (`gh api repos/omeka/omeka-s/contents/LICENSE`)
  differs from the gnu.org text only in four URLs; Omeka S's README says
  "version 3 (GPLv3)" without "or later"; `provemark/c2pa-verifier` is a
  public repository and on Packagist (HTTP 200), so the README's link to it
  holds.
- Reasoned, not measured: nothing new.
- Decided by Maurice: the M0 steps as proposed ("ok, begin met M0.2").
