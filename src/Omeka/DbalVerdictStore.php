<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Check\Evidence;
use ContentCredentials\Check\Verdict;
use ContentCredentials\Check\VerdictStore;
use Doctrine\DBAL\Connection;

/**
 * Writes verdicts through Omeka S's database connection, with bound
 * parameters only (SPEC-002). Called after Doctrine's commit (step 10), so
 * it never flushes the entity manager.
 */
final class DbalVerdictStore implements VerdictStore
{
    public function __construct(private readonly Connection $connection) {}

    public function write(int $mediaId, Verdict $verdict, ?Evidence $evidence = null): void
    {
        if ($evidence === null) {
            $this->upsert($mediaId, $verdict);

            return;
        }
        // Stored together or not at all (ADR-0005). Evidence is only ever inserted.
        $this->connection->transactional(function () use ($mediaId, $verdict, $evidence): void {
            $this->connection->insert(Schema::EVIDENCE_TABLE, [
                'media_id' => $mediaId,
                'checked_at' => $verdict->checkedAt->format('Y-m-d H:i:s'),
                'checked_sha256' => $verdict->checkedSha256,
                'verifier' => $verdict->verifier,
                'trust' => $verdict->trust,
                'trust_sha256' => $evidence->trustSha256,
                'sha256' => hash('sha256', $evidence->document),
                'document' => $evidence->document,
            ]);
            $this->upsert($mediaId, $verdict);
        });
    }

    private function upsert(int $mediaId, Verdict $verdict): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '.Schema::TABLE.' (media_id, state, format, signer_issuer, signer_common_name, signed_at, codes, remote_manifest_url, reason, verifier, trust, checked_sha256, checked_at)'
            .' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            .' ON DUPLICATE KEY UPDATE state = VALUES(state), format = VALUES(format), signer_issuer = VALUES(signer_issuer),'
            .' signer_common_name = VALUES(signer_common_name), signed_at = VALUES(signed_at), codes = VALUES(codes),'
            .' remote_manifest_url = VALUES(remote_manifest_url), reason = VALUES(reason), verifier = VALUES(verifier), trust = VALUES(trust),'
            .' checked_sha256 = VALUES(checked_sha256), checked_at = VALUES(checked_at)',
            [
                $mediaId,
                $verdict->state,
                $verdict->format,
                $verdict->signerIssuer,
                $verdict->signerCommonName,
                $verdict->signedAt,
                json_encode($verdict->codes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $verdict->remoteManifestUrl,
                $verdict->reason,
                $verdict->verifier,
                $verdict->trust,
                $verdict->checkedSha256,
                $verdict->checkedAt->format('Y-m-d H:i:s'),
            ],
        );
    }
}
