<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\AuthTokenService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class ApiController extends BaseController
{
    protected AuthTokenService $tokenService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->tokenService = service('authTokenService');
    }

    protected function authenticatedUserId(): ?int
    {
        $token = $this->extractBearerToken();
        if ($token === null) {
            return null;
        }

        try {
            $claims = $this->tokenService->decodeAccessToken($token);
        } catch (\Throwable) {
            return null;
        }

        $userId = (int) ($claims['sub'] ?? 0);

        return $userId > 0 ? $userId : null;
    }

    protected function unauthorizedResponse(): ResponseInterface
    {
        return $this->response->setStatusCode(401)->setJSON([
            'message' => 'No autorizado.',
        ]);
    }

    protected function validationErrorResponse(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'message' => 'No se ha podido validar.',
            'errors'  => $errors,
        ]);
    }

    private function extractBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}


