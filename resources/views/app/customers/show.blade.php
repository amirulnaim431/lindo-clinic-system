<x-internal-layout>
    <x-slot name="title">Customer Profile</x-slot>
    <x-slot name="subtitle">
        Consolidated patient, membership, and appointment information for this customer record.
    </x-slot>

    @php
        $statusLabel = function ($status) {
            if (is_object($status) && property_exists($status, 'value')) {
                return (string) $status->value;
            }

            if (is_object($status) && method_exists($status, 'value')) {
                return (string) $status->value();
            }

            return $status ? (string) $status : null;
        };

        $badgeClass = function ($status) {
            $value = strtolower((string) $status);

            return match ($value) {
                'booked', 'confirmed' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
                'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
                'cancelled', 'canceled' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200',
                'completed', 'done' => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200',
                default => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200',
            };
        };

        $canEditCustomer = auth()->user() && method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin();
    @endphp

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-sm font-semibold text-emerald-800">{{ session('success') }}</p>
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a
                    href="{{ route('app.customers.index') }}"
                    class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-900"
                >
                    ← Back to Customers
                </a>

                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Customer profile</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                        {{ $customer->full_name ?: 'Unnamed Customer' }}
                    </h1>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if($customer->membership_type)
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ $customer->membership_type }}
                            </span>
                        @endif

                        @if($customer->current_package)
                            <span class="inline-flex items-center rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 ring-1 ring-inset ring-violet-200">
                                Package: {{ $customer->current_package }}
                            </span>
                        @endif

                        @if($customer->membership_code)
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                Code: {{ $customer->membership_code }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 lg:items-end">
                @if($canEditCustomer)
                    <a
                        href="{{ route('app.customers.edit', $customer) }}"
                        class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Edit Customer
                    </a>
                @endif

                <div class="grid gap-3 sm:grid-cols-2 lg:w-[28rem]">
                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Upcoming appointments</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $upcomingAppointments->count() }}</p>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Appointment history</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $appointmentHistory->count() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Profile</h2>
                        <p class="mt-1 text-sm text-slate-500">Core customer and identity information.</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Full name</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->full_name ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Phone</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->phone ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Email</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->email ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Date of birth</p>
                            <p class="mt-2 text-sm text-slate-900">
                                {{ $customer->dob ? \Illuminate\Support\Carbon::parse($customer->dob)->format('d M Y') : '—' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">IC / Passport</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->ic_passport ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Gender</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->gender ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Marital status</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->marital_status ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Nationality</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->nationality ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Occupation</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->occupation ?: '—' }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Address</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-900">{{ $customer->address ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Medical / Clinic Info</h2>
                        <p class="mt-1 text-sm text-slate-500">Basic clinical information currently stored in CRM.</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Weight</p>
                            <p class="mt-2 text-sm text-slate-900">
                                {{ $customer->weight !== null ? rtrim(rtrim(number_format((float) $customer->weight, 2, '.', ''), '0'), '.') . ' kg' : '—' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Height</p>
                            <p class="mt-2 text-sm text-slate-900">
                                {{ $customer->height !== null ? rtrim(rtrim(number_format((float) $customer->height, 2, '.', ''), '0'), '.') . ' cm' : '—' }}
                            </p>
                        </div>

                        <div class="md:col-span-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Allergies</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-900">{{ $customer->allergies ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Operational</h2>
                        <p class="mt-1 text-sm text-slate-500">Upcoming appointments and appointment history linked to this customer.</p>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Upcoming appointments</h3>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                    {{ $upcomingAppointments->count() }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                @forelse($upcomingAppointments as $group)
                                    @php
                                        $status = $statusLabel($group->status);
                                    @endphp

                                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ $group->starts_at ? $group->starts_at->format('d M Y, h:i A') : 'Date not available' }}
                                                </p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ $group->ends_at ? 'Until ' . $group->ends_at->format('h:i A') : 'End time not available' }}
                                                </p>
                                            </div>

                                            @if($status)
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($status) }}">
                                                    {{ ucfirst($status) }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-4 space-y-2">
                                            @forelse($group->items as $item)
                                                <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                    <span class="font-medium text-slate-900">{{ $item->service->name ?? 'Service' }}</span>
                                                    <span class="text-slate-400">·</span>
                                                    <span>{{ $item->staff->full_name ?? 'Unassigned staff' }}</span>
                                                </div>
                                            @empty
                                                <p class="text-sm text-slate-500">No appointment item details available.</p>
                                            @endforelse
                                        </div>

                                        @if($group->notes)
                                            <div class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-500">
                                                {{ $group->notes }}
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500">
                                        No upcoming appointments found.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Appointment history</h3>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                    {{ $appointmentHistory->count() }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                @forelse($appointmentHistory as $group)
                                    @php
                                        $status = $statusLabel($group->status);
                                    @endphp

                                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ $group->starts_at ? $group->starts_at->format('d M Y, h:i A') : 'Date not available' }}
                                                </p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ $group->ends_at ? 'Until ' . $group->ends_at->format('h:i A') : 'End time not available' }}
                                                </p>
                                            </div>

                                            @if($status)
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($status) }}">
                                                    {{ ucfirst($status) }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-4 space-y-2">
                                            @forelse($group->items as $item)
                                                <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                    <span class="font-medium text-slate-900">{{ $item->service->name ?? 'Service' }}</span>
                                                    <span class="text-slate-400">·</span>
                                                    <span>{{ $item->staff->full_name ?? 'Unassigned staff' }}</span>
                                                </div>
                                            @empty
                                                <p class="text-sm text-slate-500">No appointment item details available.</p>
                                            @endforelse
                                        </div>

                                        @if($group->notes)
                                            <div class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-500">
                                                {{ $group->notes }}
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500">
                                        No appointment history found.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Emergency Contact</h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Name</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->emergency_contact_name ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Phone</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->emergency_contact_phone ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Membership</h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Membership code</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->membership_code ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Membership type</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->membership_type ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Current package</p>
                            <p class="mt-2 text-sm text-slate-900">{{ $customer->current_package ?: '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Current package since</p>
                            <p class="mt-2 text-sm text-slate-900">
                                {{ $customer->current_package_since ? \Illuminate\Support\Carbon::parse($customer->current_package_since)->format('d M Y') : '—' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-slate-900">Administrative</h2>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Notes</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-900">
                            {{ $customer->notes ?: 'No administrative notes recorded.' }}
                        </p>
                    </div>
                </div>

                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-6 shadow-sm">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold text-slate-900">Future Expansion</h2>
                    </div>

                    <div class="space-y-3 text-sm text-slate-500">
                        <p>Payments and billing history placeholder</p>
                        <p>Package history placeholder</p>
                        <p>Treatment / claims / clinical documents placeholder</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-internal-layout>