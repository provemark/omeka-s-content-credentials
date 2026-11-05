<?php

declare(strict_types=1);

namespace EvidenceRouteProbe;

use ContentCredentials\Omeka\TrustConfig;
use ContentCredentials\Omeka\VerifierLoader;
use Doctrine\DBAL\Connection;
use Omeka\Job\AbstractJob;
use Provemark\C2paVerifier\Verifier\Verifier;
use Throwable;

/**
 * Measurement only (M5.2): verifies every checkable media item with the
 * module's trust settings (built once) and appends its evidence document to
 * the probe's table. Arguments: label, limit. Writes to
 * /tmp/evidence-probe.log.
 */
final class EvidenceJob extends AbstractJob
{
    private const DIR = '/var/www/html/modules/ContentCredentials';

    public static function insert(Connection $connection, int $mediaId, string $document): float
    {
        $t = microtime(true);
        $connection->insert(Module::TABLE, [
            'media_id' => $mediaId,
            'checked_at' => gmdate('Y-m-d H:i:s'),
            'sha256' => hash('sha256', $document),
            'document' => $document,
        ]);

        return microtime(true) - $t;
    }

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $store = $services->get('Omeka\File\Store');
        $label = (string) $this->getArg('label', 'run');
        $limit = (int) $this->getArg('limit', 0);
        VerifierLoader::register(self::DIR.'/vendor/provemark/c2pa-verifier/src');
        $settings = TrustConfig::create($services, self::DIR)->built()->settings;
        $verifier = new Verifier;
        $t0 = microtime(true);
        $n = $bytes = $failures = 0;
        $verifyTime = $jsonTime = $insertTime = 0.0;
        $after = 0;
        self::log(sprintf('START %s pid=%d trust=%s', $label, getmypid(), $settings === null ? 'none' : 'yes'));
        while (true) {
            $rows = $connection->fetchAllAssociative(
                "SELECT id, storage_id, extension FROM media WHERE has_original = 1 AND media_type IN ('image/jpeg', 'image/png', 'image/webp') AND id > ? ORDER BY id LIMIT 200",
                [$after],
            );
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $after = (int) $row['id'];
                $path = $store->getLocalPath('original/'.$row['storage_id'].($row['extension'] !== null ? '.'.$row['extension'] : ''));
                $stream = @fopen($path, 'rb');
                if ($stream === false) {
                    $failures++;

                    continue;
                }
                try {
                    $t = microtime(true);
                    $report = $verifier->verify($stream, $settings);
                    $verifyTime += microtime(true) - $t;
                    $t = microtime(true);
                    $document = $report->evidenceToJson();
                    $jsonTime += microtime(true) - $t;
                    $insertTime += self::insert($connection, $after, $document);
                    $bytes += strlen($document);
                    $n++;
                } catch (Throwable $e) {
                    $failures++;
                    self::log('FAIL '.$after.' '.$e::class);
                } finally {
                    fclose($stream);
                }
                if ($limit > 0 && $n >= $limit) {
                    break 2;
                }
            }
        }
        self::log(sprintf('END %s n=%d failures=%d elapsed=%.1fs verify=%.1fs json=%.3fs insert=%.1fs bytes=%d peak=%.1fMB', $label, $n, $failures, microtime(true) - $t0, $verifyTime, $jsonTime, $insertTime, $bytes, memory_get_peak_usage(true) / 1048576));
    }

    private static function log(string $line): void
    {
        Module::log($line);
    }
}
