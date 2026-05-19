<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Models\TagModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class TagsController extends ApiController
{
    private TagModel $tags;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->tags = model(TagModel::class);
    }

    public function index(): ResponseInterface
    {
        $userId = $this->authenticatedUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $rows = $this->tags
            ->select('tags.name')
            ->join('memory_tags', 'memory_tags.tag_id = tags.id')
            ->join('memories', 'memories.id = memory_tags.memory_id')
            ->where('memories.user_id', $userId)
            ->groupBy('tags.id')
            ->orderBy('tags.name', 'ASC')
            ->findAll();

        $data = array_map(static fn (array $row): string => (string) $row['name'], $rows);

        return $this->response->setJSON([
            'data' => $data,
        ]);
    }

    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }
}
