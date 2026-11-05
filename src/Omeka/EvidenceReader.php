<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Reads the evidence rows (SPEC-006). It says nothing about who may see
 * them: EvidenceController reads the media item first.
 *
 * @phpstan-type EvidenceRow array{id: int, media_id: int, checked_at: string, checked_sha256: string, verifier: string, trust: ?string, sha256: string, document: string}
 */
final class EvidenceReader
{
    private const COLUMNS = 'id, media_id, checked_at, checked_sha256, verifier, trust, sha256';

    public function __construct(private readonly Connection $connection) {}

    /** How many checks are kept; 0 when the table cannot be read, so the media page still opens. */
    public function count(int $mediaId): int
    {
        try {
            $count = $this->connection->fetchOne('SELECT COUNT(*) FROM '.Schema::EVIDENCE_TABLE.' WHERE media_id = ?', [$mediaId]);
        } catch (Throwable) {
            return 0;
        }

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * A media item's checks, newest first, without their documents.
     *
     * @return list<EvidenceRow>
     */
    public function rows(int $mediaId): array
    {
        $rows = [];
        foreach ($this->connection->fetchAllAssociative('SELECT '.self::COLUMNS.", '' AS document FROM ".Schema::EVIDENCE_TABLE.' WHERE media_id = ? ORDER BY id DESC', [$mediaId]) as $row) {
            $rows[] = self::typed($row);
        }

        return $rows;
    }

    /**
     * One check with its document, or null.
     *
     * @return EvidenceRow|null
     */
    public function row(int $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.', document FROM '.Schema::EVIDENCE_TABLE.' WHERE id = ?', [$id]);

        return $row === false ? null : self::typed($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return EvidenceRow
     */
    private static function typed(array $row): array
    {
        $text = static fn (string $column): string => is_scalar($row[$column] ?? null) ? (string) $row[$column] : '';
        $trust = $row['trust'] ?? null;

        return [
            'id' => (int) $text('id'),
            'media_id' => (int) $text('media_id'),
            'checked_at' => $text('checked_at'),
            'checked_sha256' => $text('checked_sha256'),
            'verifier' => $text('verifier'),
            'trust' => is_string($trust) ? $trust : null,
            'sha256' => $text('sha256'),
            'document' => $text('document'),
        ];
    }
}
