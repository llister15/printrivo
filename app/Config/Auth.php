<?php

namespace Config;

class Auth extends \CodeIgniter\Shield\Config\Auth
{
    public array $views = [
        'login' => 'auth/login',
        'register' => 'auth/register',
        'layout' => 'marketing/layout',
    ];
    public array $redirects = [
        'register' => 'account/subscription', 'login' => 'dashboard', 'logout' => '/',
        'force_reset' => 'account', 'permission_denied' => 'dashboard', 'group_denied' => 'dashboard',
    ];
    public string $userProvider = \App\Models\UserModel::class;
    public int $minimumPasswordLength = 12;
    public array $personalFields = ['first_name', 'last_name'];
    public bool $allowMagicLinkLogins = false; // Enable recovery only with configured mail delivery.
    public array $sessionConfig = [
        'field' => 'user', 'allowRemembering' => false,
        'rememberCookieName' => 'mber_remember', 'rememberLength' => 2592000,
    ];

    public function loginRedirect(): string
    {
        return site_url('dashboard');
    }
}
