<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    protected function roleOptions(): array
    {
        return [
            'doctor' => 'Doctor',
            'nurse' => 'Nurse',
            'beautician' => 'Beautician',
            'therapist' => 'Therapist',
            'admin' => 'Admin',
        ];
    }

    public function index()
    {
        $roles = $this->roleOptions();

        $staff = Staff::query()
            ->with(['services' => function ($query) {
                $query->orderBy('name');
            }])
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('full_name')
            ->paginate(20);

        return view('app.staff.index', compact('staff', 'roles'));
    }

    public function create()
    {
        $roles = $this->roleOptions();

        $staff = new Staff([
            'is_active' => true,
        ]);

        $services = Service::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->get();

        return view('app.staff.form', [
            'mode' => 'create',
            'staff' => $staff,
            'roles' => $roles,
            'services' => $services,
            'selectedServiceIds' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['string', 'exists:services,id'],
        ]);

        $staff = Staff::create([
            'full_name' => $data['full_name'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $staff->services()->sync($data['service_ids'] ?? []);

        return redirect()
            ->route('app.staff.index')
            ->with('success', 'Staff created.');
    }

    public function edit(Staff $staff)
    {
        $roles = $this->roleOptions();

        $staff->load('services');

        $services = Service::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->get();

        return view('app.staff.form', [
            'mode' => 'edit',
            'staff' => $staff,
            'roles' => $roles,
            'services' => $services,
            'selectedServiceIds' => $staff->services->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ]);
    }

    public function update(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['string', 'exists:services,id'],
        ]);

        $staff->update([
            'full_name' => $data['full_name'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $staff->services()->sync($data['service_ids'] ?? []);

        return redirect()
            ->route('app.staff.index')
            ->with('success', 'Staff updated.');
    }
}
