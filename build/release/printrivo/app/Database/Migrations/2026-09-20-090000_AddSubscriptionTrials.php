<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddSubscriptionTrials extends Migration
{
    public function up()
    {
        $this->forge->addColumn('subscriptions', [
            'trial_requested'=>['type'=>'BOOLEAN','default'=>false],
            'trial_ends_at'=>['type'=>'DATETIME','null'=>true],
            'billing_consent_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addField([
            'id'=>['type'=>'INTEGER','auto_increment'=>true],
            'environment'=>['type'=>'VARCHAR','constraint'=>10],
            'kind'=>['type'=>'VARCHAR','constraint'=>12],
            'signal_hash'=>['type'=>'VARCHAR','constraint'=>64],
            'subscription_id'=>['type'=>'INTEGER','unsigned'=>true],
            'ip_hash'=>['type'=>'VARCHAR','constraint'=>64,'null'=>true],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['environment','kind','signal_hash']);
        $this->forge->addKey(['environment','ip_hash','created_at']);
        // Keep pseudonymous claims after account deletion to prevent repeat trials.
        $this->forge->createTable('trial_claims');
    }
    public function down()
    {
        $this->forge->dropTable('trial_claims');
        $this->forge->dropColumn('subscriptions',['trial_requested','trial_ends_at','billing_consent_at']);
    }
}
