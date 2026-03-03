<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    private array $roles = [
        'therapist' => 'Therapist',
        'doctor' => 'Doctor',
        'assistant' => 'Assistant',
        'frontdesk' => 'Front Desk',
    ];

    public function index()
    {
        $staff = Staff::orderBy('full_name')->paginate(20);

        return view('admin.staff.index', [
            'staff' => $staff,
            'roles' => $this->roles,
        ]);
    }

    public function create()
    {
        return view('admin.staff.form', [
            'staff' => new Staff(),
            'roles' => $this->roles,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys($this->roles))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool)($data['is_active'] ?? false);

        Staff::create($data);

        return redirect()->route('admin.staff.index')
            ->with('success', 'Staff created successfully.');
    }

    public function edit(Staff $staff)
    {
        return view('admin.staff.form', [
            'staff' => $staff,
            'roles' => $this->roles,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys($this->roles))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $staff->update($data);

        return redirect()->route('admin.staff.index')
            ->with('success', 'Staff updated successfully.');
    }
}