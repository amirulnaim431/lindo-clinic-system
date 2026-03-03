@extends('layouts.app')

@section('content')
    <div class="card">
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:14px;">
            <div>
                <h2 style="margin:0 0 6px;">Book an Appointment</h2>
                <p style="margin:0; color:#9ab1c9;">Choose service, staff (optional), and time slot.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert bad">
                <strong>Booking failed:</strong>
                <ul>
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('booking.store') }}" id="bookingForm">
            @csrf

            <div class="grid">
                <div>
                    <label>Customer name</label>
                    <input name="customer_name" value="{{ old('customer_name') }}" placeholder="Full name" required>
                </div>

                <div>
                    <label>Customer phone</label>
                    <input name="customer_phone" value="{{ old('customer_phone') }}" placeholder="e.g. 0123456789" required>
                </div>

                <div>
                    <label>Service</label>
                    <select name="service_id" id="service_id" required>
                        <option value="">-- Choose service --</option>
                        @foreach($services as $s)
                            <option
                                value="{{ $s->id }}"
                                data-duration="{{ $s->duration_minutes }}"
                                {{ old('service_id') == $s->id ? 'selected' : '' }}
                            >
                                {{ $s->label }} ({{ $s->duration_minutes }} min)
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Duration auto-fills from selected service.</div>
                </div>

                <div>
                    <label>Staff (optional)</label>
                    <select name="staff_id" id="staff_id">
                        <option value="">Any available staff</option>
                        @foreach($staff as $st)
                            <option value="{{ $st->id }}" {{ old('staff_id') == $st->id ? 'selected' : '' }}>
                                {{ $st->label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">If none selected, system auto-picks an available staff.</div>
                </div>

                <div>
                    <label>Date</label>
                    <input type="date" id="date" required>
                    <div class="hint">Clinic hours: 09:00–17:00 (for now).</div>
                </div>

                <div>
                    <label>Time slot</label>
                    <select id="time_slot" required>
                        <option value="">Select date + service first</option>
                    </select>
                    <div class="hint" id="slot_hint">Unavailable slots will be disabled.</div>
                </div>

                <div class="span-2">
                    <label>Duration</label>
                    <input type="text" id="duration_display" readonly value="Select a service">
                    <div class="hint">End time is calculated internally (for conflict checks).</div>
                </div>

                <div class="span-2">
                    <label>Notes (optional)</label>
                    <textarea name="notes" rows="3" placeholder="Any extra note...">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- start_at field that backend expects: "Y-m-d H:i" --}}
            <input type="hidden" name="start_at" id="start_at" value="{{ old('start_at') }}">

            <button class="btn" type="submit">Submit booking</button>
        </form>
    </div>

    <script>
        (function () {
            const serviceSelect = document.getElementById('service_id');
            const staffSelect = document.getElementById('staff_id');
            const dateInput = document.getElementById('date');
            const timeSlot = document.getElementById('time_slot');
            const startAtHidden = document.getElementById('start_at');
            const durationDisplay = document.getElementById('duration_display');
            const slotHint = document.getElementById('slot_hint');
            const form = document.getElementById('bookingForm');

            function selectedDuration() {
                const opt = serviceSelect.options[serviceSelect.selectedIndex];
                const d = opt ? opt.getAttribute('data-duration') : null;
                return d ? parseInt(d, 10) : null;
            }

            function updateDurationUI() {
                const d = selectedDuration();
                durationDisplay.value = d ? `${d} minutes` : 'Select a service';
            }

            function setSlotsLoading(msg) {
                timeSlot.innerHTML = '';
                const o = document.createElement('option');
                o.value = '';
                o.textContent = msg;
                timeSlot.appendChild(o);
            }

            function updateStartAtHidden() {
                const d = dateInput.value;
                const t = timeSlot.value;
                if (!d || !t) return;
                startAtHidden.value = `${d} ${t}`;
            }

            async function loadSlots() {
                updateDurationUI();

                const dateVal = dateInput.value;
                const serviceId = serviceSelect.value;
                const staffId = staffSelect.value;

                // Reset start_at until a valid slot picked
                startAtHidden.value = '';

                if (!dateVal || !serviceId) {
                    setSlotsLoading('Select date + service first');
                    return;
                }

                setSlotsLoading('Loading slots...');
                slotHint.textContent = 'Fetching availability...';

                const params = new URLSearchParams({
                    date: dateVal,
                    service_id: serviceId,
                });
                if (staffId) params.append('staff_id', staffId);

                const url = `{{ route('booking.slots') }}?` + params.toString();

                try {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });

                    if (!res.ok) {
                        setSlotsLoading('Failed to load slots');
                        slotHint.textContent = 'Could not fetch availability.';
                        return;
                    }

                    const data = await res.json();

                    // Update duration from server (source of truth)
                    if (data.duration_minutes) {
                        durationDisplay.value = `${data.duration_minutes} minutes`;
                    }

                    timeSlot.innerHTML = '';
                    const first = document.createElement('option');
                    first.value = '';
                    first.textContent = '— Choose a time slot —';
                    timeSlot.appendChild(first);

                    const slots = data.slots || [];
                    let availableCount = 0;

                    for (const s of slots) {
                        const opt = document.createElement('option');
                        opt.value = s.time;

                        if (s.available) {
                            opt.textContent = s.time;
                            availableCount++;
                        } else {
                            opt.textContent = `${s.time} (Booked)`;
                            opt.disabled = true;
                        }

                        timeSlot.appendChild(opt);
                    }

                    slotHint.textContent = availableCount
                        ? `${availableCount} available slot(s) shown.`
                        : 'No slots available for this date/service. Try another day.';

                } catch (e) {
                    setSlotsLoading('Failed to load slots');
                    slotHint.textContent = 'Network error while fetching availability.';
                }
            }

            serviceSelect.addEventListener('change', loadSlots);
            staffSelect.addEventListener('change', loadSlots);
            dateInput.addEventListener('change', loadSlots);
            timeSlot.addEventListener('change', updateStartAtHidden);

            form.addEventListener('submit', (e) => {
                updateStartAtHidden();

                if (!serviceSelect.value) {
                    e.preventDefault();
                    alert('Please select a service.');
                    return;
                }
                if (!dateInput.value) {
                    e.preventDefault();
                    alert('Please select a date.');
                    return;
                }
                if (!timeSlot.value) {
                    e.preventDefault();
                    alert('Please select a time slot.');
                    return;
                }
                if (!startAtHidden.value) {
                    e.preventDefault();
                    alert('Invalid start time.');
                    return;
                }
            });

            // Init
            updateDurationUI();
            setSlotsLoading('Select date + service first');

            // If old start_at exists, split into date + time and load slots
            const oldStart = startAtHidden.value;
            if (oldStart && oldStart.includes(' ')) {
                const parts = oldStart.split(' ');
                if (parts.length >= 2) {
                    dateInput.value = parts[0];
                    // load slots first, then select time
                    loadSlots().then(() => {
                        const oldTime = parts[1].slice(0,5);
                        timeSlot.value = oldTime;
                        updateStartAtHidden();
                    });
                }
            }
        })();
    </script>
@endsection