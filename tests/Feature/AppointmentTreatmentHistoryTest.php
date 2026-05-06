<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\AppointmentItemOptionSelection;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceOptionGroup;
use App\Models\ServiceOptionValue;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
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

    public function test_calendar_empty_date_shows_simple_empty_state(): void
    {
        $admin = $this->createAdmin();
        $this->createStaff(['full_name' => 'Dr Amanda Binti Elli']);

        $response = $this->actingAs($admin)->get(route('app.calendar', [
            'date' => '2026-04-28',
        ]));

        $response->assertOk();
        $response->assertSee('No appointments yet');
        $response->assertDontSee('PIC: Dr Amanda');
    }

    public function test_embedded_calendar_reference_board_shows_empty_staff_boxes(): void
    {
        $admin = $this->createAdmin();
        $this->createStaff(['full_name' => 'Dr Amanda Binti Elli']);

        $response = $this->actingAs($admin)->get(route('app.calendar', [
            'date' => '2026-04-28',
            'embedded' => 1,
            'compact' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Dr Amanda');
        $response->assertSee('10:00 AM - 10:45 AM');
        $response->assertSee('Empty box');
        $response->assertSee('Available');
    }

    public function test_calendar_merges_services_for_same_customer_time_and_pic(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff([
            'full_name' => "Dr. Syarifah Munira 'Aaqilah Binti Al Sayed Mohamad",
        ]);
        $customer = Customer::query()->create([
            'full_name' => 'Lily Salina Binti Baharudin',
            'phone' => '0123454901',
            'current_package' => 'Bronze',
        ]);
        $start = Carbon::parse('2026-04-28 11:00:00');
        $group = AppointmentGroup::query()->create([
            'customer_id' => $customer->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(45),
            'status' => AppointmentStatus::Booked,
            'notes' => 'test',
        ]);
        $service = Service::query()->create([
            'service_code' => 'consult_tirze_calendar',
            'name' => 'Tirze',
            'category_key' => 'consultations',
            'consultation_category_key' => 'wellness',
            'default_staff_role' => 'doctor',
            'duration_minutes' => 60,
            'is_active' => true,
            'display_order' => 1,
        ]);
        $secondService = Service::query()->create([
            'service_code' => 'consult_tirze_10mg_calendar',
            'name' => 'Tirze 10MG',
            'category_key' => 'consultations',
            'consultation_category_key' => 'wellness',
            'default_staff_role' => 'doctor',
            'duration_minutes' => 60,
            'is_active' => true,
            'display_order' => 2,
        ]);

        $firstItem = AppointmentItem::query()->create([
            'appointment_group_id' => $group->id,
            'service_id' => $service->id,
            'service_name_snapshot' => 'Tirze',
            'service_category_key_snapshot' => 'consultations',
            'service_category_label_snapshot' => 'Consultation',
            'staff_id' => $staff->id,
            'staff_name_snapshot' => $staff->full_name,
            'staff_role_snapshot' => 'doctor',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(45),
        ]);
        $secondItem = AppointmentItem::query()->create([
            'appointment_group_id' => $group->id,
            'service_id' => $secondService->id,
            'service_name_snapshot' => 'Tirze 10MG',
            'service_category_key_snapshot' => 'consultations',
            'service_category_label_snapshot' => 'Consultation',
            'staff_id' => $staff->id,
            'staff_name_snapshot' => $staff->full_name,
            'staff_role_snapshot' => 'doctor',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(45),
        ]);
        AppointmentItemOptionSelection::query()->create([
            'appointment_item_id' => $secondItem->id,
            'option_group_name' => 'Session',
            'option_value_label' => 'Session 4',
            'display_order' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('app.calendar', [
            'date' => '2026-04-28',
        ]));

        $response->assertOk();
        $response->assertSee('PIC: Dr Aqilah');
        $response->assertSee('Consult Tirze | Consult Tirze 10MG | Session 4');
        $this->assertSame(1, substr_count($response->getContent(), 'Lily Salina Binti Baharudin'));
    }

    public function test_calendar_hides_cancelled_or_rescheduled_appointments(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff([
            'full_name' => "Dr. Syarifah Munira 'Aaqilah Binti Al Sayed Mohamad",
        ]);
        $customer = Customer::query()->create([
            'full_name' => 'Cancelled Calendar Customer',
            'phone' => '0123000000',
            'current_package' => 'Bronze',
        ]);
        $start = Carbon::parse('2026-04-28 11:00:00');
        $group = AppointmentGroup::query()->create([
            'customer_id' => $customer->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(45),
            'status' => AppointmentStatus::Cancelled,
            'notes' => 'requested another date',
        ]);
        $service = Service::query()->create([
            'service_code' => 'cancelled_calendar_service',
            'name' => 'Consult Tirze',
            'category_key' => 'consultations',
            'consultation_category_key' => 'wellness',
            'default_staff_role' => 'doctor',
            'duration_minutes' => 60,
            'is_active' => true,
            'display_order' => 1,
        ]);

        AppointmentItem::query()->create([
            'appointment_group_id' => $group->id,
            'service_id' => $service->id,
            'service_name_snapshot' => 'Consult Tirze',
            'service_category_key_snapshot' => 'consultations',
            'service_category_label_snapshot' => 'Consultation',
            'staff_id' => $staff->id,
            'staff_name_snapshot' => $staff->full_name,
            'staff_role_snapshot' => 'doctor',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(45),
        ]);

        $response = $this->actingAs($admin)->get(route('app.calendar', [
            'date' => '2026-04-28',
        ]));

        $response->assertOk();
        $response->assertDontSee('Cancelled Calendar Customer');
        $response->assertDontSee('requested another date');
        $response->assertSee('No appointments yet');
    }

    public function test_calendar_keeps_same_customer_separate_under_different_pics_and_pdf_order(): void
    {
        $admin = $this->createAdmin();
        $adila = $this->createStaff([
            'full_name' => 'Adila',
            'employee_code' => 'LND-3001',
        ]);
        $drAmanda = $this->createStaff([
            'full_name' => 'Dr. Amanda Binti Elli',
            'employee_code' => 'LND-3002',
        ]);
        $drAqilah = $this->createStaff([
            'full_name' => "Dr. Syarifah Munira 'Aaqilah Binti Al Sayed Mohamad",
            'employee_code' => 'LND-3003',
        ]);
        $emma = $this->createStaff([
            'full_name' => 'Emma',
            'employee_code' => 'LND-3004',
        ]);
        $farhana = $this->createStaff([
            'full_name' => 'Farhana',
            'employee_code' => 'LND-3005',
        ]);
        $customer = Customer::query()->create([
            'full_name' => 'Nur Test Customer',
            'phone' => '0120000000',
            'current_package' => 'Bronze',
        ]);
        $service = Service::query()->create([
            'service_code' => 'calendar_pdf_order_service',
            'name' => 'Facial Treatment',
            'category_key' => 'aesthetics',
            'default_staff_role' => 'doctor',
            'duration_minutes' => 60,
            'is_active' => true,
            'display_order' => 1,
        ]);
        $start = Carbon::parse('2026-04-28 10:00:00');

        foreach ([$emma, $drAmanda, $farhana, $drAqilah, $adila] as $staff) {
            $group = AppointmentGroup::query()->create([
                'customer_id' => $customer->id,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes(45),
                'status' => AppointmentStatus::Booked,
            ]);

            AppointmentItem::query()->create([
                'appointment_group_id' => $group->id,
                'service_id' => $service->id,
                'service_name_snapshot' => 'Facial Treatment',
                'service_category_key_snapshot' => 'aesthetics',
                'service_category_label_snapshot' => 'Aesthetic',
                'staff_id' => $staff->id,
                'staff_name_snapshot' => $staff->full_name,
                'staff_role_snapshot' => 'doctor',
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes(45),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('app.calendar', [
            'date' => '2026-04-28',
        ]));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertLessThan(
            strpos($content, 'schedule-section-head__pic">PIC: Dr Amanda'),
            strpos($content, 'schedule-section-head__pic">PIC: Dr Aqilah')
        );
        $this->assertLessThan(
            strpos($content, 'schedule-section-head__pic">PIC: Dr Amanda'),
            strpos($content, 'schedule-section-head__pic">PIC: Adila')
        );
        $this->assertLessThan(
            strpos($content, 'schedule-section-head__pic">PIC: Emma'),
            strpos($content, 'schedule-section-head__pic">PIC: Farhana')
        );
        $this->assertSame(5, substr_count($content, 'Nur Test Customer'));
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

    public function test_customer_search_returns_multiple_short_query_suggestions(): void
    {
        $admin = $this->createAdmin();

        foreach (['Abigail Tan', 'Adam Abu', 'Aina Bakar', 'Alyssa Binti Ahmad', 'Amirul Basri'] as $name) {
            Customer::query()->create([
                'full_name' => $name,
                'phone' => '01'.fake()->unique()->numerify('########'),
                'current_package' => 'Bronze',
            ]);
        }

        $response = $this->actingAs($admin)->getJson(route('app.appointments.customer-search', [
            'q' => 'ab',
        ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(5, count($response->json('customers')));
    }

    public function test_medex_machine_cannot_be_double_booked_in_same_time_slot(): void
    {
        $admin = $this->createAdmin();
        $firstStaff = $this->createStaff();
        $secondStaff = $this->createStaff([
            'full_name' => 'Emma',
            'employee_code' => 'LND-4002',
            'operational_role' => 'beautician',
            'role_key' => 'beautician',
            'role' => 'beautician',
        ]);
        $service = Service::query()->create([
            'service_code' => 'medex_full_face',
            'name' => 'Medex Full Face Lifting',
            'category_key' => 'beauty_spa',
            'default_staff_role' => 'beautician',
            'duration_minutes' => 60,
            'is_active' => true,
            'display_order' => 1,
        ]);
        $service->staff()->sync([$firstStaff->id, $secondStaff->id]);

        $makePayload = fn (string $instanceId, Staff $staff) => json_encode([
            'services' => [[
                'instance_id' => $instanceId,
                'service_id' => $service->id,
                'selected_options' => [],
            ]],
            'assignments' => [[
                'instance_id' => $instanceId,
                'staff_id' => $staff->id,
                'start_time' => '10:00',
                'slot_index' => 1,
            ]],
        ]);

        $this->actingAs($admin)->post(route('app.appointments.store'), [
            'date' => '2026-04-28',
            'customer_full_name' => 'Medex First',
            'customer_phone' => '0100000001',
            'booking_payload' => $makePayload('medex-a', $firstStaff),
        ])->assertRedirect();

        $response = $this->actingAs($admin)->from(route('app.appointments.index'))->post(route('app.appointments.store'), [
            'date' => '2026-04-28',
            'customer_full_name' => 'Medex Second',
            'customer_phone' => '0100000002',
            'booking_payload' => $makePayload('medex-b', $secondStaff),
        ]);

        $response->assertRedirect(route('app.appointments.index'));
        $response->assertSessionHasErrors('booking_payload');
        $this->assertDatabaseCount('appointment_groups', 1);
    }

    public function test_break_blocks_and_merged_services_protect_staff_boxes(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff();
        $service = Service::query()->create([
            'service_code' => 'consult_tirze',
            'name' => 'Consult Tirze',
            'category_key' => 'consultations',
            'consultation_category_key' => 'wellness',
            'default_staff_role' => 'doctor',
            'duration_minutes' => 60,
            'is_active' => true,
            'display_order' => 1,
        ]);
        $service->staff()->sync([$staff->id]);

        $this->actingAs($admin)->postJson(route('app.appointments.slot-blocks.store'), [
            'staff_id' => $staff->id,
            'date' => '2026-04-28',
            'start_time' => '10:00',
            'slot_index' => 1,
            'reason' => 'Lunch break',
        ])->assertOk();

        $blockedResponse = $this->actingAs($admin)->from(route('app.appointments.index'))->post(route('app.appointments.store'), [
            'date' => '2026-04-28',
            'customer_full_name' => 'Blocked Customer',
            'customer_phone' => '0100000003',
            'booking_payload' => json_encode([
                'services' => [[
                    'instance_id' => 'blocked-service',
                    'service_id' => $service->id,
                    'selected_options' => [],
                ]],
                'assignments' => [[
                    'instance_id' => 'blocked-service',
                    'staff_id' => $staff->id,
                    'start_time' => '10:00',
                    'slot_index' => 1,
                ]],
            ]),
        ]);

        $blockedResponse->assertRedirect(route('app.appointments.index'));
        $blockedResponse->assertSessionHasErrors('booking_payload');

        $mergedResponse = $this->actingAs($admin)->post(route('app.appointments.store'), [
            'date' => '2026-04-28',
            'customer_full_name' => 'Merged Customer',
            'customer_phone' => '0100000004',
            'booking_payload' => json_encode([
                'services' => [[
                    'instance_id' => 'merged-service',
                    'service_id' => $service->id,
                    'selected_options' => [],
                ]],
                'assignments' => [[
                    'instance_id' => 'merged-service',
                    'staff_id' => $staff->id,
                    'start_time' => '11:00',
                    'slot_index' => 1,
                    'span_slots' => true,
                ]],
            ]),
        ]);

        $mergedResponse->assertRedirect();
        $this->assertDatabaseHas('appointment_slot_blocks', [
            'staff_id' => $staff->id,
            'start_time' => '11:00:00',
            'slot_index' => 2,
        ]);
    }
}
