# SPEC-###: <title>

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | draft \| approved \| implemented \| superseded    |
| Author     | <name>                                            |
| Approved   | <name + date, or — while draft>                   |
| Supersedes | <SPEC-### or —>                                   |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

<Why this exists. The user/domain need. What breaks or is impossible without
it. Point at the decisions in `NOTES.md`, at the step notes in `notes/` that
measured what this spec rests on, at the Omeka S developer documentation
(`omeka.org/s/docs/developer/`, by page) and the Omeka S 4.2.1 source (by
file and class), and at the C2PA specification by version and section
number. Never at a file that is not in the repository.>

## Scope

**In scope**

- <bullet>

**Out of scope** (each needs its own spec before it may be built)

- <bullet>

## Behavior

Acceptance criteria as Given/When/Then. Each is individually testable and will be
covered by a Pest test tagged `->group('SPEC-###')`, in `tests/Unit` when it
needs no Omeka S and in `tests/Integration` (the Docker environment of
`tools/env/`) when it does. At least one criterion MUST be an error /
malformed-input path. This project fails closed: it never shows a verdict
the verifier did not give, a failure is stored as state `error` with a
reason, and the upload always proceeds. Everything read from a file is
untrusted text; a criterion that displays it says how it is escaped (the
Laminas `escapeHtml` view helper, or which other).

- **AC1 — <happy path name>**
  - Given <precondition>
  - When <action>
  - Then <observable, verifiable outcome>

- **AC2 — <error path name>** *(required: error / malformed input)*
  - Given <invalid precondition>
  - When <action>
  - Then <specific failure: exception type / error value, no partial side effects>

## References

<What this spec is measured against, kept apart from what it is reasoned from.>

- Specification: <C2PA Technical Specification version and section(s), e.g.
  "C2PA 2.4 §15.x"; Omeka S developer documentation by URL; the Omeka S
  4.2.1 source by file and class>
- Oracle: <the tool and pinned version, the fixture, and the command whose
  output the acceptance criteria must match — e.g. "provemark/c2pa-verifier
  v0.10.0, `tests/Fixtures/...`, `vendor/bin/c2pa-verify [--settings f] <file>`";
  the Omeka S and PHP version it was measured on>
- Reasoned: <claims made from reading rather than running, if any>

## API sketch

<Illustrative only — not binding implementation. Signatures, value objects,
interfaces. Note `final`, `readonly`, `strict_types=1` intentions. Show the
shape a caller sees. `Module.php` must stay parseable on PHP 8.1
(ADR-0001).>

```php
// namespace ContentCredentials\...;
```

## Open questions

- <unresolved decision; blocker vs. non-blocker>
  *Status <date>:* <required once the spec is approved: answered, measured,
  read, or still open and why>

## Traceability

Filled when status becomes `implemented`. Every acceptance criterion maps to at
least one test; every source file maps back to this spec.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1                  | —                           | —                    |
| AC2                  | —                           | —                    |
