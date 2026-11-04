<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Check\Recorder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Laminas\Log\Logger;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Media;
use Omeka\File\Store\Local;
use Provemark\C2paVerifier\Verifier\VerificationReport;
use Provemark\C2paVerifier\Verifier\Verifier;
use Throwable;
use UnexpectedValueException;

/**
 * Verifies new media after Doctrine has committed them (SPEC-002, ADR-0003).
 *
 * note() runs in `entity.persist.post`, inside the flush (step 09): it only
 * remembers a media item of a checked type with an original, and the first
 * time adds this object as a Doctrine postFlush listener. postFlush() runs
 * after the commit (step 10) and records each remembered item. Nothing
 * thrown here may reach Omeka S: inside the flush it would undo the upload,
 * after it it would answer HTTP 500 for an upload that was kept (step 10).
 */
final class AfterFlushListener
{
    public const MEDIA_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<Media> */
    private array $pending = [];

    private bool $attached = false;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Recorder $recorder,
        private readonly ?Local $store,
    ) {}

    public static function create(ServiceLocatorInterface $services, string $moduleDirectory): self
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $connection = $services->get('Omeka\Connection');
        $store = $services->get('Omeka\File\Store');
        $logger = $services->get('Omeka\Logger');
        if (! $entityManager instanceof EntityManager || ! $connection instanceof Connection) {
            throw new UnexpectedValueException('Omeka S did not provide its entity manager and connection.');
        }
        $check = new MediaCheck(
            static function () use ($moduleDirectory): string {
                VerifierLoader::register($moduleDirectory.'/vendor/provemark/c2pa-verifier/src');

                return Verifier::VERSION;
            },
            /** @param resource $stream */
            static fn ($stream): VerificationReport => (new Verifier)->verify($stream, null),
        );
        $recorder = new Recorder($check, new DbalVerdictStore($connection), static function (Throwable $e, int $mediaId) use ($logger): void {
            if ($logger instanceof Logger) {
                // The class only: a message may hold paths or SQL.
                $logger->warn(sprintf('Content Credentials: the check of media %d was not stored (%s).', $mediaId, $e::class));
            }
        });

        return new self($entityManager, $recorder, $store instanceof Local ? $store : null);
    }

    public function note(mixed $target): void
    {
        try {
            if (! $target instanceof Media || ! in_array($target->getMediaType(), self::MEDIA_TYPES, true) || $target->hasOriginal() !== true) {
                return;
            }
            $this->pending[] = $target;
            if (! $this->attached) {
                $this->entityManager->getEventManager()->addEventListener([Events::postFlush], $this);
                $this->attached = true;
            }
        } catch (Throwable) {
            // Inside the flush: never let anything undo the upload.
        }
    }

    public function postFlush(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as $media) {
            try {
                $id = $media->getId();
                if (is_int($id)) {
                    $this->recorder->record($id, $this->originalPath($media), PHP_VERSION);
                }
            } catch (Throwable) {
                // The recorder catches its own failures; this is the last guard.
            }
        }
    }

    private function originalPath(Media $media): ?string
    {
        $filename = $media->getFilename();
        if ($this->store === null || ! is_string($filename)) {
            return null;
        }

        return $this->store->getLocalPath('original/'.$filename);
    }
}
