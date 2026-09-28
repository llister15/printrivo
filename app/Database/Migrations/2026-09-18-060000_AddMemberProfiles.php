<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddMemberProfiles extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => ''],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => ''],
        ]);
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'stripe'],
            'provider_customer_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'provider_subscription_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'provider_price_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'incomplete'],
            'current_period_start' => ['type' => 'DATETIME', 'null' => true],
            'current_period_end' => ['type' => 'DATETIME', 'null' => true],
            'cancel_at_period_end' => ['type' => 'BOOLEAN', 'default' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'status']);
        $this->forge->addUniqueKey('provider_subscription_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('subscriptions');
    }
    public function down()
    {
        $this->forge->dropTable('subscriptions');
        $this->forge->dropColumn('users', ['first_name', 'last_name']);
    }
}
