<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_provision_staff_login_from_staff_form(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@lindo.com',
        ]);

        $response = $this->actingAs($admin)->post(route('app.staff.store'), [
            'full_name' => 'Aisyah Azman',
            'employee_code' => 'LND-0901',
            'job_title' => 'Clinic Coordinator',
            'department' => 'Administration',
            'email' => 'aisyah@lindo.com',
            'operational_role' => 'admin',
            'is_active' => '1',
            'can_login' => '1',
            'access_permissions' => ['staff.view', 'appointments.manage'],
        ]);

        $response->assertRedirect(route('app.staff.index'));
        $response->assertSessionHas('staff_access_link');

        $staff = Staff::query()->where('email', 'aisyah@lindo.com')->firstOrFail();
        $this->assertNotNull($staff->user_id);
        $this->assertSame('LND-0901', $staff->employee_code);
        $this->assertTrue($staff->can_login);

        $user = User::query()->findOrFail($staff->user_id);
        $this->assertSame('staff', $user->role);
        $this->assertTrue($user->password_setup_required);
        $this->assertNotNull($user->last_password_reset_sent_at);
    }

    public function test_admin_can_provision_super_admin_access_for_executive_staff(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@lindo.com',
        ]);

        $response = $this->actingAs($admin)->post(route('app.staff.store'), [
            'full_name' => 'Board Director',
            'employee_code' => 'LND-0902',
            'job_title' => 'Board Member',
            'department' => 'Executive Office',
            'email' => 'board.member@lindo.com',
            'operational_role' => 'management',
            'is_active' => '1',
            'can_login' => '1',
            'access_level' => 'admin',
        ]);

        $response->assertRedirect(route('app.staff.index'));
        $response->assertSessionHas('staff_access_link');

        $staff = Staff::query()->where('email', 'board.member@lindo.com')->firstOrFail();
        $user = User::query()->findOrFail($staff->user_id);

        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->password_setup_required);
    }
}
