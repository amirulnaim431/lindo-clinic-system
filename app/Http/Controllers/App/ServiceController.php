<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Service::supportsCatalogFields(), 503, 'Run the latest migration before opening the service catalog.');

        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', ''));
        $status = trim((string) $request->input('status', 'active'));

        $services = Service::query()
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

        return view('app.services.index', [
            'services' => $services,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active',
            ],
            'categoryOptions' => Service::categoryOptions(),
        ]);
    }

    public function create(): View
    {
        abort_unless(Service::supportsCatalogFields(), 503, 'Run the latest migration before opening the service catalog.');

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
        abort_unless(Service::supportsCatalogFields(), 503, 'Run the latest migration before opening the service catalog.');

        $service = Service::query()->create($this->validatedData($request));

        return redirect()
            ->route('app.services.edit', $service)
            ->with('success', 'Service created.');
    }

    public function edit(Service $service): View
    {
        abort_unless(Service::supportsCatalogFields(), 503, 'Run the latest migration before opening the service catalog.');

        return $this->formView('edit', $service);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        abort_unless(Service::supportsCatalogFields(), 503, 'Run the latest migration before opening the service catalog.');

        $service->update($this->validatedData($request, $service));

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
            'description' => ['nullable', 'string', 'max:4000'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'price' => ['nullable', 'integer', 'min:0'],
            'promo_price' => ['nullable', 'integer', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable'],
            'is_promo' => ['nullable'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_promo'] = $request->boolean('is_promo');
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        if (! $data['is_promo']) {
            $data['promo_price'] = null;
        }

        return $data;
    }
}
