<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\RefreshTokenModel;
use Config\Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use UnexpectedValueException;

class AuthTokenService
{
    public function __construct(
        private readonly Auth $config,
        private readonly RefreshTokenModel $refreshTokens,
    ) {
    }

    /**
     * @return array{token: string, expiresIn: int}
     */
    public function issueAccessToken(int $userId): array
    {
        $now = time();

        $payload = [
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->config->accessTokenTtlSeconds,
            'jti' => bin2hex(random_bytes(16)),
            'iss' => $this->config->jwtIssuer,
        ];

        $token = JWT::encode($payload, $this->jwtSecret(), 'HS256');

        return [
            'token'     => $token,
            'expiresIn' => $this->config->accessTokenTtlSeconds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeAccessToken(string $token): array
    {
        $claims = (array) JWT::decode($token, new Key($this->jwtSecret(), 'HS256'));

        if (($claims['iss'] ?? '') !== $this->config->jwtIssuer) {
            throw new UnexpectedValueException('Emisor del token de acceso no válido.');
        }

        if (! isset($claims['sub'])) {
            throw new UnexpectedValueException('Sujeto del token de acceso no válido.');
        }

        return $claims;
    }

    public function issueRefreshToken(int $userId, string $ipAddress, string $userAgent): string
    {
        $token = bin2hex(random_bytes(64));

        $this->refreshTokens->insert([
            'user_id'    => $userId,
            'token_hash' => $this->hashToken($token),
            'expires_at' => $this->dateTimeFromNow($this->config->refreshTokenTtlSeconds),
            'ip_address' => $ipAddress !== '' ? $ipAddress : null,
            'user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null,
        ]);

        return $token;
    }

    /**
     * @return array{userId: int, token: string}|null
     */
    public function rotateRefreshToken(string $currentToken, string $ipAddress, string $userAgent): ?array
    {
        $record = $this->findValidRefreshToken($currentToken);

        if ($record === null) {
            return null;
        }

        $this->refreshTokens->update((int) $record['id'], [
            'revoked_at' => $this->now(),
        ]);

        $nextToken = $this->issueRefreshToken((int) $record['user_id'], $ipAddress, $userAgent);

        return [
            'userId' => (int) $record['user_id'],
            'token'  => $nextToken,
        ];
    }

    public function revokeRefreshToken(string $token): void
    {
        $record = $this->refreshTokens
            ->where('token_hash', $this->hashToken($token))
            ->first();

        if ($record === null || $record['revoked_at'] !== null) {
            return;
        }

        $this->refreshTokens->update((int) $record['id'], [
            'revoked_at' => $this->now(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findValidRefreshToken(string $token): ?array
    {
        $record = $this->refreshTokens
            ->where('token_hash', $this->hashToken($token))
            ->where('revoked_at', null)
            ->first();

        if ($record === null) {
            return null;
        }

        $expiresAt = strtotime((string) $record['expires_at']);
        if ($expiresAt === false || $expiresAt <= time()) {
            $this->refreshTokens->update((int) $record['id'], [
                'revoked_at' => $this->now(),
            ]);

            return null;
        }

        return $record;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function dateTimeFromNow(int $seconds): string
    {
        return gmdate('Y-m-d H:i:s', time() + $seconds);
    }

    private function jwtSecret(): string
    {
        if ($this->config->jwtSecret === '') {
            throw new RuntimeException('El secreto JWT no está configurado.');
        }

        return $this->config->jwtSecret;
    }
}


