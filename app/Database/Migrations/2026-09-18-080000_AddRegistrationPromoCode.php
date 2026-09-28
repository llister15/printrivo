<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddRegistrationPromoCode extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', ['promo_code' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true]]);
    }
    public function down()
    {
        $this->forge->dropColumn('users', 'promo_code');
    }
}
