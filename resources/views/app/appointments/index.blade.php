<x-internal-layout :title="'Appointments'" :subtitle="'Schedule & Operations'">

    @if ($errors->any())
        <div style="background:#fee2e2;color:#7f1d1d;padding:12px;border-radius:8px;margin-bottom:15px;">
            <ul style="margin-left:15px;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:25px;">
        <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
            <div style="font-weight:600;font-size:18px;">
                Create appointment
            </div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;">
                Dev phase: 1 hour sessions (09:00–17:00)
            </div>
        </div>

        <div style="padding:20px;">

            {{-- DATE + SERVICES --}}
            <form method="GET" action="{{ route('app.appointments.index') }}">

                <div style="margin-bottom:15px;">
                    <label style="font-size:12px;font-weight:600;">Date</label><br>
                    <input type="date"
                           name="date"
                           value="{{ $filters['date'] ?? now()->toDateString() }}"
                           style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;margin-top:5px;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="font-size:12px;font-weight:600;">
                        Services (multi-select)
                    </label><br>

                    <select name="service_ids[]"
                            multiple
                            style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;margin-top:5px;min-height:100px;">
                        @foreach($services as $svc)
                            <option value="{{ $svc->id }}"
                                @selected(in_array($svc->id, $filters['service_ids'] ?? []))>
                                {{ $svc->name }}
                            </option>
                        @endforeach
                    </select>

                    <div style="font-size:11px;color:#64748b;margin-top:5px;">
                        Hold Ctrl / Command to select multiple.
                    </div>
                </div>

                <div style="margin-top:15px;display:flex;gap:10px;">

                    <button type="submit"
                        style="background:#111827;color:#ffffff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;">
                        Check available slots
                    </button>

                    <a href="{{ route('app.appointments.index') }}"
                       style="background:#ffffff;color:#111827;padding:10px 18px;border-radius:8px;border:1px solid #cbd5e1;text-decoration:none;font-weight:600;">
                        Clear
                    </a>

                </div>

            </form>

            {{-- AVAILABLE SLOTS --}}
            @if(!empty($availability))
                <div style="margin-top:30px;border-top:1px solid #e2e8f0;padding-top:20px;">
                    <div style="font-weight:600;margin-bottom:10px;">
                        Available slots
                    </div>

                    <div style="display:flex;flex-wrap:wrap;gap:10px;">

                        @forelse($availability['viableSlots'] as $slot)

                            <form method="POST" action="{{ route('app.appointments.store') }}">
                                @csrf
                                <input type="hidden" name="date" value="{{ $filters['date'] }}">
                                @foreach(($filters['service_ids'] ?? []) as $sid)
                                    <input type="hidden" name="service_ids[]" value="{{ $sid }}">
                                @endforeach
                                <input type="hidden" name="time" value="{{ $slot }}">

                                <button
                                    style="background:#ffffff;color:#111827;padding:8px 16px;border-radius:8px;border:1px solid #cbd5e1;font-weight:600;cursor:pointer;">
                                    {{ $slot }}
                                </button>
                            </form>

                        @empty
                            <div style="color:#64748b;font-size:14px;">
                                No slots available for selected services.
                            </div>
                        @endforelse

                    </div>
                </div>
            @endif

        </div>
    </div>


    {{-- APPOINTMENT LIST --}}
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;">

        <div style="padding:20px;border-bottom:1px solid #e2e8f0;">
            <div style="font-weight:600;">
                Appointments
            </div>
            <div style="font-size:12px;color:#64748b;">
                Showing {{ $filters['date'] ?? now()->toDateString() }}
            </div>
        </div>

        <div style="padding:20px;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <th align="left">Time</th>
                        <th align="left">Customer</th>
                        <th align="left">Services</th>
                        <th align="left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointmentGroups as $g)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 0;">
                                {{ optional($g->starts_at)->format('H:i') }}
                            </td>
                            <td>
                                {{ $g->customer?->full_name ?? '-' }}
                            </td>
                            <td>
                                {{ $g->services_summary ?? '-' }}
                            </td>
                            <td>
                                {{ $g->status?->label() ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding:20px;text-align:center;color:#64748b;">
                                No appointments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

</x-internal-layout>