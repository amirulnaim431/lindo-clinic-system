<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceOptionGroup;
use App\Models\ServiceOptionValue;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LindoClinicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = config('lindo_service_catalog');

        if (! is_array($catalog)) {
            return;
        }

        DB::transaction(function () use ($catalog) {
            $this->syncStaff($catalog['staff'] ?? []);
            $optionGroups = $this->syncOptionGroups($catalog['option_groups'] ?? []);
            $services = $this->syncServices($catalog['services'] ?? []);
            $this->syncServiceOptionsAndAssignments($catalog['services'] ?? [], $services, $optionGroups);
            $this->retireLegacyServices($catalog['retired_service_codes'] ?? []);
        });
    }

    private function syncStaff(array $rows): void
    {
        foreach ($rows as $row) {
            $staff = Staff::query()->firstOrNew(['full_name' => $row['full_name']]);

            $staff->fill([
                'job_title' => $row['job_title'] ?? null,
                'department' => $row['department'] ?? null,
                'operational_role' => $row['operational_role'] ?? 'support',
                'role_key' => $row['operational_role'] ?? 'support',
                'role' => $row['operational_role'] ?? 'support',
                'is_active' => true,
            ]);

            if (! $staff->exists) {
                $staff->can_login = false;
                $staff->access_permissions = [];
            }

            $staff->save();
        }
    }

    private function syncOptionGroups(array $groups): array
    {
        $result = [];

        foreach ($groups as $groupIndex => $group) {
            $optionGroup = ServiceOptionGroup::query()->updateOrCreate(
                ['code' => $group['code']],
                [
                    'name' => $group['name'],
                    'selection_mode' => $group['selection_mode'] ?? 'single',
                    'is_active' => true,
                    'display_order' => $groupIndex + 1,
                ]
            );

            foreach (($group['values'] ?? []) as $valueIndex => $label) {
                ServiceOptionValue::query()->updateOrCreate(
                    [
                        'service_option_group_id' => $optionGroup->id,
                        'value_code' => Str::slug((string) $label, '_'),
                    ],
                    [
                        'label' => $label,
                        'display_order' => $valueIndex + 1,
                    ]
                );
            }

            $result[$group['code']] = $optionGroup->fresh('values');
        }

        return $result;
    }

    private function syncServices(array $services): array
    {
        $result = [];

        foreach ($services as $index => $service) {
            $record = Service::query()->updateOrCreate(
                ['service_code' => $service['code']],
                [
                    'name' => $service['name'],
                    'category_key' => $service['category_key'],
                    'consultation_category_key' => $service['consultation_category_key'] ?? null,
                    'default_staff_role' => $service['default_staff_role'] ?? null,
                    'description' => $service['description'] ?? null,
                    'duration_minutes' => $service['duration_minutes'] ?? 60,
                    'price' => $service['price'] ?? null,
                    'promo_price' => $service['promo_price'] ?? null,
                    'is_promo' => (bool) ($service['is_promo'] ?? false),
                    'is_active' => true,
                    'display_order' => $service['display_order'] ?? ($index + 1),
                ]
            );

            $result[$service['code']] = $record;
        }

        return $result;
    }

    private function syncServiceOptionsAndAssignments(array $services, array $serviceRecords, array $optionGroups): void
    {
        $staffByName = Staff::query()->get()->keyBy('full_name');

        foreach ($services as $service) {
            $serviceRecord = $serviceRecords[$service['code']] ?? null;

            if (! $serviceRecord) {
                continue;
            }

            $pivotPayload = [];

            foreach (($service['option_groups'] ?? []) as $index => $groupCode) {
                $group = $optionGroups[$groupCode] ?? null;

                if (! $group) {
                    continue;
                }

                $pivotPayload[$group->id] = [
                    'id' => (string) Str::ulid(),
                    'is_required' => true,
                    'display_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $serviceRecord->optionGroups()->sync($pivotPayload);

            $staffIds = collect($service['staff'] ?? [])
                ->map(fn (string $name) => $staffByName->get($name)?->id)
                ->filter()
                ->values()
                ->all();

            $serviceRecord->staff()->sync($staffIds);
        }
    }

    private function retireLegacyServices(array $serviceCodes): void
    {
        $codes = collect($serviceCodes)
            ->filter(fn ($code) => is_string($code) && trim($code) !== '')
            ->values()
            ->all();

        if ($codes === []) {
            return;
        }

        Service::query()
            ->whereIn('service_code', $codes)
            ->update(['is_active' => false]);
    }
}
