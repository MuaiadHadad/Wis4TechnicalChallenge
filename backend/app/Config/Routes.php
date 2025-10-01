<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Default route
$routes->get('/', 'Home::index');

// Authentication routes
$routes->group('auth', function($routes) {
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout');
    $routes->get('profile', 'AuthController::profile');
    $routes->get('check', 'AuthController::checkAuth');
});

// Task management routes (Admin only)
$routes->group('tasks', function($routes) {
    $routes->get('/', 'TaskController::index');
    $routes->post('/', 'TaskController::create');
    $routes->get('show/(:num)', 'TaskController::show/$1');
    $routes->get('collaborators', 'TaskController::getCollaborators');
    $routes->get('my-tasks', 'TaskController::myTasks');
});

// Task execution routes (Collaborator)
$routes->group('executions', function($routes) {
    $routes->get('/', 'TaskExecutionController::index');
    $routes->post('submit', 'TaskExecutionController::submit');
    $routes->get('show/(:num)', 'TaskExecutionController::show/$1');
    $routes->get('download/(:num)', 'TaskExecutionController::downloadFile/$1');
});

// API routes with CORS handling
$routes->options('(:any)', function() {
    return service('response')->setHeader('Access-Control-Allow-Origin', '*')
                             ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                             ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});
