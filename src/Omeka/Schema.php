<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

/**
 * The module's tables, created in install() and dropped in uninstall(): the
 * verdict of each media item (ADR-0003, SPEC-002) and the evidence of each
 * check (ADR-0005, SPEC-006). Text from the file goes into LONGTEXT columns,
 * so no length the file chooses can make a write fail. `trust` names the
 * trust settings a check used (SPEC-004, SPEC-002 amendment 1).
 *
 * Required by Module.php during install and uninstall, when Omeka S has not
 * put src/ on the autoloader (step 07): this file depends on nothing.
 */
final class Schema
{
    public const TABLE = 'content_credentials_verdict';

    public const EVIDENCE_TABLE = 'content_credentials_evidence';

    /**
     * @return list<string>
     */
    public static function create(): array
    {
        return [
            'CREATE TABLE content_credentials_verdict ('
            .'media_id INT NOT NULL, '
            .'state VARCHAR(16) NOT NULL, '
            .'format VARCHAR(255) DEFAULT NULL, '
            .'signer_issuer LONGTEXT DEFAULT NULL, '
            .'signer_common_name LONGTEXT DEFAULT NULL, '
            .'signed_at LONGTEXT DEFAULT NULL, '
            .'codes LONGTEXT NOT NULL, '
            .'remote_manifest_url LONGTEXT DEFAULT NULL, '
            .'reason VARCHAR(16) DEFAULT NULL, '
            .'verifier VARCHAR(32) DEFAULT NULL, '
            .'trust VARCHAR(64) DEFAULT NULL, '
            .'checked_sha256 CHAR(64) DEFAULT NULL, '
            .'checked_at DATETIME NOT NULL, '
            .'PRIMARY KEY(media_id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            'ALTER TABLE content_credentials_verdict ADD CONSTRAINT FK_CONTENT_CREDENTIALS_VERDICT_MEDIA '
            .'FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE',
            'CREATE TABLE content_credentials_evidence ('
            .'id INT AUTO_INCREMENT NOT NULL, '
            .'media_id INT NOT NULL, '
            .'checked_at DATETIME NOT NULL, '
            .'checked_sha256 CHAR(64) NOT NULL, '
            .'verifier VARCHAR(32) NOT NULL, '
            .'trust VARCHAR(64) DEFAULT NULL, '
            .'trust_sha256 CHAR(64) DEFAULT NULL, '
            .'sha256 CHAR(64) NOT NULL, '
            .'document LONGTEXT NOT NULL, '
            .'INDEX IDX_CONTENT_CREDENTIALS_EVIDENCE_MEDIA (media_id, id), '
            .'PRIMARY KEY(id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            'ALTER TABLE content_credentials_evidence ADD CONSTRAINT FK_CONTENT_CREDENTIALS_EVIDENCE_MEDIA '
            .'FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE',
        ];
    }

    public static function drop(): string
    {
        return 'DROP TABLE IF EXISTS content_credentials_evidence, content_credentials_verdict';
    }
}
