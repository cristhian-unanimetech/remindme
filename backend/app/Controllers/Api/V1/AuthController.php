<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\AuthTokenService;
use App\Models\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Auth as AuthConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AuthController extends BaseController
{
    private UserModel $users;
    private AuthTokenService $tokenService;
    private AuthConfig $authConfig;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->users        = model(UserModel::class);
        $this->tokenService = service('authTokenService');
        $this->authConfig   = config('Auth');
    }

    public function register(): ResponseInterface
    {
        $data = $this->requestData();
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));

        $rules = [
            'name'                  => 'required|min_length[2]|max_length[100]',
            'email'                 => 'required|valid_email|max_length[150]|is_unique[users.email]',
            'password'              => 'required|min_length[8]|max_length[72]|regex_match[/^(?=.*[A-Za-z])(?=.*\d).+$/]',
            'password_confirmation' => 'required|matches[password]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse();
        }

        $userId = $this->users->insert([
            'name'     => trim((string) $data['name']),
            'email'    => $data['email'],
            'password' => $this->hashPassword((string) $data['password']),
        ], true);

        if (! is_int($userId)) {
            return $this->response->setStatusCode(500)->setJSON([
                'message' => 'No se ha podido crear el usuario.',
            ]);
        }

        $user = $this->users->find($userId);
        if (! is_array($user)) {
            return $this->response->setStatusCode(500)->setJSON([
                'message' => 'Usuario no encontrado tras el registro.',
            ]);
        }

        return $this->responseWithAuth($user, 201);
    }

    public function login(): ResponseInterface
    {
        $data = $this->requestData();
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));

        $rules = [
            'email'    => 'required|valid_email|max_length[150]',
            'password' => 'required|min_length[8]|max_length[72]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse();
        }

        $user = $this->users->where('email', $data['email'])->first();

        if (! is_array($user) || ! password_verify((string) $data['password'], (string) $user['password'])) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Credenciales incorrectas.',
            ]);
        }

        return $this->responseWithAuth($user);
    }

    public function refresh(): ResponseInterface
    {
        $currentRefreshToken = (string) $this->request->getCookie($this->authConfig->refreshCookieName);
        if ($currentRefreshToken === '') {
            return $this->unauthorizedResponse();
        }

        $rotated = $this->tokenService->rotateRefreshToken(
            $currentRefreshToken,
            $this->request->getIPAddress(),
            $this->requestUserAgent()
        );

        if ($rotated === null) {
            $this->clearRefreshCookie();

            return $this->unauthorizedResponse();
        }

        $user = $this->users->find($rotated['userId']);
        if (! is_array($user)) {
            $this->clearRefreshCookie();

            return $this->unauthorizedResponse();
        }

        $this->setRefreshCookie($rotated['token']);
        $access = $this->tokenService->issueAccessToken((int) $user['id']);

        return $this->response->setJSON([
            'user'        => $this->mapUser($user),
            'accessToken' => $access['token'],
            'expiresIn'   => $access['expiresIn'],
        ]);
    }

    public function logout(): ResponseInterface
    {
        $refreshToken = (string) $this->request->getCookie($this->authConfig->refreshCookieName);
        if ($refreshToken !== '') {
            $this->tokenService->revokeRefreshToken($refreshToken);
        }

        $this->clearRefreshCookie();

        return $this->response->setJSON([
            'message' => 'Sesión cerrada.',
        ]);
    }

    public function me(): ResponseInterface
    {
        $token = $this->extractBearerToken();
        if ($token === null) {
            return $this->unauthorizedResponse();
        }

        try {
            $claims = $this->tokenService->decodeAccessToken($token);
        } catch (\Throwable) {
            return $this->unauthorizedResponse();
        }

        $userId = (int) ($claims['sub'] ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        $user = $this->users->find($userId);
        if (! is_array($user)) {
            return $this->unauthorizedResponse();
        }

        return $this->response->setJSON([
            'user' => $this->mapUser($user),
        ]);
    }

    public function getProfile(): ResponseInterface
    {
        $userId = $this->resolveUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $user = $this->users->find($userId);
        if (! is_array($user)) {
            return $this->unauthorizedResponse();
        }

        $memoriesCount = model(\App\Models\MemoryModel::class)
            ->where('user_id', $userId)
            ->countAllResults();

        return $this->response->setJSON([
            'user'          => $this->mapUser($user),
            'memoriesCount' => (int) $memoriesCount,
        ]);
    }

    public function updateProfile(): ResponseInterface
    {
        $userId = $this->resolveUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $data  = $this->requestData();
        $rules = ['name' => 'required|min_length[2]|max_length[100]'];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse();
        }

        $this->users->update($userId, [
            'name' => trim((string) $data['name']),
        ]);

        $updated = $this->users->find($userId);

        return $this->response->setJSON([
            'user'    => $this->mapUser(is_array($updated) ? $updated : []),
            'message' => 'Perfil actualizado.',
        ]);
    }

    public function changePassword(): ResponseInterface
    {
        $userId = $this->resolveUserId();
        if ($userId === null) {
            return $this->unauthorizedResponse();
        }

        $data  = $this->requestData();
        $rules = [
            'current_password'          => 'required',
            'new_password'              => 'required|min_length[8]|max_length[72]|regex_match[/^(?=.*[A-Za-z])(?=.*\d).+$/]',
            'new_password_confirmation' => 'required|matches[new_password]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationErrorResponse();
        }

        $user = $this->users->find($userId);
        if (! is_array($user)) {
            return $this->unauthorizedResponse();
        }

        if (! password_verify((string) $data['current_password'], (string) $user['password'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'message' => 'No se ha podido validar.',
                'errors'  => ['current_password' => 'La contraseña actual no es correcta.'],
            ]);
        }

        $this->users->update($userId, [
            'password' => $this->hashPassword((string) $data['new_password']),
        ]);

        return $this->response->setJSON([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    private function responseWithAuth(array $user, int $statusCode = 200): ResponseInterface
    {
        $accessToken  = $this->tokenService->issueAccessToken((int) $user['id']);
        $refreshToken = $this->tokenService->issueRefreshToken(
            (int) $user['id'],
            $this->request->getIPAddress(),
            $this->requestUserAgent()
        );

        $this->setRefreshCookie($refreshToken);

        return $this->response->setStatusCode($statusCode)->setJSON([
            'user'        => $this->mapUser($user),
            'accessToken' => $accessToken['token'],
            'expiresIn'   => $accessToken['expiresIn'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }

        $post = $this->request->getPost();

        return is_array($post) ? $post : [];
    }

    private function requestUserAgent(): string
    {
        return mb_substr((string) $this->request->getHeaderLine('User-Agent'), 0, 255);
    }

    private function setRefreshCookie(string $token): void
    {
        $this->response->setCookie(
            $this->authConfig->refreshCookieName,
            $token,
            $this->authConfig->refreshTokenTtlSeconds,
            $this->authConfig->refreshCookieDomain,
            $this->authConfig->refreshCookiePath,
            '',
            $this->authConfig->refreshCookieSecure,
            true,
            $this->authConfig->refreshCookieSameSite
        );
    }

    private function clearRefreshCookie(): void
    {
        $this->response->deleteCookie(
            $this->authConfig->refreshCookieName,
            $this->authConfig->refreshCookieDomain,
            $this->authConfig->refreshCookiePath
        );
    }

    /**
     * @return array{id: int, name: string, email: string, createdAt: string|null, updatedAt: string|null}
     */
    private function mapUser(array $user): array
    {
        return [
            'id'        => (int) $user['id'],
            'name'      => (string) $user['name'],
            'email'     => (string) $user['email'],
            'createdAt' => isset($user['created_at']) ? (string) $user['created_at'] : null,
            'updatedAt' => isset($user['updated_at']) ? (string) $user['updated_at'] : null,
        ];
    }

    private function unauthorizedResponse(): ResponseInterface
    {
        return $this->response->setStatusCode(401)->setJSON([
            'message' => 'No autorizado.',
        ]);
    }

    private function validationErrorResponse(): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'message' => 'No se ha podido validar.',
            'errors'  => $this->validator->getErrors(),
        ]);
    }

    private function resolveUserId(): ?int
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

    private function extractBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            try {
                $argonHash = password_hash($password, PASSWORD_ARGON2ID);
                if (is_string($argonHash) && $argonHash !== '') {
                    return $argonHash;
                }
            } catch (\Throwable) {
            }
        }

        $bcryptHash = password_hash($password, PASSWORD_BCRYPT);
        if (! is_string($bcryptHash) || $bcryptHash === '') {
            throw new RuntimeException('No se ha podido hashear la contraseña.');
        }

        return $bcryptHash;
    }
}
