<?php

declare(strict_types=1);

namespace JobProbe;

use Omeka\Entity\Media;
use Omeka\Job\AbstractJob;

/** Measurement only (M4.1): see Module.php. */
final class ProbeJob extends AbstractJob
{
    public static function log(string $line): void
    {
        file_put_contents('/tmp/job-probe.log', date('H:i:s').' '.$line."\n", FILE_APPEND);
    }

    public function perform(): void
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $logger = $this->getServiceLocator()->get('Omeka\Logger');
        self::log(sprintf('PERFORM pid=%d sapi=%s php=%s user=%s', getmypid(), PHP_SAPI, PHP_VERSION, get_current_user()));
        $logger->info('JOBPROBE info line with <b>markup</b>');
        if ($this->getArg('throw')) {
            throw new \RuntimeException('JOBPROBE thrown /var/www/html/secret/path');
        }
        if ($this->getArg('fatal')) {
            jobprobe_undefined_function();
        }
        $sleep = (int) $this->getArg('sleep_ms', 0);
        for ($i = 0; $i < (int) $this->getArg('loops', 0); $i++) {
            usleep($sleep * 1000);
            if ($this->shouldStop()) {
                self::log("STOPPED at loop {$i}");

                return;
            }
        }
        if ($media = $this->getArg('media')) {
            $clear = (bool) $this->getArg('clear');
            $ids = $em->getConnection()->fetchFirstColumn('SELECT id FROM media ORDER BY id');
            $start = memory_get_usage(true);
            $n = 0;
            for ($pass = 0; $pass < (int) $media; $pass++) {
                foreach ($ids as $id) {
                    $m = $em->find(Media::class, $id);
                    $m?->getFilename();
                    if ($clear && ++$n % 100 === 0) {
                        $em->clear();
                    }
                }
                $em->clear(Media::class);
            }
            self::log(sprintf('MEDIA passes=%d ids=%d clear=%s mem_start=%.1fMB mem_end=%.1fMB peak=%.1fMB', $media, count($ids), $clear ? 'yes' : 'no', $start / 1048576, memory_get_usage(true) / 1048576, memory_get_peak_usage(true) / 1048576));
        }
        self::log('DONE');
    }
}
