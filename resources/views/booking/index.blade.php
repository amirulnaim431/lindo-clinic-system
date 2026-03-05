<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Lindo Clinic — Booking</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900">
    <div class="min-h-screen">
        <div class="mx-auto max-w-3xl px-4 py-10">
            <div class="mb-6">
                <h1 class="text-3xl font-semibold">Book an appointment</h1>
                <p class="mt-1 text-sm text-slate-600">Select a service, pick a date and time slot, then submit.</p>
            </div>

            @if (session('success'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900 text-sm">
                    <div class="font-semibold mb-1">Fix the following:</div>
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <form method="POST" action="{{ route('booking.store') }}" class="grid grid-cols-1 gap-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Service</label>
                        <select id="service_id" name="service_id" class="w-full rounded-xl border-slate-300">
                            <option value="">Select service</option>
                            @foreach($services as $svc)
                                <option value="{{ $svc->id }}" @selected(old('service_id') == (string)$svc->id)>
                                    {{ $svc->name }} ({{ (int)($svc->duration_minutes ?? 60) }} min)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Preferred staff (optional)</label>
                        <select id="staff_id" name="staff_id" class="w-full rounded-xl border-slate-300">
                            <option value="">No preference</option>
                            @foreach($staff as $s)
                                <option value="{{ $s->id }}" @selected(old('staff_id') == (string)$s->id)>
                                    {{ $s->full_name }} ({{ $s->role_key }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                            <input id="date" type="date" name="date" value="{{ old('date', $today ?? now()->format('Y-m-d')) }}"
                                   class="w-full rounded-xl border-slate-300" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Time slot</label>
                            <select id="time" name="time" class="w-full rounded-xl border-slate-300">
                                <option value="">Select a slot</option>
                            </select>
                            <div id="slotHint" class="mt-1 text-xs text-slate-500">
                                Choose service + date to load available slots.
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Your name</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}"
                                   class="w-full rounded-xl border-slate-300" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                            <input type="text" name="customer_phone" value="{{ old('customer_phone') }}"
                                   class="w-full rounded-xl border-slate-300" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full rounded-xl border-slate-300">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Submit booking
                    </button>

                    <div class="text-xs text-slate-500">
                        Dev phase: slots are generated by the backend. If none appear, try another date.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const serviceEl = document.getElementById('service_id');
            const staffEl = document.getElementById('staff_id');
            const dateEl = document.getElementById('date');
            const timeEl = document.getElementById('time');
            const hintEl = document.getElementById('slotHint');

            async function loadSlots() {
                const serviceId = serviceEl.value;
                const staffId = staffEl.value;
                const date = dateEl.value;

                timeEl.innerHTML = '<option value="">Select a slot</option>';

                if (!serviceId || !date) {
                    hintEl.textContent = 'Choose service + date to load available slots.';
                    return;
                }

                hintEl.textContent = 'Loading slots...';

                const params = new URLSearchParams();
                params.set('service_id', serviceId);
                params.set('date', date);
                if (staffId) params.set('staff_id', staffId);

                try {
                    const res = await fetch('/booking/slots?' + params.toString(), {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!res.ok) throw new Error('Failed to load slots');

                    const data = await res.json();
                    const slots = Array.isArray(data.slots) ? data.slots : [];

                    if (!slots.length) {
                        hintEl.textContent = 'No slots available. Try a different date or staff.';
                        return;
                    }

                    slots.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s;
                        opt.textContent = s;
                        timeEl.appendChild(opt);
                    });

                    hintEl.textContent = 'Select a time slot.';
                } catch (e) {
                    hintEl.textContent = 'Could not load slots. Please refresh and try again.';
                }
            }

            serviceEl.addEventListener('change', loadSlots);
            staffEl.addEventListener('change', loadSlots);
            dateEl.addEventListener('change', loadSlots);

            // Attempt initial load (useful after validation errors)
            if (serviceEl.value && dateEl.value) loadSlots();
        })();
    </script>
</body>
</html>