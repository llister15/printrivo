<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('/', 'Marketing::index');
$routes->get('index.php', 'Marketing::index');
$routes->get('pricing', 'Marketing::pricing');
$routes->get('terms', 'Marketing::terms');
$routes->get('privacy', 'Marketing::privacy');
$routes->get('login', 'Login::loginView', ['as' => 'login']);
$routes->post('login', 'Login::loginAction', ['filter' => 'auth-rates']);
$routes->get('register', 'Register::registerView', ['as' => 'register']);
$routes->post('register', 'Register::registerAction', ['filter' => 'auth-rates']);
$routes->post('logout', 'Login::logoutAction', ['as' => 'logout', 'filter' => 'member']);
$routes->get('account/subscription', 'Account::subscription', ['filter' => 'member']);
$routes->get('account/billing-history', 'Account::billingHistory', ['filter' => 'member']);
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'member']);
$routes->post('support/bug-report', 'BugReport::send', ['filter' => 'member']);
$routes->post('billing/paypal/checkout', 'PayPal::checkout', ['filter' => 'member']);
$routes->get('billing/paypal/return', 'PayPal::returned', ['filter' => 'member']);
$routes->post('billing/paypal/refresh', 'PayPal::refresh', ['filter' => 'member']);
$routes->post('billing/paypal/cancel', 'PayPal::cancel', ['filter' => 'member']);
$routes->post('webhooks/paypal', 'PayPal::webhook');


$routes->group('', ['filter' => 'member:subscription'], static function ($routes) {
$routes->match(['GET', 'POST'], 'tools/qr', 'QrCode::index');
$routes->get('tools/gang-sheet', 'GangSheet::index');
$routes->get('tools/image-studio', 'ImageStudio::index');
$routes->post('api/tools/gang-sheet/inspect', 'GangSheet::inspect');
$routes->get('files/qr-logo/(:segment)', 'QrCode::logo/$1');

// Legacy links share controllers and filters; there is no alternate tool runtime.
$routes->match(['GET', 'POST'], 'qrcode', 'QrCode::index');
$routes->match(['GET', 'POST'], 'qrcode/index.php', 'QrCode::index');
$routes->get('dtf', 'GangSheet::legacyIndex');
$routes->get('dtf/index.php', 'GangSheet::legacyIndex');
$routes->get('dtf/builder.php', 'GangSheet::index');
$routes->post('dtf/upload.php', 'GangSheet::inspect');
$routes->match(['GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], 'dtf/upload.php', 'GangSheet::uploadMethodNotAllowed');
$routes->get('cleanimg', 'ImageStudio::index');
$routes->get('cleanimg/index.php', 'ImageStudio::index');

});

$routes->get('admin', 'Admin::index', ['filter'=>'admin']);
$routes->get('admin/users/(:num)', 'Admin::user/$1', ['filter'=>'admin']);
$routes->post('admin/users/(:num)/cancel', 'Admin::cancel/$1', ['filter'=>'admin']);
