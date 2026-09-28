<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

class TestAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(ENVIRONMENT, ['development', 'testing'], true)) {
            throw new RuntimeException('Test admin creation is only available in development or testing.');
        }

        $email = 'admin@printrivo.test';
        $users = model(UserModel::class);
        $existing = $users->findByCredentials(['email' => $email]);
        if ($existing !== null) {
            if (! $existing->inGroup('admin')) {
                throw new RuntimeException('The test email already belongs to a non-admin account. No changes made.');
            }
            return; // Never reset credentials or billing when re-running the seeder.
        }

        $password = (string) env('PRINTRIVO_TEST_ADMIN_PASSWORD', '');
        if (strlen($password) < 12) {
            throw new RuntimeException('Set PRINTRIVO_TEST_ADMIN_PASSWORD to at least 12 characters.');
        }

        $this->db->transException(true)->transStart();
        $user = new User([
            'email' => $email,
            'password' => $password,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'active' => 1,
        ]);
        if (! $users->save($user)) {
            throw new RuntimeException('Unable to create the test administrator.');
        }
        $user = $users->findById($users->getInsertID());
        $user->addGroup('user', 'admin');
        $this->db->transComplete();
    }
}
