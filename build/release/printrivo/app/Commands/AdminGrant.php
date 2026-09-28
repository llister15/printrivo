<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class AdminGrant extends BaseCommand
{
    protected $group='Printrivo';
    protected $name='admin:grant';
    protected $description='Grant admin access to an existing account by email (server CLI only).';
    public function run(array $params)
    {
        $email=$params[0]??'';
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { CLI::error('Usage: php spark admin:grant owner@example.com'); return; }
        $user=model(\App\Models\UserModel::class)->findByCredentials(['email'=>$email]);
        if (!$user || !$user->active || $user->isBanned()) { CLI::error('An active, unbanned account is required.'); return; }
        $user->addGroup('admin');
        CLI::write('Admin access granted to account #'.$user->id.'.','green');
    }
}
