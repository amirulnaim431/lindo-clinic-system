<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    private function roleOptions(): array
    {
        return [
            'doctor'     => 'Doctor',
            'nurse'      => 'Nurse',
            'beautician' => 'Beautician',
            'therapist'  => 'Therapist',
            'admin'      => 'Admin',
        ];
    }

    public function index()
    {
        $roles = $this->roleOptions();

        $staff = Staff::query()
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('full_name')
            ->paginate(20);

        return view('app.staff.index', compact('staff', 'roles'));
    }

    public function create()
    {
        $roles = $this->roleOptions();
        $staff = new Staff(['is_active' => true]);

        return view('app.staff.form', [
            'mode'  => 'create',
            'staff' => $staff,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'role'      => ['required', 'string', 'max:50'],
            'is_active' => ['nullable'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        Staff::create($data);

        return redirect()->route('app.staff.index')->with('success', 'Staff created.');
    }

    public function edit(Staff $staff)
    {
        $roles = $this->roleOptions();

        return view('app.staff.form', [
            'mode'  => 'edit',
            'staff' => $staff,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'role'      => ['required', 'string', 'max:50'],
            'is_active' => ['nullable'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $staff->update($data);

        return redirect()->route('app.staff.index')->with('success', 'Staff updated.');
    }
}