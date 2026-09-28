<?php

namespace App\Database\Migrations;

use App\Database\Seeds\TestAdminSeeder;
use CodeIgniter\Database\Migration;

class CreateDefaultTestAdmin extends Migration
{
    public function up()
    {
        // Explicit local opt-in: never ship a known password or provision a test
        // administrator in production. Shield's earlier migration creates users.
        if (! in_array(ENVIRONMENT, ['development', 'testing'], true)
            || (string) env('PRINTRIVO_TEST_ADMIN_PASSWORD', '') === '') {
            return;
        }

        (new TestAdminSeeder(config('Database'), $this->db))->run();
    }

    public function down()
    {
        // Preserve the account and any subsequent billing/history on rollback.
        // It may have existed before this migration ran.
    }
}
