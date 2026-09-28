<?php
use App\Services\PrintOrderStorage;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
final class PrintOrderStorageTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace=null;
    protected $refresh=true;
    private function removeStorage(bool $history): void
    {
        \Config\Database::forge($this->db)->dropTable('print_orders',true);
        if($history)$this->db->table(config('Migrations')->table)->where('class','App\\Database\\Migrations\\CreatePrintOrders')->delete();
        $this->db->resetDataCache();
    }
    public function testOnlyAdminCanInstallAndOnlyPrintMigrationIsApplied(): void
    {
        $this->removeStorage(true);
        $before=$this->db->table(config('Migrations')->table)->countAllResults();
        $storage=new PrintOrderStorage();
        $this->assertFalse($storage->ensureReady(false));$this->assertFalse($this->db->tableExists('print_orders'));
        $this->assertTrue($storage->ensureReady(true));
        $this->assertTrue($this->db->tableExists('print_orders'));
        $this->assertSame($before+1,$this->db->table(config('Migrations')->table)->countAllResults());
        $this->db->table('print_orders')->insert(['user_id'=>1,'request_id'=>'test','payload_hash'=>str_repeat('a',64),'created_at'=>gmdate('Y-m-d H:i:s')]);
        $this->assertTrue($storage->ensureReady(true));$this->assertTrue($storage->ensureReady(false));
        $this->assertSame(1,$this->db->table('print_orders')->countAllResults());
        $this->assertSame($before+1,$this->db->table(config('Migrations')->table)->countAllResults());
    }
    public function testRecordedMigrationIsRepairedUpwardWithoutDeletingHistory(): void
    {
        $before=$this->db->table(config('Migrations')->table)->countAllResults();
        $this->removeStorage(false);
        $this->assertTrue((new PrintOrderStorage())->ensureReady(true));
        $this->assertTrue($this->db->tableExists('print_orders'));
        $this->assertSame($before,$this->db->table(config('Migrations')->table)->countAllResults());
    }
}
