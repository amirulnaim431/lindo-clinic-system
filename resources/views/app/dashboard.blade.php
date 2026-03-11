<x-internal-layout
    :title="'Dashboard'"
    :subtitle="'Clinic operations overview'">

    {{-- KPI CARDS --}}
    <div class="grid grid-cols-4 gap-6 mb-8">

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="text-sm text-slate-500">Today Appointments</div>
            <div class="text-2xl font-semibold mt-2">
                {{ $todayAppointments ?? 0 }}
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="text-sm text-slate-500">Total Staff</div>
            <div class="text-2xl font-semibold mt-2">
                {{ $staffCount ?? 0 }}
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="text-sm text-slate-500">Upcoming</div>
            <div class="text-2xl font-semibold mt-2">
                {{ $upcomingAppointments ?? 0 }}
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="text-sm text-slate-500">Completed Today</div>
            <div class="text-2xl font-semibold mt-2">
                {{ $completedToday ?? 0 }}
            </div>
        </div>

    </div>


    {{-- TODAY APPOINTMENTS PANEL --}}
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">

        <div class="px-6 py-4 border-b border-slate-200 font-semibold">
            Today's Schedule
        </div>

        <div class="p-6">

            @if(isset($todayList) && count($todayList))

                <table class="w-full text-sm">

                    <thead class="text-left text-slate-500 border-b">
                        <tr>
                            <th class="py-2">Time</th>
                            <th class="py-2">Customer</th>
                            <th class="py-2">Service</th>
                            <th class="py-2">Staff</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($todayList as $row)

                        <tr class="border-b last:border-0">

                            <td class="py-2">
                                {{ $row->start_time }}
                            </td>

                            <td>
                                {{ $row->customer_name }}
                            </td>

                            <td>
                                {{ $row->service_name }}
                            </td>

                            <td>
                                {{ $row->staff_name }}
                            </td>

                            <td>

                                <span class="px-2 py-1 rounded text-xs bg-slate-100">
                                    {{ $row->status }}
                                </span>

                            </td>

                        </tr>

                        @endforeach

                    </tbody>

                </table>

            @else

                <div class="text-sm text-slate-500">
                    No appointments scheduled today.
                </div>

            @endif

        </div>

    </div>

</x-internal-layout>