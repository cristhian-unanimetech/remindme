<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->group('auth', static function (RouteCollection $routes): void {
        $routes->post('register', 'AuthController::register');
        $routes->post('login', 'AuthController::login');
        $routes->post('refresh', 'AuthController::refresh');
        $routes->post('logout', 'AuthController::logout');
        $routes->get('me', 'AuthController::me', ['filter' => 'jwtauth']);
        $routes->get('profile', 'AuthController::getProfile', ['filter' => 'jwtauth']);
        $routes->put('profile', 'AuthController::updateProfile', ['filter' => 'jwtauth']);
        $routes->put('password', 'AuthController::changePassword', ['filter' => 'jwtauth']);
        $routes->options('', 'AuthController::options');
        $routes->options('(:any)', 'AuthController::options');
    });

    $routes->get('memories', 'MemoriesController::index', ['filter' => 'jwtauth']);
    $routes->post('memories', 'MemoriesController::create', ['filter' => 'jwtauth']);
    $routes->get('memories/(:num)', 'MemoriesController::show/$1', ['filter' => 'jwtauth']);
    $routes->post('memories/(:num)', 'MemoriesController::update/$1', ['filter' => 'jwtauth']);
    $routes->put('memories/(:num)', 'MemoriesController::update/$1', ['filter' => 'jwtauth']);
    $routes->delete('memories/(:num)', 'MemoriesController::delete/$1', ['filter' => 'jwtauth']);
    $routes->options('memories', 'MemoriesController::options');
    $routes->options('memories/(:any)', 'MemoriesController::options');

    $routes->get('tags', 'TagsController::index', ['filter' => 'jwtauth']);
    $routes->options('tags', 'TagsController::options');

    $routes->post('ai/assist', 'AiController::assist', ['filter' => 'jwtauth']);
    $routes->options('ai/(:any)', 'AiController::options');
});
