<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

// ---------------------------------------------------------------------------
// Autentikasi (hanya tamu)
// ---------------------------------------------------------------------------
$routes->get('login', 'Auth\LoginController::loginForm', ['filter' => 'guest']);
$routes->post('login', 'Auth\LoginController::attempt', ['filter' => 'guest']);
$routes->post('logout', 'Auth\LoginController::logout', ['filter' => 'auth']);

// ---------------------------------------------------------------------------
// Halaman (semua peran terautentikasi kecuali yang ditandai)
// ---------------------------------------------------------------------------
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('denah', 'FloorController::index');
    $routes->get('ruangan', 'RoomController::index');
    $routes->get('ruangan/(:num)', 'RoomController::show/$1');
    $routes->get('perangkat', 'DeviceController::index');
    $routes->get('perangkat/(:num)', 'DeviceController::show/$1');
    $routes->get('otomatisasi', 'AutomationController::index');
    $routes->get('otomatisasi/(:num)', 'AutomationController::show/$1');
    $routes->get('log', 'ActivityLogController::index');

    // Halaman admin
    $routes->get('pengguna', 'UserController::index', ['filter' => 'role:admin']);
    $routes->get('pengaturan', 'SettingController::index', ['filter' => 'role:admin']);
});

// ---------------------------------------------------------------------------
// API — semua peran terautentikasi (baca)
// ---------------------------------------------------------------------------
$routes->group('api', ['filter' => 'auth'], static function ($routes) {
    $routes->get('dashboard', 'Api\DashboardController::index');
    $routes->get('floors', 'Api\FloorController::index');
    $routes->get('floors/(:num)', 'Api\FloorController::show/$1');

    $routes->get('rooms', 'Api\RoomController::index');
    $routes->get('rooms/(:num)', 'Api\RoomController::show/$1');

    $routes->get('devices', 'Api\DeviceController::index');
    $routes->get('devices/(:num)', 'Api\DeviceController::show/$1');
    $routes->get('devices/(:num)/commands', 'Api\DeviceController::commands/$1');

    $routes->get('overrides', 'Api\OverrideController::index');

    $routes->get('automation/rules', 'Api\AutomationController::index');
    $routes->get('automation/executions', 'Api\AutomationController::executions');

    $routes->get('activity-logs', 'Api\ActivityLogController::index');

    // Preferensi tema — semua peran
    $routes->put('preferences', 'Api\PreferenceController::update');
    $routes->post('preferences', 'Api\PreferenceController::update');
});

// ---------------------------------------------------------------------------
// API — kontrol (admin & operator)
// ---------------------------------------------------------------------------
$routes->group('api', ['filter' => ['auth', 'role:admin,operator']], static function ($routes) {
    $routes->post('rooms/(:num)/occupancy', 'Api\RoomController::setOccupancy/$1');
    $routes->post('rooms/(:num)/mode', 'Api\RoomController::setMode/$1');
    $routes->post('devices/(:num)/command', 'Api\DeviceController::command/$1');
});

// ---------------------------------------------------------------------------
// API — manajemen (admin)
// ---------------------------------------------------------------------------
$routes->group('api', ['filter' => ['auth', 'role:admin']], static function ($routes) {
    $routes->post('rooms', 'Api\RoomController::store');
    $routes->put('rooms/(:num)', 'Api\RoomController::update/$1');
    $routes->post('rooms/(:num)/archive', 'Api\RoomController::archive/$1');

    $routes->post('devices', 'Api\DeviceController::store');
    $routes->put('devices/(:num)', 'Api\DeviceController::update/$1');
    $routes->post('devices/(:num)/archive', 'Api\DeviceController::archive/$1');
    $routes->post('devices/(:num)/position', 'Api\DeviceController::position/$1');

    $routes->post('overrides', 'Api\OverrideController::store');
    $routes->post('overrides/(:num)/end', 'Api\OverrideController::end/$1');

    $routes->post('automation/rules', 'Api\AutomationController::store');
    $routes->put('automation/rules/(:num)', 'Api\AutomationController::update/$1');
    $routes->delete('automation/rules/(:num)', 'Api\AutomationController::destroy/$1');

    $routes->get('users', 'Api\UserController::index');
    $routes->post('users', 'Api\UserController::store');
    $routes->put('users/(:num)', 'Api\UserController::update/$1');

    $routes->get('settings', 'Api\SettingController::index');
    $routes->put('settings', 'Api\SettingController::update');
    $routes->post('settings', 'Api\SettingController::update');
});
