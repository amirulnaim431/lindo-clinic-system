<x-internal-layout>
    @php
        $appointmentDate = function ($appointment) {
            foreach (['start_at', 'scheduled_at', 'appointment_at', 'appointment_date', 'date', 'starts_at'] as $field) {
                if (isset($appointment->{$field}) && !empty($appointment->{$field})) {
                    try {
                        return \Illuminate\Support\Carbon::parse($appointment->{$field});
                    } catch (\Throwable $e) {
                        return null;
                    }
                }
            }

            return null;
        };

        $appointmentStatus = function ($appointment) {
            foreach (['status', 'appointment_status'] as $field) {
                if (isset($appointment->{$field}) && !empty($appointment->{$field})) {
                    return (string) $appointment->{$field};
                }
            }

            return null;
        };

        $appointmentReference = function ($appointment) {
            foreach (['appointment_no', 'code', 'reference_no', 'id'] as $field) {
                if (isset($appointment->{$field}) && !empty($appointment->{$field})) {
                    return (string) $appointment->{$field};
                }
            }

            return null;
        };
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('app.customers.index') }}" class="text-sm font-medium text-slate-500 transition hover:text-slate-900">
                        ← Back to Customers
                    </a>
                </div>

                <p class="mt-4 text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Customer Profile</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">{{ $customer->full_name ?: 'Unnamed Customer' }}</h1>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if($customer->membership_type)
                        <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                            {{ $customer->membership_type }}
                        </span>
                    @endif

                    @if($customer->current_package)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            Package: {{ $customer->current_package }}
                        </span>
                    @endif

                    @if($customer->membership_code)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            Code: {{ $customer->membership_code }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Profile</h2>
                        <p class="mt-1 text-sm text-slate-500">Core customer and identity information.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 px-5 py-5 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Full name</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->full_name ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->phone ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Email</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->email ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Date of birth</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">
                                {{ $customer->dob ? \Illuminate\Support\Carbon::parse($customer->dob)->format('d M Y') : '—' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">IC / Passport</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->ic_passport ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Gender</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->gender ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Marital status</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->marital_status ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Nationality</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->nationality ?: '—' }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Occupation</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->occupation ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Medical / Clinic Info</h2>
                        <p class="mt-1 text-sm text-slate-500">Basic clinical information currently stored in CRM.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 px-5 py-5 md:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Weight</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->weight ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Height</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->height ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Allergies</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->allergies ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Operational</h2>
                        <p class="mt-1 text-sm text-slate-500">Appointments connected to this customer record.</p>
                    </div>

                    @if($hasAppointmentsRelation)
                        <div class="grid grid-cols-1 gap-6 px-5 py-5 xl:grid-cols-2">
                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-900">Upcoming appointments</h3>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                        {{ $upcomingAppointments->count() }}
                                    </span>
                                </div>

                                <div class="space-y-3">
                                    @forelse($upcomingAppointments as $appointment)
                                        @php
                                            $date = $appointmentDate($appointment);
                                            $status = $appointmentStatus($appointment);
                                            $reference = $appointmentReference($appointment);
                                        @endphp

                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">
                                                        {{ $reference ? 'Appointment ' . $reference : 'Appointment' }}
                                                    </p>
                                                    <p class="mt-1 text-sm text-slate-600">
                                                        {{ $date ? $date->format('d M Y, h:i A') : 'Date not available' }}
                                                    </p>
                                                </div>

                                                @if($status)
                                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 border border-slate-200">
                                                        {{ $status }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                                            No upcoming appointments found.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-900">Appointment history</h3>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                        {{ $appointmentHistory->count() }}
                                    </span>
                                </div>

                                <div class="space-y-3">
                                    @forelse($appointmentHistory as $appointment)
                                        @php
                                            $date = $appointmentDate($appointment);
                                            $status = $appointmentStatus($appointment);
                                            $reference = $appointmentReference($appointment);
                                        @endphp

                                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">
                                                        {{ $reference ? 'Appointment ' . $reference : 'Appointment' }}
                                                    </p>
                                                    <p class="mt-1 text-sm text-slate-600">
                                                        {{ $date ? $date->format('d M Y, h:i A') : 'Date not available' }}
                                                    </p>
                                                </div>

                                                @if($status)
                                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                                        {{ $status }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                                            No appointment history found.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="px-5 py-5">
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                                Appointment relationship is not available in the current model state. Keep this section as a placeholder until the customer-to-appointments relationship is confirmed in staging.
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Emergency Contact</h2>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Name</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->emergency_contact_name ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->emergency_contact_phone ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Membership</h2>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Membership code</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->membership_code ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Membership type</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->membership_type ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Current package</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $customer->current_package ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Current package since</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">
                                {{ $customer->current_package_since ? \Illuminate\Support\Carbon::parse($customer->current_package_since)->format('d M Y') : '—' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Administrative</h2>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Notes</p>
                            <div class="mt-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                                {{ $customer->notes ?: 'No administrative notes recorded.' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Future Expansion</h2>
                    </div>

                    <div class="space-y-3 px-5 py-5 text-sm text-slate-600">
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            Payments and billing history placeholder
                        </div>
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            Package history placeholder
                        </div>
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            Treatment / claims / clinical documents placeholder
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-internal-layout>