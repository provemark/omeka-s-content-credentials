<?php

declare(strict_types=1);

namespace EventProbe;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Entity\Media;
use Omeka\Module\AbstractModule;

/**
 * Measurement only (M1.1, notes/step-09-events-and-original.md): logs one
 * JSON line per event that concerns media, with what is known about the
 * media and its original at that moment. Never part of the module.
 */
final class Module extends AbstractModule
{
    private const LOG = '/tmp/event-probe.log';

    public function attachListeners(SharedEventManagerInterface $shared): void
    {
        foreach (['entity.persist.pre', 'entity.persist.post', 'entity.update.post', 'entity.remove.pre', 'entity.remove.post'] as $name) {
            $shared->attach('Omeka\Entity\Media', $name, fn (Event $e) => $this->log($e));
        }
        foreach (['api.create.pre', 'api.create.post', 'api.update.post', 'api.delete.post', 'api.hydrate.post', 'api.batch_create.post'] as $name) {
            $shared->attach('Omeka\Api\Adapter\MediaAdapter', $name, fn (Event $e) => $this->log($e));
            $shared->attach('Omeka\Api\Adapter\ItemAdapter', $name, fn (Event $e) => $this->log($e));
        }
        foreach (['media.ingest_file.pre', 'media.ingest_file.post'] as $name) {
            $shared->attach('Omeka\File\TempFile', $name, fn (Event $e) => $this->log($e));
        }
    }

    private function log(Event $event): void
    {
        $target = $event->getTarget();
        $line = [
            'event' => $event->getName(),
            'target' => is_object($target) ? get_class($target) : gettype($target),
            'pid' => getmypid(),
            'uri' => $_SERVER['REQUEST_URI'] ?? 'cli',
        ];
        $media = $target instanceof Media ? $target : null;
        $response = $event->getParam('response');
        if ($media === null && is_object($response) && method_exists($response, 'getContent')) {
            $content = $response->getContent();
            $line['response'] = is_object($content) ? get_class($content) : gettype($content);
        }
        $entity = $event->getParam('entity');
        if ($media === null && $entity instanceof Media) {
            $media = $entity;
        }
        $tempFile = $event->getParam('tempFile');
        if (is_object($tempFile) && method_exists($tempFile, 'getTempPath')) {
            $path = $tempFile->getTempPath();
            $line['temp_path'] = $path;
            $line['temp_exists'] = is_file($path);
            $line['temp_sha256'] = is_file($path) ? hash_file('sha256', $path) : null;
        }
        if ($media !== null) {
            $original = $media->getStorageId() !== null
                ? OMEKA_PATH.'/files/original/'.$media->getStorageId().($media->getExtension() !== null ? '.'.$media->getExtension() : '')
                : null;
            $line += [
                'media_id' => $media->getId(),
                'ingester' => $media->getIngester(),
                'storage_id' => $media->getStorageId(),
                'media_sha256' => $media->getSha256(),
                'original_exists' => $original !== null && is_file($original),
                'original_sha256' => $original !== null && is_file($original) ? hash_file('sha256', $original) : null,
            ];
        }
        file_put_contents(self::LOG, json_encode($line, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
    }
}
