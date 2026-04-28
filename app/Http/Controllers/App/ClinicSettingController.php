<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use Illuminate\Http\Request;

class ClinicSettingController extends Controller
{
    public function edit()
    {
        return view('app.settings.edit', [
            'title' => 'Clinic Settings',
            'subtitle' => 'Control appointment board timing and booking capacity.',
            'schedule' => ClinicSetting::appointmentSchedule(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'slot_step_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'boxes_per_slot' => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        ClinicSetting::putAppointmentSchedule([
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'slot_duration_minutes' => (int) $validated['slot_duration_minutes'],
            'slot_step_minutes' => (int) $validated['slot_step_minutes'],
            'boxes_per_slot' => (int) $validated['boxes_per_slot'],
        ]);

        return back()->with('success', 'Clinic settings updated.');
    }
}
