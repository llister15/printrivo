<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddPayPalBilling extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', ['alpha_redeemed_at' => ['type'=>'DATETIME','null'=>true]]);
        $this->forge->addColumn('subscriptions', [
            'provider_environment'=>['type'=>'VARCHAR','constraint'=>10,'null'=>true],
            'paypal_reference'=>['type'=>'VARCHAR','constraint'=>32,'null'=>true],
            'billing_amount'=>['type'=>'INTEGER','null'=>true],
            'billing_currency'=>['type'=>'VARCHAR','constraint'=>3,'null'=>true],
            'paypal_event_at'=>['type'=>'DATETIME','null'=>true],
            'paypal_hold_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addField([
            'id'=>['type'=>'INTEGER','auto_increment'=>true],
            'user_id'=>['type'=>'INTEGER','unsigned'=>true],
            'environment'=>['type'=>'VARCHAR','constraint'=>10],
            'subscription_id'=>['type'=>'INTEGER','unsigned'=>true],
            'request_id'=>['type'=>'VARCHAR','constraint'=>32],
            'approval_url'=>['type'=>'TEXT','null'=>true],
            'request_body'=>['type'=>'TEXT'],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['user_id','environment']);
        $this->forge->addForeignKey('user_id','users','id','','CASCADE');
        $this->forge->addForeignKey('subscription_id','subscriptions','id','','CASCADE');
        $this->forge->createTable('paypal_checkouts');
        $this->forge->addField([
            'id'=>['type'=>'INTEGER','auto_increment'=>true],
            'provider'=>['type'=>'VARCHAR','constraint'=>20],
            'environment'=>['type'=>'VARCHAR','constraint'=>10],
            'event_id'=>['type'=>'VARCHAR','constraint'=>128],
            'event_type'=>['type'=>'VARCHAR','constraint'=>80],
            'processed_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['provider','environment','event_id']);
        $this->forge->createTable('payment_events');
    }
    public function down()
    {
        $this->forge->dropTable('payment_events');
        $this->forge->dropTable('paypal_checkouts');
        $this->forge->dropColumn('subscriptions',['provider_environment','paypal_reference','billing_amount','billing_currency','paypal_event_at','paypal_hold_at']);
        $this->forge->dropColumn('users','alpha_redeemed_at');
    }
}
