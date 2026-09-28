<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class TestSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(ENVIRONMENT, ['development', 'testing'], true)) {
            throw new RuntimeException('Test subscriptions are only available in development/testing.');
        }
        $user = model(UserModel::class)->findByCredentials(['email' => 'admin@printrivo.test']);
        if ($user === null || ! $user->inGroup('admin')) {
            throw new RuntimeException('Create the default test administrator first.');
        }

        $table = $this->db->table('subscriptions');
        $row = $table->where('user_id', $user->id)->where('provider', 'test')->get()->getRowArray();
        $now = gmdate('Y-m-d H:i:s');
        // Re-running renews only the synthetic entitlement; never changes PayPal records.
        $data = [
            'status' => 'active',
            'current_period_start' => $now,
            'current_period_end' => gmdate('Y-m-d H:i:s', time() + 30 * 86400),
            'cancel_at_period_end' => true,
            'updated_at' => $now,
        ];
        if ($row !== null) {
            $table->where('id', $row['id'])->update($data);
        } else {
            $table->insert($data + [
                'user_id' => $user->id,
                'provider' => 'test',
                'provider_environment' => 'test',
                'provider_subscription_id' => 'test-admin-' . $user->id,
                'created_at' => $now,
            ]);
        }
    }
}
