<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = Carbon::parse($request->get('date', now()->toDateString()))->startOfDay();

        $todayAppointments = Appointment::query()
            ->whereDate('start_at', $date->toDateString());

        $kpi = [
            'today_total' => (clone $todayAppointments)->count(),
            'today_completed' => (clone $todayAppointments)->where('status', 'completed')->count(),
            'today_cancelled' => (clone $todayAppointments)->where('status', 'cancelled')->count(),
            'active_staff' => User::query()->whereIn('role', ['staff', 'admin'])->count(),
        ];

        // Staff view: show only their appointments list preview
        $user = $request->user();
        $myNext = Appointment::query()
            ->with(['customer:id,name', 'service:id,name'])
            ->when($user->role === 'staff', fn($q) => $q->where('staff_id', $user->id))
            ->whereDate('start_at', $date->toDateString())
            ->orderBy('start_at')
            ->limit(6)
            ->get();

        return view('app.dashboard', [
            'date' => $date->toDateString(),
            'kpi' => $kpi,
            'myNext' => $myNext,
        ]);
    }
}