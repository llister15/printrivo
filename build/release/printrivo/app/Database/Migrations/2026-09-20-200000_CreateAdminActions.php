<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateAdminActions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'admin_id'=>['type'=>'INT','unsigned'=>true],
            'user_id'=>['type'=>'INT','unsigned'=>true],
            'subscription_id'=>['type'=>'INT','unsigned'=>true],
            'action'=>['type'=>'VARCHAR','constraint'=>40],
            'outcome'=>['type'=>'VARCHAR','constraint'=>40],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('admin_actions');
    }
    public function down() { $this->forge->dropTable('admin_actions'); }
}
