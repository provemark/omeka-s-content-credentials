<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Display\RecheckSection;
use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Job\Dispatcher;
use Throwable;
use UnexpectedValueException;

/**
 * Starting runs and showing the latest (SPEC-005), on the configure page,
 * which Omeka shows to global administrators only (AC10, step 15). One run
 * at a time (AC9). A run that Omeka could not start is `error` at once,
 * while Omeka's page would report success (step 18); start() says so.
 */
final class RecheckRuns
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Dispatcher $dispatcher,
    ) {}

    public static function create(ServiceLocatorInterface $services): self
    {
        $connection = $services->get('Omeka\Connection');
        $dispatcher = $services->get('Omeka\Job\Dispatcher');
        if (! $connection instanceof Connection || ! $dispatcher instanceof Dispatcher) {
            throw new UnexpectedValueException('Omeka S did not provide its database connection or job dispatcher.');
        }

        return new self($connection, $dispatcher);
    }

    /** @return array{id: int, status: string, started: ?string, ended: ?string}|null */
    public function latest(): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT id, status, started, ended FROM job WHERE class = ? ORDER BY id DESC LIMIT 1', [RecheckJob::class]);
        if ($row === false) {
            return null;
        }
        $text = static fn (mixed $value): ?string => is_string($value) ? $value : null;

        return [
            'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
            'status' => $text($row['status'] ?? null) ?? '',
            'started' => $text($row['started'] ?? null),
            'ended' => $text($row['ended'] ?? null),
        ];
    }

    /** Starts a run of the given kind; returns null, or why there is none or it failed. */
    public function start(string $media): ?string
    {
        if (! in_array($media, [RecheckSection::ALL, RecheckSection::WITHOUT_RESULT], true)) {
            return 'No run was started: unknown kind of run.'; // @translate
        }
        $latest = $this->latest();
        if ($latest !== null && in_array($latest['status'], RecheckSection::RUNNING, true)) {
            return 'A run is already in progress; no new run was started.'; // @translate
        }
        $job = $this->dispatcher->dispatch(RecheckJob::class, ['media' => $media]);

        // Omeka's dispatch() may return null; no job is no run (fail closed).
        $failed = 'The run could not start; see its job page.'; // @translate

        return $job === null || $job->getStatus() === 'error' ? $failed : null;
    }

    /**
     * Marks a run whose process has gone as ended (AC14); returns null, or why
     * nothing changed. One statement, so a run that has moved on is left alone.
     */
    public function end(mixed $id): ?string
    {
        $refused = 'No run was marked as ended.'; // @translate
        if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
            return $refused;
        }
        $marks = implode(', ', array_fill(0, count(RecheckSection::ENDABLE), '?'));
        try {
            $changed = $this->connection->executeStatement(
                "UPDATE job SET status = 'error', ended = ?, pid = NULL, log = CONCAT(IFNULL(log, ''), ?) WHERE id = ? AND class = ? AND status IN ({$marks})",
                [date('Y-m-d H:i:s'), date('c')." INFO (6): Marked as ended by an administrator.\n", (int) $id, RecheckJob::class, ...RecheckSection::ENDABLE],
            );
        } catch (Throwable) {
            return $refused;
        }

        return $changed === 1 ? null : $refused;
    }

    public function html(PhpRenderer $view): string
    {
        $text = new ViewText($view);
        $latest = $this->latest();
        $url = null;
        $plugin = $latest === null ? null : $view->plugin('url');
        if ($latest !== null && is_callable($plugin)) {
            $built = $plugin('admin/id', ['controller' => 'job', 'id' => $latest['id']]);
            $url = is_string($built) ? $built : null;
        }

        return RecheckSection::html(
            static fn (string $source): string => $text->translate($source),
            static fn (string $value): string => $text->escape($value),
            $latest,
            $url,
        );
    }
}
