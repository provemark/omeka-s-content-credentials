<?php

declare(strict_types=1);

namespace RecheckProbe;

use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Check\Recorder;
use ContentCredentials\Omeka\DbalVerdictStore;
use ContentCredentials\Omeka\TrustConfig;
use ContentCredentials\Omeka\VerifierLoader;
use ContentCredentials\Trust\BuiltTrust;
use Omeka\Job\AbstractJob;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

/**
 * Measurement only (M4.2): re-checks media with the module's own
 * MediaCheck and Recorder, reading media rows through DBAL in batches.
 * Arguments: label, trust (once|per_item), after_id, limit, stop_every,
 * batch, only_unchecked. Writes to /tmp/recheck-probe.log.
 */
final class RecheckJob extends AbstractJob
{
    private const DIR = '/var/www/html/modules/ContentCredentials';

    public static function log(string $line): void
    {
        file_put_contents('/tmp/recheck-probe.log', date('H:i:s').' '.$line."\n", FILE_APPEND);
    }

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $store = $services->get('Omeka\File\Store');
        $label = (string) $this->getArg('label', 'run');
        $trustConfig = TrustConfig::create($services, self::DIR);
        VerifierLoader::register(self::DIR.'/vendor/provemark/c2pa-verifier/src');
        $once = $this->getArg('trust', 'once') === 'once' ? $trustConfig->built() : null;
        $check = new MediaCheck(
            static fn (): string => Verifier::VERSION,
            static fn ($stream, ?TrustSettings $settings) => (new Verifier)->verify($stream, $settings),
            static fn (): BuiltTrust => $once ?? $trustConfig->built(),
        );
        $failures = 0;
        $recorder = new Recorder($check, new DbalVerdictStore($connection), static function () use (&$failures): void {
            $failures++;
        });
        $after = (int) $this->getArg('after_id', 0);
        $limit = (int) $this->getArg('limit', 0);
        $stopEvery = max(1, (int) $this->getArg('stop_every', 50));
        $batch = max(1, (int) $this->getArg('batch', 200));
        $onlyUnchecked = (bool) $this->getArg('only_unchecked', false);
        $t0 = microtime(true);
        $n = 0;
        $byType = [];
        self::log(sprintf('START %s pid=%d after_id=%d trust=%s mem=%.1fMB', $label, getmypid(), $after, $once ? 'once' : 'per_item', memory_get_usage(true) / 1048576));
        while (true) {
            $rows = $connection->fetchAllAssociative(
                'SELECT m.id, m.media_type, m.storage_id, m.extension FROM media m'
                .($onlyUnchecked ? ' LEFT JOIN content_credentials_verdict v ON v.media_id = m.id' : '')
                ." WHERE m.has_original = 1 AND m.media_type IN ('image/jpeg', 'image/png', 'image/webp') AND m.id > ?"
                .($onlyUnchecked ? ' AND v.media_id IS NULL' : '')
                .' ORDER BY m.id LIMIT '.$batch,
                [$after],
            );
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $file = $row['storage_id'].($row['extension'] !== null ? '.'.$row['extension'] : '');
                $t = microtime(true);
                $recorder->record((int) $row['id'], $store->getLocalPath('original/'.$file), PHP_VERSION);
                $byType[$row['media_type']][0] = ($byType[$row['media_type']][0] ?? 0) + 1;
                $byType[$row['media_type']][1] = ($byType[$row['media_type']][1] ?? 0) + (microtime(true) - $t);
                $after = (int) $row['id'];
                $n++;
                if ($n % 500 === 0) {
                    self::log(sprintf('PROGRESS %s n=%d elapsed=%.1fs mem=%.1fMB last_id=%d', $label, $n, microtime(true) - $t0, memory_get_usage(true) / 1048576, $after));
                }
                if ($n % $stopEvery === 0 && $this->shouldStop()) {
                    self::log(sprintf('STOPPED %s n=%d last_id=%d elapsed=%.1fs', $label, $n, $after, microtime(true) - $t0));

                    return;
                }
                if ($limit > 0 && $n >= $limit) {
                    break 2;
                }
            }
        }
        $types = [];
        foreach ($byType as $type => [$count, $seconds]) {
            $types[] = sprintf('%s %d x %.1fms', $type, $count, 1000 * $seconds / $count);
        }
        self::log(sprintf('END %s n=%d elapsed=%.1fs last_id=%d failures=%d peak=%.1fMB | %s', $label, $n, microtime(true) - $t0, $after, $failures, memory_get_peak_usage(true) / 1048576, implode('; ', $types)));
    }
}
