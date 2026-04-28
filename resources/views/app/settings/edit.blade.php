<x-internal-layout :title="$title" :subtitle="$subtitle">
    <div class="ops-shell">
        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash--error">
                Please fix the highlighted settings before saving.
            </div>
        @endif

        <section class="panel">
            <div class="panel-header">
                <div>
                    <div class="compact-label">Appointment board</div>
                    <h3 class="panel-title-display" style="font-size:24px;">Time controls</h3>
                </div>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('app.settings.update') }}" class="stack">
                    @csrf
                    @method('PUT')

                    <div class="settings-grid">
                        <div class="field-block">
                            <label class="field-label" for="start_time">First appointment slot</label>
                            <input id="start_time" name="start_time" type="time" value="{{ old('start_time', $schedule['start_time']) }}" class="form-input" required>
                            @error('start_time') <div class="small-note">{{ $message }}</div> @enderror
                        </div>

                        <div class="field-block">
                            <label class="field-label" for="end_time">Clinic closing time</label>
                            <input id="end_time" name="end_time" type="time" value="{{ old('end_time', $schedule['end_time']) }}" class="form-input" required>
                            @error('end_time') <div class="small-note">{{ $message }}</div> @enderror
                        </div>

                        <div class="field-block">
                            <label class="field-label" for="slot_duration_minutes">Displayed slot length</label>
                            <input id="slot_duration_minutes" name="slot_duration_minutes" type="number" min="15" max="240" value="{{ old('slot_duration_minutes', $schedule['slot_duration_minutes']) }}" class="form-input" required>
                            <div class="small-note">Example: 45 shows 10:00 AM - 10:45 AM.</div>
                        </div>

                        <div class="field-block">
                            <label class="field-label" for="slot_step_minutes">Time gap between slots</label>
                            <input id="slot_step_minutes" name="slot_step_minutes" type="number" min="15" max="240" value="{{ old('slot_step_minutes', $schedule['slot_step_minutes']) }}" class="form-input" required>
                            <div class="small-note">Example: 60 gives 10:00, 11:00, 12:00.</div>
                        </div>

                        <div class="field-block">
                            <label class="field-label" for="boxes_per_slot">Boxes per staff slot</label>
                            <input id="boxes_per_slot" name="boxes_per_slot" type="number" min="1" max="4" value="{{ old('boxes_per_slot', $schedule['boxes_per_slot']) }}" class="form-input" required>
                            <div class="small-note">Default is 2 customers per staff time window.</div>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">Save settings</button>
                        <a href="{{ route('app.calendar') }}" class="btn btn-secondary">View calendar</a>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
    </style>
</x-internal-layout>
