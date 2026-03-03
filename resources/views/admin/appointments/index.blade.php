{{-- resources/views/admin/appointments/index.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px;">
            <h2 style="margin:0;">Admin · Appointments</h2>

            @if (session('success'))
                <div style="padding:8px 12px; border:1px solid #16a34a; background:#f0fdf4; color:#166534; border-radius:8px;">
                    {{ session('success') }}
                </div>
            @endif
        </div>
    </x-slot>

    <div style="padding: 24px 0;">
        <div style="max-width: 1100px; margin: 0 auto; padding: 0 16px;">

            @if ($errors->any())
                <div style="padding:10px 12px; border:1px solid #dc2626; background:#fef2f2; color:#991b1b; border-radius:8px; margin-bottom: 12px;">
                    <strong>Fix:</strong>
                    <ul style="margin:6px 0 0 18px;">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="GET" action="{{ route('admin.appointments.index') }}"
                  style="display:flex; gap:12px; align-items:end; flex-wrap:wrap; padding:12px; border:1px solid #e5e7eb; border-radius:12px; margin-bottom: 16px; background: white;">
                <div>
                    <label style="display:block; font-size:12px; color:#374151; margin-bottom:6px;">Date</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}"
                           style="padding:8px 10px; border:1px solid #d1d5db; border-radius:8px;">
                </div>

                <div>
                    <label style="display:block; font-size:12px; color:#374151; margin-bottom:6px;">Staff</label>
                    <select name="staff_id" style="padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; min-width: 220px;">
                        <option value="">All Staff</option>
                        @foreach ($staffList as $s)
                            <option value="{{ $s->id }}" @selected((string)$filters['staff_id'] === (string)$s->id)>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                        style="padding:9px 14px; border:0; border-radius:10px; background:#111827; color:white; cursor:pointer;">
                    Filter
                </button>

                <a href="{{ route('admin.appointments.index') }}"
                   style="padding:9px 14px; border:1px solid #d1d5db; border-radius:10px; background:white; color:#111827; text-decoration:none;">
                    Reset
                </a>
            </form>

            <div style="overflow:auto; border:1px solid #e5e7eb; border-radius:12px; background: white;">
                <table style="width:100%; border-collapse:collapse; min-width: 900px;">
                    <thead>
                        <tr style="background:#f9fafb; text-align:left;">
                            <th style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">Time</th>
                            <th style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">Customer</th>
                            <th style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">Service</th>
                            <th style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">Staff</th>
                            <th style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">Status</th>
                            <th style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($appointments as $a)
                            <tr>
                                <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6;">
                                    {{ $a->start_at?->format('H:i') }} - {{ $a->end_at?->format('H:i') }}
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6;">
                                    {{ $a->customer?->name ?? '—' }}
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6;">
                                    {{ $a->service?->name ?? '—' }}
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6;">
                                    {{ $a->staff?->name ?? '—' }}
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6;">
                                    <span style="padding:4px 8px; border-radius:999px; border:1px solid #e5e7eb;">
                                        {{ $a->status?->label() ?? '—' }}
                                    </span>
                                </td>
                                <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6;">
                                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">

                                        <form method="POST" action="{{ route('admin.appointments.status', $a) }}" style="display:flex; gap:6px; align-items:center;">
                                            @csrf
                                            @method('PATCH')

                                            <select name="status" style="padding:6px 8px; border:1px solid #d1d5db; border-radius:8px;">
                                                @foreach ($statusOptions as $opt)
                                                    <option value="{{ $opt->value }}" @selected($a->status?->value === $opt->value)>
                                                        {{ $opt->label() }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <button type="submit"
                                                    style="padding:7px 10px; border:1px solid #d1d5db; border-radius:10px; background:white; cursor:pointer;">
                                                Update
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.appointments.complete', $a) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    style="padding:7px 10px; border:0; border-radius:10px; background:#16a34a; color:white; cursor:pointer;">
                                                Mark Completed
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding:14px 12px; color:#6b7280;">No appointments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 12px;">
                {{ $appointments->links() }}
            </div>
        </div>
    </div>
</x-app-layout>