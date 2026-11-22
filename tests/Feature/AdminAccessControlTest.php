<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_only_access_assigned_admin_areas(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'permissions' => ['dashboard.view', 'products.manage'],
        ]);

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.products'))->assertOk();
        $this->actingAs($staff)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.staff'))->assertForbidden();
    }

    public function test_inactive_staff_cannot_log_in(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => false,
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => $staff->email,
            'password' => 'secret123',
        ])->assertInvalid('email');
    }

    public function test_active_staff_login_redirects_to_admin_and_is_audited(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'active-staff@example.com',
            'password' => 'secret123',
            'permissions' => ['dashboard.view'],
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $staff->email,
            'password' => 'secret123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('audit_logs', ['user_id' => $staff->id, 'action' => 'auth.login']);
    }

    public function test_admin_staff_changes_are_recorded_without_passwords(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Warehouse Staff',
            'email' => 'staff@example.com',
            'mobile' => '01700000000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'permissions' => ['inventory.manage'],
            'is_active' => 1,
        ])->assertRedirect(route('admin.staff'));

        $log = AuditLog::query()->where('action', 'admin.staff.store')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertArrayNotHasKey('password', $log->metadata ?? []);
        $this->assertArrayNotHasKey('password_confirmation', $log->metadata ?? []);
        $this->actingAs($admin)->get(route('admin.audit-logs'))->assertOk()->assertSee('admin.staff.store');
    }
}
