<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

/**
 * A store that counts the verdicts a run stores, by state (SPEC-005). The
 * placeholder `error` / `interrupted` that Recorder writes first is not a
 * verdict and is not counted.
 */
final class RecheckCounts implements VerdictStore
{
    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(private readonly VerdictStore $store) {}

    public function write(int $mediaId, Verdict $verdict, ?Evidence $evidence = null): void
    {
        $this->store->write($mediaId, $verdict, $evidence);
        if ($verdict->reason !== Verdict::INTERRUPTED) {
            $this->counts[$verdict->state] = ($this->counts[$verdict->state] ?? 0) + 1;
        }
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return $this->counts;
    }
}
