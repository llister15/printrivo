<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DatabaseTarget extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'db:target';
    protected $description = 'Show the connected database and test admin status without secrets.';

    public function run(array $params)
    {
        $db = db_connect();
        $db->initialize();
        CLI::write('Driver: ' . $db->DBDriver);
        CLI::write('Host: ' . $db->hostname . ':' . $db->port);
        CLI::write('Database: ' . $db->database);
        CLI::write('Users table: ' . $db->prefixTable('users'));
        $user = model(\App\Models\UserModel::class)->findByCredentials(['email' => 'admin@printrivo.test']);
        CLI::write('Test admin: ' . ($user === null ? 'missing' : 'present (ID ' . $user->id . ')'));
        if ($user !== null) {
            CLI::write('Active entitlement: ' . ((new \App\Services\SubscriptionAccess())->activeFor((int) $user->id) ? 'yes' : 'no'));
        }
    }
}
