<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceOptionGroup;
use App\Models\Staff;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $catalogReady = Service::supportsCatalogFields();

        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', ''));
        $status = trim((string) $request->input('status', 'active'));

        if ($catalogReady) {
            $services = Service::query()
                ->with([
                    'optionGroups',
                    'staff' => function ($query) {
                        $query->where('is_active', true)->orderBy('full_name');
                    },
                ])
                ->when($search !== '', function ($query) use ($search) {
                    $like = '%'.$search.'%';
                    $query->where(function ($builder) use ($like) {
                        $builder
                            ->where('name', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    });
                })
                ->when($category !== '', fn ($query) => $query->where('category_key', $category))
                ->when($status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderByRaw('CASE WHEN is_active = 1 THEN 0 ELSE 1 END')
                ->orderBy('category_key')
                ->orderBy('display_order')
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString();
        } else {
            $services = new LengthAwarePaginator([], 0, 24, 1, [
                'path' => route('app.services.index'),
                'pageName' => 'page',
            ]);
        }

        return view('app.services.index', [
            'services' => $services,
            'catalogReady' => $catalogReady,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active',
            ],
            'categoryOptions' => Service::categoryOptions(),
            'roleOptions' => Staff::operationalRoleOptions(),
            'staffOptions' => Staff::query()
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'role_key']),
        ]);
    }

    public function create(): View
    {
        abort_if(! Service::supportsCatalogFields(), 404);

        return $this->formView('create', new Service([
            'is_active' => true,
            'is_promo' => false,
            'duration_minutes' => 60,
            'display_order' => 0,
            'category_key' => 'consultations',
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Service::supportsCatalogFields()) {
            return redirect()
                ->route('app.services.index')
                ->with('error', 'Run the latest migration before adding services.');
        }

        $data = $this->validatedData($request);
        $service = Service::query()->create(collect($data)->except(['option_group_ids', 'staff_ids'])->all());
        $service->optionGroups()->sync($this->optionGroupSyncPayload($data['option_group_ids'] ?? []));
        $service->staff()->sync($data['staff_ids'] ?? []);

        return redirect()
            ->route('app.services.edit', $service)
            ->with('success', 'Service created.');
    }

    public function edit(Service $service): View
    {
        abort_if(! Service::supportsCatalogFields(), 404);

        return $this->formView('edit', $service);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        if (! Service::supportsCatalogFields()) {
            return redirect()
                ->route('app.services.index')
                ->with('error', 'Run the latest migration before updating services.');
        }

        $data = $this->validatedData($request, $service);
        $service->update(collect($data)->except(['option_group_ids', 'staff_ids'])->all());
        $service->optionGroups()->sync($this->optionGroupSyncPayload($data['option_group_ids'] ?? []));
        $service->staff()->sync($data['staff_ids'] ?? []);

        return redirect()
            ->route('app.services.edit', $service)
            ->with('success', 'Service updated.');
    }

    private function formView(string $mode, Service $service): View
    {
        return view('app.services.form', [
            'mode' => $mode,
            'service' => $service,
            'categoryOptions' => Service::categoryOptions(),
            'roleOptions' => Staff::operationalRoleOptions(),
            'staffOptions' => Staff::query()
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'role_key']),
            'optionGroups' => ServiceOptionGroup::query()
                ->with('values')
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(),
            'selectedOptionGroupIds' => $service->exists
                ? $service->optionGroups()->pluck('service_option_groups.id')->map(fn ($id) => (string) $id)->all()
                : [],
            'selectedStaffIds' => $service->exists
                ? $service->staff()->pluck('staff.id')->map(fn ($id) => (string) $id)->all()
                : [],
        ]);
    }

    private function validatedData(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('services', 'name')->ignore($service?->id),
            ],
            'category_key' => ['required', 'string', Rule::in(array_keys(Service::categoryOptions()))],
            'default_staff_role' => ['nullable', 'string', Rule::in(array_keys(Staff::operationalRoleOptions()))],
            'description' => ['nullable', 'string', 'max:4000'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'price' => ['nullable', 'integer', 'min:0'],
            'promo_price' => ['nullable', 'integer', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable'],
            'is_promo' => ['nullable'],
            'option_group_ids' => ['nullable', 'array'],
            'option_group_ids.*' => ['string', Rule::exists('service_option_groups', 'id')],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['string', Rule::exists('staff', 'id')],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_promo'] = $request->boolean('is_promo');
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        if (! $data['is_promo']) {
            $data['promo_price'] = null;
        }

        return $data;
    }

    private function optionGroupSyncPayload(array $optionGroupIds): array
    {
        return collect($optionGroupIds)
            ->values()
            ->mapWithKeys(fn ($id, $index) => [(string) $id => [
                'id' => (string) Str::ulid(),
                'is_required' => true,
                'display_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]])
            ->all();
    }
}
