<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\GroqService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class AiController extends ApiController
{
    private GroqService $groq;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->groq = new GroqService();
    }

    public function assist(): ResponseInterface
    {
        $userId = $this->authenticatedUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $body   = $this->request->getJSON(true) ?? [];
        $action  = trim((string) ($body['action'] ?? ''));
        $content = trim((string) ($body['content'] ?? ''));
        $title   = trim((string) ($body['title'] ?? ''));

        if (! in_array($action, ['improve_text', 'improve_title', 'suggest_tags'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'message' => 'Acción no válida.',
            ]);
        }

        if ($action === 'improve_text' && $content === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'message' => 'El campo contenido es obligatorio para esta acción.',
            ]);
        }

        if ($action === 'improve_title' && $title === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'message' => 'El campo título es obligatorio para esta acción.',
            ]);
        }

        if ($action === 'suggest_tags' && ($title === '' && $content === '')) {
            return $this->response->setStatusCode(422)->setJSON([
                'message' => 'Se necesita al menos título o contenido para sugerir etiquetas.',
            ]);
        }

        try {
            $suggestion = match ($action) {
                'improve_text'  => $this->groq->improveText($content),
                'improve_title' => $this->groq->improveTitle($title, $content),
                'suggest_tags'  => $this->groq->suggestTags($title, $content),
            };
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(503)->setJSON([
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $message = ENVIRONMENT === 'development'
                ? $e->getMessage()
                : 'El servicio de IA no está disponible en este momento.';

            return $this->response->setStatusCode(503)->setJSON(['message' => $message]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'suggestion' => $suggestion,
        ]);
    }

    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }
}
