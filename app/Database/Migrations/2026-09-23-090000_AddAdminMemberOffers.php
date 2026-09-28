<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddAdminMemberOffers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'user_id'=>['type'=>'INTEGER','unsigned'=>true],
            'token_hash'=>['type'=>'VARCHAR','constraint'=>64],
            'expires_at'=>['type'=>'DATETIME'],
            'accepted_at'=>['type'=>'DATETIME','null'=>true],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('user_id',true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addForeignKey('user_id','users','id','','CASCADE');
        $this->forge->createTable('member_invitations');
        $this->forge->addField([
            'id'=>['type'=>'INTEGER','unsigned'=>true,'auto_increment'=>true],
            'user_id'=>['type'=>'INTEGER','unsigned'=>true],
            'environment'=>['type'=>'VARCHAR','constraint'=>10],
            'amount'=>['type'=>'INTEGER'],
            'admin_id'=>['type'=>'INTEGER','unsigned'=>true],
            'subscription_id'=>['type'=>'INTEGER','unsigned'=>true,'null'=>true],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['user_id','environment']);
        $this->forge->addForeignKey('user_id','users','id','','CASCADE');
        $this->forge->createTable('member_prices');
        $this->forge->addColumn('subscriptions',[
            'discount_amount'=>['type'=>'INTEGER','null'=>true],
            'previous_billing_amount'=>['type'=>'INTEGER','null'=>true],
            'discount_requested_at'=>['type'=>'DATETIME','null'=>true],
            'discount_confirmed_at'=>['type'=>'DATETIME','null'=>true],
            'discount_old_price_until'=>['type'=>'DATETIME','null'=>true],
            'discount_admin_id'=>['type'=>'INTEGER','unsigned'=>true,'null'=>true],
        ]);
        $this->forge->modifyColumn('admin_actions',['subscription_id'=>['type'=>'INT','unsigned'=>true,'null'=>true]]);
    }
    public function down()
    {
        $this->forge->dropTable('member_prices');
        $this->forge->dropTable('member_invitations');
        $this->forge->dropColumn('subscriptions',['discount_amount','previous_billing_amount','discount_requested_at',
            'discount_confirmed_at','discount_old_price_until','discount_admin_id']);
        // Keep audit rows for account actions, which have no subscription ID.
    }
}
