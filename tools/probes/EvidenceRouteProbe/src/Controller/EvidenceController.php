<?php

declare(strict_types=1);

namespace EvidenceRouteProbe\Controller;

use Doctrine\DBAL\Connection;
use EvidenceRouteProbe\Module;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Measurement only (M5.2). `raw` serves the latest document without asking
 * whether the user may see the media item; `show` and `download` read the
 * media item through Omeka's API first, which applies Omeka's visibility.
 */
final class EvidenceController extends AbstractActionController
{
    public function __construct(private readonly Connection $connection) {}

    public function rawAction(): Response
    {
        return $this->json($this->latest((int) $this->params('media-id')), false);
    }

    public function showAction(): ViewModel
    {
        $media = $this->api()->read('media', (int) $this->params('media-id'))->getContent();
        $view = new ViewModel(['media' => $media, 'row' => $this->latest($media->id())]);
        $view->setTemplate('evidence-route-probe/evidence/show');

        return $view;
    }

    public function downloadAction(): Response
    {
        $media = $this->api()->read('media', (int) $this->params('media-id'))->getContent();

        return $this->json($this->latest($media->id()), true);
    }

    /** @return array{id: int, sha256: string, document: string}|null */
    private function latest(int $mediaId): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT id, sha256, document FROM '.Module::TABLE.' WHERE media_id = ? ORDER BY id DESC LIMIT 1', [$mediaId]);

        return $row === false ? null : ['id' => (int) $row['id'], 'sha256' => (string) $row['sha256'], 'document' => (string) $row['document']];
    }

    /** @param array{id: int, sha256: string, document: string}|null $row */
    private function json(?array $row, bool $attachment): Response
    {
        $response = $this->getResponse();
        if ($row === null) {
            $response->setStatusCode(404);
            $response->setContent('no evidence');

            return $response;
        }
        $response->setContent($row['document']);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', 'application/json');
        $headers->addHeaderLine('X-Probe-Sha256', $row['sha256']);
        if ($attachment) {
            $headers->addHeaderLine('Content-Disposition', 'attachment; filename="media-'.(int) $this->params('media-id').'-evidence-'.$row['id'].'.json"');
        }

        return $response;
    }
}
