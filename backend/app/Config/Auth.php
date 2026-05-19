<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Auth extends BaseConfig
{
    public string $jwtSecret = '';

    public string $jwtIssuer = 'remindme-api';

    public int $accessTokenTtlSeconds = 900;

    public int $refreshTokenTtlSeconds = 604800;

    public string $refreshCookieName = 'refresh_token';

    public bool $refreshCookieSecure = false;

    public string $refreshCookieSameSite = 'Lax';

    public string $refreshCookieDomain = '';

    public string $refreshCookiePath = '/';

    public function __construct()
    {
        parent::__construct();

        $this->jwtSecret             = (string) env('auth.jwtSecret', env('encryption.key', 'remindme-dev-secret'));
        $this->jwtIssuer             = (string) env('auth.jwtIssuer', $this->jwtIssuer);
        $this->accessTokenTtlSeconds = (int) env('auth.accessTokenTtlSeconds', $this->accessTokenTtlSeconds);
        $this->refreshTokenTtlSeconds = (int) env('auth.refreshTokenTtlSeconds', $this->refreshTokenTtlSeconds);
        $this->refreshCookieName     = (string) env('auth.refreshCookieName', $this->refreshCookieName);
        $this->refreshCookieSecure   = $this->toBool(env('auth.refreshCookieSecure'), ENVIRONMENT === 'production');
        $this->refreshCookieSameSite = (string) env('auth.refreshCookieSameSite', $this->refreshCookieSameSite);
        $this->refreshCookieDomain   = (string) env('auth.refreshCookieDomain', $this->refreshCookieDomain);
        $this->refreshCookiePath     = (string) env('auth.refreshCookiePath', $this->refreshCookiePath);
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }
}
