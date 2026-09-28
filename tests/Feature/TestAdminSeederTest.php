<?php

use App\Database\Seeds\TestAdminSeeder;
use App\Database\Seeds\TestSubscriptionSeeder;
use App\Database\Migrations\CreateDefaultTestAdmin;
use App\Models\UserModel;
use App\Services\SubscriptionAccess;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class TestAdminSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $refresh = true;

    public function testCreatesAdminWithWorkingPasswordWithoutGrantingSubscription(): void
    {
        $original = getenv('PRINTRIVO_TEST_ADMIN_PASSWORD');
        putenv('PRINTRIVO_TEST_ADMIN_PASSWORD=TestOnlySecret!48279');
        try {
            $migration = new CreateDefaultTestAdmin();
            $migration->up();
            $users = model(UserModel::class);
            $user = $users->findByCredentials(['email' => 'admin@printrivo.test']);
            $this->assertTrue($user->inGroup('admin'));
            $this->assertTrue($user->isActivated());
            $this->assertTrue(password_verify('TestOnlySecret!48279', $user->password_hash));
            $this->assertNull((new SubscriptionAccess())->activeFor((int) $user->id));

            $this->seed(TestSubscriptionSeeder::class);
            $this->seed(TestSubscriptionSeeder::class);
            $access = (new SubscriptionAccess())->activeFor((int) $user->id);
            $this->assertSame('test', $access['provider']);
            $this->assertSame(1, $this->db->table('subscriptions')->where('user_id', $user->id)->countAllResults());
            $this->db->table('subscriptions')->where('id', $access['id'])->update(['current_period_end' => '2000-01-01 00:00:00']);
            $this->assertNull((new SubscriptionAccess())->activeFor((int) $user->id));

            putenv('PRINTRIVO_TEST_ADMIN_PASSWORD=ChangedSecret!48279');
            $this->seed(TestAdminSeeder::class);
            $same = $users->findByCredentials(['email' => 'admin@printrivo.test']);
            $this->assertSame($user->id, $same->id);
            $this->assertTrue(password_verify('TestOnlySecret!48279', $same->password_hash));
            $migration->down();
            $this->assertNotNull($users->findById($user->id));
        } finally {
            putenv($original === false ? 'PRINTRIVO_TEST_ADMIN_PASSWORD' : 'PRINTRIVO_TEST_ADMIN_PASSWORD=' . $original);
        }
    }

    public function testMigrationSkipsCreationWithoutPassword(): void
    {
        $original = getenv('PRINTRIVO_TEST_ADMIN_PASSWORD');
        putenv('PRINTRIVO_TEST_ADMIN_PASSWORD=');
        try {
            (new CreateDefaultTestAdmin())->up();
            $this->assertNull(model(UserModel::class)->findByCredentials(['email' => 'admin@printrivo.test']));
        } finally {
            putenv($original === false ? 'PRINTRIVO_TEST_ADMIN_PASSWORD' : 'PRINTRIVO_TEST_ADMIN_PASSWORD=' . $original);
        }
    }
}
