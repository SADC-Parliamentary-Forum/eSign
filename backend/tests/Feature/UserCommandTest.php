<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $adminRole;
    protected $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->userRole = Role::create(['name' => 'user']);
    }

    public function test_delete_user_soft_delete()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'role_id' => $this->userRole->id,
        ]);

        $this->artisan('user:delete', ['email' => 'test@example.com'])
            ->expectsQuestion('Are you sure you want to delete test@example.com?', 'yes')
            ->expectsOutput('User soft deleted.')
            ->assertExitCode(0);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_delete_user_force_delete()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'role_id' => $this->userRole->id,
        ]);

        $this->artisan('user:delete', ['email' => 'test@example.com', '--force' => true])
            ->expectsQuestion('Are you sure you want to delete test@example.com?', 'yes')
            ->expectsOutput('User permanently deleted.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_user_cancellation()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'role_id' => $this->userRole->id,
        ]);

        $this->artisan('user:delete', ['email' => 'test@example.com'])
            ->expectsQuestion('Are you sure you want to delete test@example.com?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_promote_user_to_admin()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'role_id' => $this->userRole->id,
            'mfa_enabled' => false,
        ]);

        $this->artisan('user:promote-admin', ['email' => 'user@example.com'])
            ->expectsQuestion('Promote user@example.com to ADMINISTRATOR?', 'yes')
            ->expectsOutput('User promoted to Administrator.')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals($this->adminRole->id, $user->role_id);
        $this->assertTrue((bool) $user->mfa_enabled);
    }

    public function test_promote_user_cancellation()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'role_id' => $this->userRole->id,
        ]);

        $this->artisan('user:promote-admin', ['email' => 'user@example.com'])
            ->expectsQuestion('Promote user@example.com to ADMINISTRATOR?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals($this->userRole->id, $user->role_id);
    }

    public function test_demote_admin_to_user()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role_id' => $this->adminRole->id,
            'mfa_enabled' => true,
        ]);

        $this->artisan('user:demote-admin', ['email' => 'admin@example.com'])
            ->expectsQuestion('Demote admin@example.com to NORMAL USER?', 'yes')
            ->expectsOutput('User downgraded to normal user.')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertEquals($this->userRole->id, $admin->role_id);
        $this->assertFalse((bool) $admin->mfa_enabled);
    }

    public function test_demote_admin_cancellation()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role_id' => $this->adminRole->id,
        ]);

        $this->artisan('user:demote-admin', ['email' => 'admin@example.com'])
            ->expectsQuestion('Demote admin@example.com to NORMAL USER?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertEquals($this->adminRole->id, $admin->role_id);
    }
}
