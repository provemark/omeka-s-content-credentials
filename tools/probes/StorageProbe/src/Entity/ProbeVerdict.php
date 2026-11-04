<?php

declare(strict_types=1);

namespace StorageProbe\Entity;

use Omeka\Entity\Media;

/**
 * Measurement only (step 10): one row per media item, keyed by the media.
 *
 * @Entity
 *
 * @Table(name="storage_probe_verdict")
 */
class ProbeVerdict
{
    /**
     * @Id
     *
     * @OneToOne(targetEntity="Omeka\Entity\Media")
     *
     * @JoinColumn(name="media_id", nullable=false, onDelete="CASCADE")
     */
    protected Media $media;

    /** @Column(type="string", length=32) */
    protected string $how;

    public function __construct(Media $media, string $how)
    {
        $this->media = $media;
        $this->how = $how;
    }

    public function getHow(): string
    {
        return $this->how;
    }
}
