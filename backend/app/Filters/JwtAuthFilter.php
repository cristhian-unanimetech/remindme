<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $this->unauthorized();
        }

        try {
            $claims = service('authTokenService')->decodeAccessToken(trim($matches[1]));
        } catch (\Throwable) {
            return $this->unauthorized();
        }

        if (! isset($claims['sub']) || (int) $claims['sub'] <= 0) {
            return $this->unauthorized();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function unauthorized(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON(['message' => 'No autorizado.']);
    }
}
