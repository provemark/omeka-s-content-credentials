# Step 45 (M7.15): the build says when Strauss prefixes nothing

2026-11-07. Clean-up 3 of the review (step 35), moved before the release by Maurice:
`tools/build.sh` sent Strauss's output to `/dev/null`. SPEC-008 amendment 5 adds AC17, approved by
Maurice.

## What was measured before building

On a tree whose Strauss configuration names a package that is not installed (a throwaway tree; the
working tree was not changed), `tools/build.sh` exited 1 with only
`find: …/vendor-prefixed/provemark/c2pa-verifier: No such file or directory`. Strauss itself had
exited 0, said `[notice] Done in 0.25s`, and prefixed nothing: the build failed later, at a line that
does not point to the cause.

Strauss 0.31.1 run by hand in two throwaway copies: with a package that is not installed, and with a
namespace prefix that is a list instead of text. Both exited 0; the second prefixed the verifier
anyway. No configuration was found that makes Strauss itself exit non-zero.

## What was built

`tools/build.sh` keeps Strauss's output. When Strauss exits non-zero, or the prefixed verifier is not
there afterwards, it says "Error: Strauss did not prefix the verifier into …. Its output:", prints the
output, removes its working folder and exits 1. On a good tree nothing more is shown than before.

## Tests seen red (measured)

```
vendor/bin/pest --testsuite=Release --filter='AC17|AC1: the zip comes'
Tests:    1 failed, 1 passed (9 assertions)
```

AC17 failed on the missing message; the `find` error was what came instead. AC1, now also checking
that a good build shows no `[notice]` line of Strauss, passed and must stay so. After building, with
AC2: 3 passed (18 assertions).

## Falsified (measured)

Each a temporary change of `tools/build.sh`, restored after its run, on those three tests.

| change | failed |
|---|---|
| only Strauss's exit code checked | AC17 |
| only the folder checked | **none** |
| the output not shown | AC17 |
| the output shown on success too | AC1 |
| exit 0 after the message | AC17 |
| no message | AC17 |
| the working folder left | **none** |

- **Only the folder checked** survives because no test makes Strauss exit non-zero: none was found
  (above), and a stand-in phar would have to pass AC2's hash check. That part of AC17 is read, not
  measured, as said when it was proposed.
- **The working folder left** is not part of AC17: the zip is made only after this point, so none is
  left either way. The next build starts by emptying the folder.

**The first run of this table was wrong.** AC1 failed with a `RuntimeException` under changes that do
not touch a good build. The exception was `rm -rf …/build/tests/clone`: "Directory not empty", in
`.git/info`, three runs out of three on the built code when the tests ran directly after each other.
Something wrote into the previous run's throwaway repository while it was being removed; what, was not
found (a guess, not checked: the IDE noticing a new repository inside the project). The table is from
a second run that empties `build/tests` first, with retries, and waits 2 s. `composer check` runs
these tests once, after other suites, and has not shown it. Not fixed here.

## Not measured

- Strauss itself exiting non-zero (above).
