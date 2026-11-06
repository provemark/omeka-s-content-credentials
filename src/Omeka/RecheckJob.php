<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Check\Recheck;
use ContentCredentials\Check\RunFailed;
use ContentCredentials\Display\RecheckSection;
use ContentCredentials\Trust\BuiltTrust;
use Doctrine\DBAL\Connection;
use Laminas\Log\Logger;
use Omeka\File\Store\Local;
use Omeka\Job\AbstractJob;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\VerificationReport;
use Provemark\C2paVerifier\Verifier\Verifier;
use Throwable;

/**
 * A run over existing media (SPEC-005), dispatched through Omeka's job
 * dispatcher and run by Omeka in its own PHP process. The trust settings
 * are built once, at the start (AC3). Omeka would write an exception's
 * message, paths and trace into the job log, which site administrators can
 * read (step 18), so only RunFailed leaves perform(): Omeka then sets the
 * job to `error` and logs that one line of the module's own (AC15).
 */
final class RecheckJob extends AbstractJob
{
    private const BATCH = 200;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $log = static function (string $line) use ($logger): void {
            if ($logger instanceof Logger) {
                $logger->info($line);
            }
        };
        try {
            $notStored = $this->run($log);
        } catch (Throwable) {
            $log('The run ended early because of an error.');

            throw new RunFailed;
        }
        if ($notStored > 0) {
            $log(Recheck::notStoredLine($notStored));

            throw new RunFailed;
        }
    }

    /**
     * The checks not stored by a run that went through every media item. A
     * stopped run counts none: it stays stopped (AC15).
     *
     * @param  \Closure(string): void  $log
     */
    private function run(\Closure $log): int
    {
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        if (! $connection instanceof Connection) {
            throw new \UnexpectedValueException('Omeka S did not provide its database connection.');
        }
        $store = $services->get('Omeka\File\Store');
        $local = $store instanceof Local ? $store : null;
        $directory = dirname(__DIR__, 2);
        $trust = TrustConfig::create($services, $directory);
        try {
            $built = $trust->built();
        } catch (Throwable) {
            $built = BuiltTrust::without('the trust settings could not be built');
        }
        $check = new MediaCheck(
            static function () use ($directory): string {
                VerifierLoader::register($directory);

                return Verifier::VERSION;
            },
            /** @param resource $stream */
            static fn ($stream, ?TrustSettings $settings): VerificationReport => (new Verifier)->verify($stream, $settings),
            static fn (): BuiltTrust => $built,
        );
        $withoutResult = $this->getArg('media') === RecheckSection::WITHOUT_RESULT;
        $from = 'FROM media m'.($withoutResult ? ' LEFT JOIN '.Schema::TABLE.' v ON v.media_id = m.id' : '')
            .' WHERE m.has_original = 1 AND m.media_type IN (?, ?, ?)'.($withoutResult ? ' AND v.media_id IS NULL' : '');
        $types = AfterFlushListener::MEDIA_TYPES;
        $total = $connection->fetchOne('SELECT COUNT(*) '.$from, $types);
        $batch = static function (int $after) use ($connection, $from, $types, $local): array {
            $rows = [];
            foreach ($connection->fetchAllAssociative('SELECT m.id, m.storage_id, m.extension '.$from.' AND m.id > ? ORDER BY m.id LIMIT '.self::BATCH, [...$types, $after]) as $row) {
                $storage = $row['storage_id'] ?? null;
                $extension = $row['extension'] ?? null;
                $file = is_string($storage) ? $storage.(is_string($extension) && $extension !== '' ? '.'.$extension : '') : null;
                $rows[] = [(int) (is_numeric($row['id'] ?? null) ? $row['id'] : 0), $local !== null && $file !== null ? $local->getLocalPath('original/'.$file) : null];
            }

            return $rows;
        };
        $recheck = new Recheck($check, new DbalVerdictStore($connection), $batch, $log, fn (): bool => $this->shouldStop() === true);

        return $recheck->run(is_numeric($total) ? (int) $total : 0, PHP_VERSION) ? $recheck->notStored() : 0;
    }
}
