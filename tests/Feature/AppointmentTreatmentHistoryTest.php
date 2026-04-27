<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceOptionGroup;
use App\Models\ServiceOptionValue;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentTreatmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createStaff(array $overrides = []): Staff
    {
        return Staff::query()->create(array_merge([
            'full_name' => 'Dr Aqilah',
            'employee_code' => 'LND-3001',
            'job_title' => 'Medical Officer',
            'department' => 'Medical',
            'operational_role' => 'doctor',
            'role_key' => 'doctor',
            'role' => 'doctor',
            'is_active' => true,
            'can_login' => false,
            'access_permissions' => [],
        ], $overrides));
    }

    public function test_booking_persists_structured_option_selection_into_customer_history(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff();

        $service = Service::query()->create([
            'service_code' => 'consult_tirze_5mg',
            'name' => 'Consult Tirze 5MG',
            'category_key' => 'wellness',
            'default_staff_role' => 'doctor',
            'description' => null,
            'duration_minutes' => 60,
            'price' => null,
            'promo_price' => null,
            'is_promo' => false,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $service->staff()->sync([$staff->id]);

        $group = ServiceOptionGroup::query()->create([
            'code' => 'tirze_cycle',
            'name' => 'Cycle',
            'selection_mode' => 'single',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $value = ServiceOptionValue::query()->create([
            'service_option_group_id' => $group->id,
            'value_code' => 'mt2',
            'label' => 'MT2',
            'display_order' => 1,
        ]);

        DB::table('service_option_group_service')->insert([
            'id' => (string) Str::ulid(),
            'service_id' => $service->id,
            'service_option_group_id' => $group->id,
            'is_required' => true,
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $instanceId = 'tirze-test-instance';
        $bookingPayload = json_encode([
            'services' => [
                [
                    'instance_id' => $instanceId,
                    'service_id' => $service->id,
                    'selected_options' => [
                        $group->id => $value->id,
                    ],
                ],
            ],
            'assignments' => [
                [
                    'instance_id' => $instanceId,
                    'staff_id' => $staff->id,
                    'start_time' => '10:00',
                    'slot_index' => 1,
                ],
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('app.appointments.store'), [
            'date' => now()->toDateString(),
            'customer_full_name' => 'Test Customer',
            'customer_phone' => '0123456789',
            'notes' => 'Follow-up booking',
            'booking_payload' => $bookingPayload,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointment_items', [
            'service_name_snapshot' => 'Consult Tirze 5MG',
            'service_category_label_snapshot' => 'Wellness',
            'staff_name_snapshot' => 'Dr Aqilah',
            'staff_role_snapshot' => 'doctor',
        ]);

        $this->assertDatabaseHas('appointment_item_option_selections', [
            'option_group_name' => 'Cycle',
            'option_value_label' => 'MT2',
        ]);

        $this->assertDatabaseHas('appointment_slot_reservations', [
            'staff_id' => $staff->id,
            'start_time' => '10:00:00',
            'slot_index' => 1,
        ]);

        $duplicateResponse = $this->actingAs($admin)->from(route('app.appointments.index'))->post(route('app.appointments.store'), [
            'date' => now()->toDateString(),
            'customer_full_name' => 'Second Customer',
            'customer_phone' => '0199999999',
            'notes' => 'Duplicate box test',
            'booking_payload' => $bookingPayload,
        ]);

        $duplicateResponse->assertRedirect(route('app.appointments.index'));
        $duplicateResponse->assertSessionHasErrors('booking_payload');
        $this->assertDatabaseCount('appointment_groups', 1);

        $customerId = DB::table('customers')->where('phone', '0123456789')->value('id');
        $historyResponse = $this->actingAs($admin)->get(route('app.customers.show', $customerId));

        $historyResponse->assertOk();
        $historyResponse->assertSee('Consult Tirze 5MG');
        $historyResponse->assertSee('Cycle: MT2');
        $historyResponse->assertSee('Staff: Dr Aqilah');
    }

    public function test_optional_service_options_can_be_skipped_when_booking(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff();

        $service = Service::query()->create([
            'service_code' => 'consult_tirze',
            'name' => 'Tirze',
            'category_key' => 'consultations',
            'consultation_category_key' => 'wellness',
            'default_staff_role' => 'doctor',
            'description' => null,
            'duration_minutes' => 60,
            'price' => null,
            'promo_price' => null,
            'is_promo' => false,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $service->staff()->sync([$staff->id]);

        $group = ServiceOptionGroup::query()->create([
            'code' => 'tirze_dosage',
            'name' => 'Dosage',
            'selection_mode' => 'single',
            'is_active' => true,
            'display_order' => 1,
        ]);

        ServiceOptionValue::query()->create([
            'service_option_group_id' => $group->id,
            'value_code' => '5mg',
            'label' => '5MG',
            'display_order' => 1,
        ]);

        DB::table('service_option_group_service')->insert([
            'id' => (string) Str::ulid(),
            'service_id' => $service->id,
            'service_option_group_id' => $group->id,
            'is_required' => false,
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $instanceId = 'tirze-optional-instance';
        $response = $this->actingAs($admin)->post(route('app.appointments.store'), [
            'date' => now()->toDateString(),
            'customer_full_name' => 'Optional Customer',
            'customer_phone' => '0111111111',
            'booking_payload' => json_encode([
                'services' => [
                    [
                        'instance_id' => $instanceId,
                        'service_id' => $service->id,
                        'selected_options' => [],
                    ],
                ],
                'assignments' => [
                    [
                        'instance_id' => $instanceId,
                        'staff_id' => $staff->id,
                        'start_time' => '10:00',
                        'slot_index' => 1,
                    ],
                ],
            ]),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointment_items', [
            'service_name_snapshot' => 'Tirze',
            'staff_name_snapshot' => 'Dr Aqilah',
        ]);
        $this->assertDatabaseCount('appointment_item_option_selections', 0);
    }

    public function test_calendar_shows_empty_staff_availability_rows(): void
    {
        $admin = $this->createAdmin();
        $this->createStaff(['full_name' => 'Dr Amanda Binti Elli']);

        $response = $this->actingAs($admin)->get(route('app.calendar', [
            'date' => '2026-04-28',
        ]));

        $response->assertOk();
        $response->assertSee('PIC: Dr Amanda');
        $response->assertSee('10:00 AM - 10:45 AM');
        $response->assertSee('No appointment yet');
    }

    public function test_appointment_builder_does_not_offer_9am_slots(): void
    {
        $admin = $this->createAdmin();
        $this->createStaff();

        $response = $this->actingAs($admin)->get(route('app.appointments.index', [
            'date' => '2026-04-28',
        ]));

        $response->assertOk();
        $response->assertDontSee('9:00 AM - 9:45 AM');
        $response->assertSee('10:00 AM - 10:45 AM');
    }
}
