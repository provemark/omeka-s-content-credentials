<?php

declare(strict_types=1);

namespace ContentCredentials\Entity;

use DateTime;
use Omeka\Entity\Media;

/**
 * The table of ADR-0003 as Doctrine maps it, for reading (SPEC-002). It is
 * written through DBAL (Omeka\DbalVerdictStore), never through the entity
 * manager. No proxies: nothing loads it lazily (step 10).
 *
 * @Entity
 *
 * @Table(name="content_credentials_verdict")
 */
class StoredVerdict
{
    /**
     * @Id
     *
     * @OneToOne(targetEntity="Omeka\Entity\Media")
     *
     * @JoinColumn(name="media_id", nullable=false, onDelete="CASCADE")
     */
    protected Media $media;

    /** @Column(type="string", length=16) */
    protected string $state;

    /** @Column(type="string", length=255, nullable=true) */
    protected ?string $format = null;

    /** @Column(name="signer_issuer", type="text", nullable=true) */
    protected ?string $signerIssuer = null;

    /** @Column(name="signer_common_name", type="text", nullable=true) */
    protected ?string $signerCommonName = null;

    /** @Column(name="signed_at", type="text", nullable=true) */
    protected ?string $signedAt = null;

    /** @Column(type="text") */
    protected string $codes;

    /** @Column(name="remote_manifest_url", type="text", nullable=true) */
    protected ?string $remoteManifestUrl = null;

    /** @Column(type="string", length=16, nullable=true) */
    protected ?string $reason = null;

    /** @Column(type="string", length=32, nullable=true) */
    protected ?string $verifier = null;

    /** @Column(type="string", length=64, nullable=true) */
    protected ?string $trust = null;

    /** @Column(name="ai_statement", type="string", length=16, nullable=true) */
    protected ?string $aiStatement = null;

    /** @Column(name="checked_sha256", type="string", length=64, nullable=true) */
    protected ?string $checkedSha256 = null;

    /** @Column(name="checked_at", type="datetime") */
    protected DateTime $checkedAt;

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getSignerIssuer(): ?string
    {
        return $this->signerIssuer;
    }

    public function getSignerCommonName(): ?string
    {
        return $this->signerCommonName;
    }

    public function getSignedAt(): ?string
    {
        return $this->signedAt;
    }

    /**
     * The codes as stored: a JSON list.
     */
    public function getCodes(): string
    {
        return $this->codes;
    }

    public function getRemoteManifestUrl(): ?string
    {
        return $this->remoteManifestUrl;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getVerifier(): ?string
    {
        return $this->verifier;
    }

    public function getTrust(): ?string
    {
        return $this->trust;
    }

    public function getAiStatement(): ?string
    {
        return $this->aiStatement;
    }

    public function getCheckedSha256(): ?string
    {
        return $this->checkedSha256;
    }

    public function getCheckedAt(): DateTime
    {
        return $this->checkedAt;
    }
}
