<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

use Closure;

/**
 * One run over existing media (SPEC-005): each media item is recorded as
 * an upload is (SPEC-002), in ascending id, until there are no more or the
 * run is asked to stop. What goes to $log is the module's own words and
 * numbers only (AC7): nothing from a file, an exception or the settings.
 */
final class Recheck
{
    public const LOG_EVERY = 500;

    public const STOP_EVERY = 50;

    private int $notStored = 0;

    /**
     * @param  Closure(int): list<array{int, ?string}>  $batch  media ids after an id, ascending, with their original's path
     * @param  Closure(string): mixed  $log
     * @param  Closure(): bool  $shouldStop
     */
    public function __construct(
        private readonly MediaCheck $check,
        private readonly VerdictStore $store,
        private readonly Closure $batch,
        private readonly Closure $log,
        private readonly Closure $shouldStop,
    ) {}

    /** True when the run went through every media item, false when it stopped. */
    public function run(int $total, string $phpVersion): bool
    {
        $counts = new RecheckCounts($this->store);
        $recorder = new Recorder($this->check, $counts, function (mixed $e, int $mediaId): void {
            $this->notStored++;
            ($this->log)(sprintf('The check of media %d was not stored.', $mediaId));
        });
        ($this->log)(sprintf('Checking %d media.', $total));
        $after = 0;
        $done = 0;
        while (($rows = ($this->batch)($after)) !== []) {
            foreach ($rows as [$id, $path]) {
                if ($id <= $after) {
                    // Not ascending: end here rather than loop.
                    break 2;
                }
                $recorder->record($id, $path, $phpVersion);
                $after = $id;
                $done++;
                if ($done % self::LOG_EVERY === 0) {
                    ($this->log)(sprintf('Checked %d of %d media.', $done, $total));
                }
                if ($done % self::STOP_EVERY === 0 && ($this->shouldStop)()) {
                    ($this->log)(sprintf('Stopped after %d of %d media.', $done, $total));

                    return false;
                }
            }
        }
        $states = $counts->counts();
        ($this->log)(sprintf(
            'Checked %d media: %d Trusted, %d Valid, %d Invalid, %d none, %d error.',
            $done,
            $states['Trusted'] ?? 0,
            $states['Valid'] ?? 0,
            $states['Invalid'] ?? 0,
            $states[Verdict::NONE] ?? 0,
            $states[Verdict::ERROR] ?? 0,
        ));

        return true;
    }

    /** The checks of this run that could not be stored (AC15). */
    public function notStored(): int
    {
        return $this->notStored;
    }

    /** The line that says how many (AC15). */
    public static function notStoredLine(int $count): string
    {
        return $count === 1 ? '1 check was not stored.' : sprintf('%d checks were not stored.', $count);
    }
}
