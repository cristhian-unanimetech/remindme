<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AuthControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $migrate = true;

    public function testRegisterCreatesUserAndReturnsTokens(): void
    {
        $response = $this->withBodyFormat('json')->post('api/v1/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201);
        $response->assertCookie('refresh_token');

        $json = json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('test@example.com', $json['user']['email']);
        $this->assertArrayHasKey('accessToken', $json);
        $this->assertSame(900, $json['expiresIn']);
    }

    public function testRegisterFailsWithWeakPassword(): void
    {
        $response = $this->withBodyFormat('json')->post('api/v1/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'weak@example.com',
            'password'              => 'weakpass',
            'password_confirmation' => 'weakpass',
        ]);

        $response->assertStatus(422);
        $response->assertJSONFragment([
            'message' => 'Validation failed.',
        ]);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $this->withBodyFormat('json')->post('api/v1/auth/register', [
            'name'                  => 'Login User',
            'email'                 => 'login@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertStatus(201);

        $response = $this->withBodyFormat('json')->post('api/v1/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'Password000',
        ]);

        $response->assertStatus(401);
        $response->assertJSONFragment([
            'message' => 'Invalid credentials.',
        ]);
    }

    public function testRefreshRotatesTokenAndRejectsOldOne(): void
    {
        $register = $this->withBodyFormat('json')->post('api/v1/auth/register', [
            'name'                  => 'Rotate User',
            'email'                 => 'rotate@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ]);
        $register->assertStatus(201);

        $firstCookie = $register->response()->getCookie('refresh_token');
        $this->assertNotNull($firstCookie);
        $firstToken = (string) $firstCookie->getValue();

        $refresh = $this->withHeaders([
            'Cookie' => 'refresh_token=' . $firstToken,
        ])->withBodyFormat('json')->post('api/v1/auth/refresh', []);

        $refresh->assertStatus(200);
        $refresh->assertCookie('refresh_token');

        $secondCookie = $refresh->response()->getCookie('refresh_token');
        $this->assertNotNull($secondCookie);
        $secondToken = (string) $secondCookie->getValue();

        $this->assertNotSame($firstToken, $secondToken);

        $oldRefresh = $this->withHeaders([
            'Cookie' => 'refresh_token=' . $firstToken,
        ])->withBodyFormat('json')->post('api/v1/auth/refresh', []);

        $oldRefresh->assertStatus(401);
    }

    public function testMeRequiresValidAccessToken(): void
    {
        $register = $this->withBodyFormat('json')->post('api/v1/auth/register', [
            'name'                  => 'Me User',
            'email'                 => 'me@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ]);
        $register->assertStatus(201);

        $json = json_decode((string) $register->getJSON(), true, 512, JSON_THROW_ON_ERROR);
        $accessToken = (string) $json['accessToken'];

        $authorized = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get('api/v1/auth/me');

        $authorized->assertStatus(200);
        $authorized->assertJSONFragment([
            'email' => 'me@example.com',
        ]);

        $unauthorized = $this->get('api/v1/auth/me');
        $unauthorized->assertStatus(401);
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $register = $this->withBodyFormat('json')->post('api/v1/auth/register', [
            'name'                  => 'Logout User',
            'email'                 => 'logout@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ]);
        $register->assertStatus(201);

        $cookie = $register->response()->getCookie('refresh_token');
        $this->assertNotNull($cookie);
        $refreshToken = (string) $cookie->getValue();

        $logout = $this->withHeaders([
            'Cookie' => 'refresh_token=' . $refreshToken,
        ])->withBodyFormat('json')->post('api/v1/auth/logout', []);

        $logout->assertStatus(200);
        $logout->assertCookie('refresh_token');

        $refreshAfterLogout = $this->withHeaders([
            'Cookie' => 'refresh_token=' . $refreshToken,
        ])->withBodyFormat('json')->post('api/v1/auth/refresh', []);

        $refreshAfterLogout->assertStatus(401);
    }
}
