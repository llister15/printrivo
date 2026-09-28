<?php
namespace Config;
class AuthGroups extends \CodeIgniter\Shield\Config\AuthGroups
{
    public string $defaultGroup = 'user';
    public array $groups = [
        'user' => ['title' => 'Member', 'description' => 'Printrivo account.'],
        'admin' => ['title' => 'Administrator', 'description' => 'Printrivo administrator.'],
    ];
    public array $permissions = [];
    public array $matrix = ['user' => [], 'admin' => []];
}
