<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreatePrintOrders extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'user_id'=>['type'=>'INT','unsigned'=>true],
            'request_id'=>['type'=>'VARCHAR','constraint'=>36],
            'payload_hash'=>['type'=>'VARCHAR','constraint'=>64],
            'receipt'=>['type'=>'TEXT','null'=>true],
            'retry_after'=>['type'=>'DATETIME','null'=>true],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['user_id','request_id']);
        $this->forge->createTable('print_orders',true);
    }
    public function down() { $this->forge->dropTable('print_orders'); }
}
