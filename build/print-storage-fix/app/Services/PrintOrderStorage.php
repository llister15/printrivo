<?php
namespace App\Services;
use CodeIgniter\Database\MigrationRunner;
/** Install only print-order storage on SFTP-only hosts, during an admin request. */
class PrintOrderStorage extends MigrationRunner
{
    public function __construct()
    {
        parent::__construct(config('Migrations'));
    }
    public function ensureReady(bool $canInstall): bool
    {
        if($this->db->tableExists('print_orders'))return true;
        if(!$canInstall)return false;
        $lock=@fopen(WRITEPATH.'cache/print-order-setup.lock','c');
        if(!$lock)return false;
        try {
            if(!flock($lock,LOCK_EX|LOCK_NB))return false;
            $this->db->resetDataCache();
            if($this->db->tableExists('print_orders'))return true;
            if(!$this->enabled)return false;
            $migration=$this->migrationFromFile(APPPATH.'Database/Migrations/2026-09-24-090000_CreatePrintOrders.php','App');
            if(!$migration)return false;
            $this->ensureTable();
            $recorded=false;
            foreach($this->getHistory($this->group) as $history){
                if($this->getObjectUid($history)===$migration->uid){$recorded=true;break;}
            }
            // Never call force/latest: only this migration may run, and only upward.
            if(!$this->migrate('up',$migration))return false;
            if(!$recorded)$this->addHistory($migration,$this->getLastBatch()+1);
            $this->db->resetDataCache();
            return $this->db->tableExists('print_orders');
        } catch(\Throwable $error) {
            log_message('error','Print-order storage setup failed ({type}). Check database CREATE permissions and writable/cache.',['type'=>get_class($error)]);
            return false;
        } finally {
            flock($lock,LOCK_UN);fclose($lock);
        }
    }
}
