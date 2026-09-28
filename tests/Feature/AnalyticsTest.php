<?php
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
final class AnalyticsTest extends CIUnitTestCase
{
    use FeatureTestTrait, DatabaseTestTrait;
    protected $namespace=null;
    protected $refresh=true;
    public function testAnalyticsOnlyAppearsOnAllowlistedPagesWithValidConfiguration(): void
    {
        $config=config('Analytics');$enabled=$config->enabled;$id=$config->measurementId;
        try {
            $config->enabled=false;$config->measurementId='G-TEST12345';
            $this->get('/')->assertDontSee('data-measurement-id');
            $config->enabled=true;
            foreach (['/','/pricing','/terms','/privacy'] as $path) { $this->get($path)->assertSee('data-measurement-id'); }
            foreach (['/login','/register'] as $path) { $this->get($path)->assertDontSee('data-measurement-id'); }
            $config->measurementId='invalid';$this->get('/')->assertDontSee('data-measurement-id');
        } finally { $config->enabled=$enabled;$config->measurementId=$id; }
    }
}
