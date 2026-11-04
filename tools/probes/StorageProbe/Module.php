<?php

declare(strict_types=1);

namespace StorageProbe;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Media;
use Omeka\Module\AbstractModule;
use RuntimeException;
use StorageProbe\Entity\ProbeVerdict;

/**
 * Measurement only (M1.2, notes/step-10-storage-and-after-flush.md).
 *
 * New media are noted in entity.persist.post; a Doctrine postFlush listener,
 * registered on the first such note, then writes one row per media. The
 * media's source name chooses the variant: "ormflush" writes through the
 * entity manager, "throw" throws, anything else writes through DBAL.
 */
final class Module extends AbstractModule
{
    private const LOG = '/tmp/storage-probe.log';

    /** @var list<Media> */
    private array $pending = [];

    private bool $registered = false;

    public function getConfig(): array
    {
        return require __DIR__.'/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $services->get('Omeka\Connection')->executeStatement(
            'CREATE TABLE storage_probe_verdict (media_id INT NOT NULL, how VARCHAR(32) NOT NULL, PRIMARY KEY(media_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
        $services->get('Omeka\Connection')->executeStatement(
            'ALTER TABLE storage_probe_verdict ADD CONSTRAINT FK_STORAGE_PROBE_MEDIA FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE'
        );
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $services->get('Omeka\Connection')->executeStatement('DROP TABLE IF EXISTS storage_probe_verdict');
    }

    public function attachListeners(SharedEventManagerInterface $shared): void
    {
        $shared->attach('Omeka\Entity\Media', 'entity.persist.post', function (Event $event): void {
            $media = $event->getTarget();
            if (! $media instanceof Media) {
                return;
            }
            $this->pending[] = $media;
            $this->log(['event' => 'entity.persist.post', 'media_id' => $media->getId()]);
            if (! $this->registered) {
                $this->registered = true;
                $em = $this->getServiceLocator()->get('Omeka\EntityManager');
                $em->getEventManager()->addEventListener(Events::postFlush, $this);
            }
        });

        // The DQL join M2's filter would need: /api/media?probe_how=dbal
        $shared->attach('Omeka\Api\Adapter\MediaAdapter', 'api.search.query', function (Event $event): void {
            $query = $event->getParam('request')->getContent();
            if (! isset($query['probe_how'])) {
                return;
            }
            $qb = $event->getParam('queryBuilder');
            $adapter = $event->getTarget();
            $alias = $adapter->createAlias();
            $qb->innerJoin(ProbeVerdict::class, $alias, 'WITH', "$alias.media = omeka_root.id")
                ->andWhere($qb->expr()->eq("$alias.how", $adapter->createNamedParameter($qb, $query['probe_how'])));
        });
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $conn = $em->getConnection();
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as $media) {
            $source = (string) $media->getSource();
            $how = str_contains($source, 'ormflush') ? 'ormflush' : (str_contains($source, 'throw') ? 'throw' : 'dbal');
            $line = [
                'event' => 'postFlush',
                'media_id' => $media->getId(),
                'how' => $how,
                'tx_active' => $conn->isTransactionActive(),
                'tx_level' => $conn->getTransactionNestingLevel(),
                'visible_elsewhere' => $this->visibleOnOtherConnection($media->getId()),
            ];
            try {
                if ($how === 'throw') {
                    $this->log($line);
                    throw new RuntimeException('probe: thrown in postFlush');
                }
                if ($how === 'ormflush') {
                    $em->persist(new ProbeVerdict($media, 'ormflush'));
                    $em->flush();
                } else {
                    $conn->insert('storage_probe_verdict', ['media_id' => $media->getId(), 'how' => 'dbal']);
                }
                $line['written'] = true;
            } catch (RuntimeException $e) {
                if ($how === 'throw') {
                    throw $e;
                }
                $line['written'] = false;
                $line['error'] = get_class($e).': '.$e->getMessage();
            }
            $this->log($line);
        }
    }

    private function visibleOnOtherConnection(?int $mediaId): ?bool
    {
        if ($mediaId === null) {
            return null;
        }
        $ini = parse_ini_file(OMEKA_PATH.'/config/database.ini');
        $pdo = new \PDO("mysql:host={$ini['host']};dbname={$ini['dbname']}", $ini['user'], $ini['password']);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM media WHERE id = ?');
        $stmt->execute([$mediaId]);

        return (int) $stmt->fetchColumn() === 1;
    }

    /** @param array<string, mixed> $line */
    private function log(array $line): void
    {
        $line['uri'] = $_SERVER['REQUEST_URI'] ?? 'cli';
        file_put_contents(self::LOG, json_encode($line, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
    }
}
