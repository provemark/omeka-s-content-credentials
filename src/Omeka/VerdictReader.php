<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Entity\StoredVerdict;
use Doctrine\ORM\EntityManager;
use Throwable;

/**
 * Reads a media item's row through the entity (SPEC-003, ADR-0003), as the
 * columns Presentation reads. A row that cannot be read at all comes back
 * without a state, so it shows as "Result unreadable", never as a verdict.
 */
final class VerdictReader
{
    public function __construct(private readonly EntityManager $entityManager) {}

    /**
     * The row for a representation's id, which Omeka types as mixed. A media
     * id is a positive integer; anything else shows as "Result unreadable".
     *
     * @return array<string, string|null>|null null when the media has no row
     */
    public function rowFor(mixed $id): ?array
    {
        if (is_int($id) && $id > 0) {
            return $this->row($id);
        }
        if (is_string($id) && preg_match('/^[1-9][0-9]*$/', $id) === 1) {
            return $this->row((int) $id);
        }

        return ['state' => null];
    }

    /**
     * @return array<string, string|null>|null null when the media has no row
     */
    public function row(int $mediaId): ?array
    {
        try {
            $stored = $this->entityManager->find(StoredVerdict::class, $mediaId);
        } catch (Throwable) {
            return ['state' => null];
        }
        if (! $stored instanceof StoredVerdict) {
            return null;
        }

        return [
            'state' => $stored->getState(),
            'format' => $stored->getFormat(),
            'signer_issuer' => $stored->getSignerIssuer(),
            'signer_common_name' => $stored->getSignerCommonName(),
            'signed_at' => $stored->getSignedAt(),
            'codes' => $stored->getCodes(),
            'remote_manifest_url' => $stored->getRemoteManifestUrl(),
            'reason' => $stored->getReason(),
            'verifier' => $stored->getVerifier(),
            'trust' => $stored->getTrust(),
            'ai_statement' => $stored->getAiStatement(),
            'checked_sha256' => $stored->getCheckedSha256(),
            'checked_at' => $stored->getCheckedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
