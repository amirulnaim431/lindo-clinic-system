<x-internal-layout :title="'Appointments'" :subtitle="'Book, review and manage clinic appointments'">

    @php
        $selectedServiceIds = collect(old('service_ids', request('service_ids', [])))
            ->map(fn ($id) => (string) $id)
            ->all();

        $selectedDate = old('appointment_date', request('appointment_date'));
        $selectedSlot = old('appointment_time', request('appointment_time'));

        $availabilityData = $availability ?? null;
        $availableSlots = is_array($availabilityData) ? ($availabilityData['slots'] ?? []) : [];
        $selectedServicesSummary = is_array($availabilityData) ? ($availabilityData['selected_services'] ?? []) : [];
        $checkedDate = is_array($availabilityData) ? ($availabilityData['date'] ?? $selectedDate) : $selectedDate;

        $statusColors = [
            'scheduled' => 'bg-amber-100 text-amber-800 border-amber-200',
            'confirmed' => 'bg-sky-100 text-sky-800 border-sky-200',
            'checked_in' => 'bg-violet-100 text-violet-800 border-violet-200',
            'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
            'no_show' => 'bg-slate-200 text-slate-700 border-slate-300',
        ];
    @endphp

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm">
            <div class="mb-1 font-semibold">Please fix the following:</div>
            <ul class="ml-5 list-disc space-y-1">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        {{-- LEFT: BOOKING FLOW --}}
        <div class="xl:col-span-7 space-y-6">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">Create Appointment</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Select one or more services, choose a date, then check available slots based on eligible staff.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">
                            Live availability check
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <form method="POST" action="{{ route('app.appointments.availability') }}" class="space-y-6">
                        @csrf

                        <div>
                            <label class="mb-3 block text-sm font-semibold text-slate-800">
                                1) Choose Service(s)
                            </label>

                            @if($services->count())
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    @foreach ($services as $service)
                                        @php
                                            $isSelected = in_array((string) $service->id, $selectedServiceIds, true);
                                            $duration = (int) ($service->duration_minutes ?? 60);
                                            $price = $service->price ?? null;
                                        @endphp

                                        <label class="group cursor-pointer rounded-2xl border transition {{ $isSelected ? 'border-slate-900 bg-slate-900 text-white shadow-md' : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm' }}">
                                            <input
                                                type="checkbox"
                                                name="service_ids[]"
                                                value="{{ $service->id }}"
                                                class="sr-only"
                                                {{ $isSelected ? 'checked' : '' }}
                                            >

                                            <div class="p-4">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-slate-900' }}">
                                                            {{ $service->name }}
                                                        </div>

                                                        @if(!empty($service->description))
                                                            <div class="mt-1 text-xs {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">
                                                                {{ $service->description }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="rounded-xl px-2.5 py-1 text-[11px] font-semibold {{ $isSelected ? 'bg-white/10 text-white border border-white/20' : 'bg-slate-100 text-slate-600' }}">
                                                        {{ $duration }} mins
                                                    </div>
                                                </div>

                                                <div class="mt-4 flex items-center justify-between">
                                                    <div class="text-xs {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">
                                                        Eligible staff enforced
                                                    </div>
                                                    <div class="text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-slate-900' }}">
                                                        {{ $price !== null ? 'RM ' . number_format((float) $price, 2) : '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    No services available yet.
                                </div>
                            @endif

                            @error('service_ids')
                                <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                            @enderror
                            @error('service_ids.*')
                                <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="appointment_date" class="mb-2 block text-sm font-semibold text-slate-800">
                                    2) Choose Date
                                </label>
                                <input
                                    id="appointment_date"
                                    name="appointment_date"
                                    type="date"
                                    min="{{ now()->toDateString() }}"
                                    value="{{ $selectedDate }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                    required
                                >
                                @error('appointment_date')
                                    <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="flex items-end">
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
                                    style="background-color:#0f172a;color:#ffffff;border-color:#0f172a;"
                                >
                                    Check Availability
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- AVAILABILITY RESULTS --}}
            @if ($availabilityData)
                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Available Slots</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Date:
                                    <span class="font-medium text-slate-700">
                                        {{ $checkedDate ? \Carbon\Carbon::parse($checkedDate)->format('d M Y') : '—' }}
                                    </span>
                                </p>
                            </div>

                            <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">
                                {{ count($availableSlots) }} slot{{ count($availableSlots) === 1 ? '' : 's' }} found
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-6 space-y-6">
                        {{-- SELECTED SERVICES SUMMARY --}}
                        <div>
                            <div class="mb-3 text-sm font-semibold text-slate-800">Selected Services</div>

                            @if (count($selectedServicesSummary))
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    @foreach ($selectedServicesSummary as $serviceInfo)
                                        @php
                                            $serviceName = $serviceInfo['name'] ?? 'Service';
                                            $durationMinutes = (int) ($serviceInfo['duration_minutes'] ?? 60);
                                            $eligibleStaff = $serviceInfo['eligible_staff'] ?? [];
                                            $eligibleCount = is_array($eligibleStaff) ? count($eligibleStaff) : 0;
                                        @endphp

                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">
                                                        {{ $serviceName }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        {{ $durationMinutes }} mins
                                                    </div>
                                                </div>

                                                <div class="rounded-xl bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 border border-slate-200">
                                                    {{ $eligibleCount }} eligible staff
                                                </div>
                                            </div>

                                            <div class="mt-3 text-xs text-slate-600">
                                                @if ($eligibleCount)
                                                    {{ implode(', ', $eligibleStaff) }}
                                                @else
                                                    No eligible staff mapped yet.
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                                    No service summary available.
                                </div>
                            @endif
                        </div>

                        {{-- SLOT BUTTONS --}}
                        <div>
                            <div class="mb-3 text-sm font-semibold text-slate-800">Choose Time Slot</div>

                            @if (count($availableSlots))
                                <form method="POST" action="{{ route('app.appointments.store') }}" class="space-y-5">
                                    @csrf

                                    @foreach ($selectedServiceIds as $serviceId)
                                        <input type="hidden" name="service_ids[]" value="{{ $serviceId }}">
                                    @endforeach

                                    <input type="hidden" name="appointment_date" value="{{ $checkedDate }}">

                                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
                                        @foreach ($availableSlots as $slot)
                                            @php
                                                $timeValue = $slot['time'] ?? '';
                                                $assignedSummary = $slot['staff_summary'] ?? [];
                                                $isChecked = $selectedSlot === $timeValue;
                                            @endphp

                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="appointment_time"
                                                    value="{{ $timeValue }}"
                                                    class="peer sr-only"
                                                    {{ $isChecked ? 'checked' : '' }}
                                                    required
                                                >

                                                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white hover:border-slate-400 hover:shadow">
                                                    <div class="text-sm font-semibold">
                                                        {{ $timeValue }}
                                                    </div>
                                                    <div class="mt-2 text-[11px] {{ $isChecked ? 'text-slate-200' : 'text-slate-500' }}">
                                                        {{ is_array($assignedSummary) && count($assignedSummary) ? implode(' • ', $assignedSummary) : 'Staff available' }}
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('appointment_time')
                                        <div class="text-sm text-rose-600">{{ $message }}</div>
                                    @enderror

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label for="customer_name" class="mb-2 block text-sm font-semibold text-slate-800">
                                                Customer Name
                                            </label>
                                            <input
                                                id="customer_name"
                                                name="customer_name"
                                                type="text"
                                                value="{{ old('customer_name') }}"
                                                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                                placeholder="Enter customer name"
                                                required
                                            >
                                            @error('customer_name')
                                                <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="customer_phone" class="mb-2 block text-sm font-semibold text-slate-800">
                                                Customer Phone
                                            </label>
                                            <input
                                                id="customer_phone"
                                                name="customer_phone"
                                                type="text"
                                                value="{{ old('customer_phone') }}"
                                                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                                placeholder="e.g. 60123456789"
                                                required
                                            >
                                            @error('customer_phone')
                                                <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div>
                                        <label for="notes" class="mb-2 block text-sm font-semibold text-slate-800">
                                            Notes <span class="font-normal text-slate-400">(optional)</span>
                                        </label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows="4"
                                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                            placeholder="Extra notes, preferences, reminders..."
                                        >{{ old('notes') }}</textarea>
                                        @error('notes')
                                            <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="text-xs text-slate-500">
                                            Booking will use the selected slot and assign eligible available staff.
                                        </div>

                                        <button
                                            type="submit"
                                            class="inline-flex items-center justify-center rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
                                            style="background-color:#0f172a;color:#ffffff;border-color:#0f172a;"
                                        >
                                            Create Appointment
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    No available slots found for the selected services on this date.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT: TODAY / RECENT APPOINTMENTS --}}
        <div class="xl:col-span-5 space-y-6">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Today’s Schedule</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Review appointments and update statuses.
                    </p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($appointments as $appointment)
                        @php
                            $status = $appointment->status ?? 'scheduled';
                            $badgeClass = $statusColors[$status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                            $customerName = $appointment->customer_name
                                ?? $appointment->appointmentGroup?->customer_name
                                ?? 'Customer';
                            $customerPhone = $appointment->customer_phone
                                ?? $appointment->appointmentGroup?->customer_phone
                                ?? '—';
                            $serviceName = $appointment->service?->name ?? 'Service';
                            $staffName = $appointment->staff?->name ?? 'Unassigned';
                        @endphp

                        <div class="px-6 py-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-semibold text-slate-900">
                                            {{ $appointment->appointment_at?->format('h:i A') ?? '—' }}
                                        </div>
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $badgeClass }}">
                                            {{ str_replace('_', ' ', ucfirst($status)) }}
                                        </span>
                                    </div>

                                    <div class="mt-2 text-sm font-medium text-slate-800">
                                        {{ $customerName }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $customerPhone }}
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-2">
                                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                                            <span class="font-semibold text-slate-700">Service:</span>
                                            {{ $serviceName }}
                                        </div>
                                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                                            <span class="font-semibold text-slate-700">Staff:</span>
                                            {{ $staffName }}
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full sm:w-44">
                                    <form method="POST" action="{{ route('app.appointments.status', $appointment) }}">
                                        @csrf
                                        @method('PATCH')

                                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Update Status
                                        </label>

                                        <select
                                            name="status"
                                            onchange="this.form.submit()"
                                            class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                        >
                                            @foreach (['scheduled', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'] as $option)
                                                <option value="{{ $option }}" @selected($status === $option)>
                                                    {{ str_replace('_', ' ', ucfirst($option)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <div class="text-sm font-medium text-slate-700">No appointments yet</div>
                            <div class="mt-1 text-sm text-slate-500">
                                Today’s schedule will appear here once bookings are created.
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-900 to-slate-800 text-white shadow-sm">
                <div class="px-6 py-6">
                    <h3 class="text-lg font-semibold">How this flow works</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-200">
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            1. Choose one or more services.
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            2. Pick a date and check live availability.
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            3. The system only offers slots where eligible staff are free.
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            4. Select a slot, fill in customer details, and create the appointment.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-internal-layout>