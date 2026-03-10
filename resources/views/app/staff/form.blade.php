<x-internal-layout
    :title="$mode === 'create' ? 'New Staff' : 'Edit Staff'"
    :subtitle="$mode === 'create' ? 'Create a staff member and assign allowed services.' : 'Update staff details and service assignments.'"
>
    @php
        $selectedServiceIds = collect(old('service_ids', $selectedServiceIds ?? []))
            ->map(fn ($id) => (string) $id)
            ->all();
    @endphp

    <style>
        .service-card {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 1rem;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            transition: all 0.18s ease;
        }

        .service-card:hover {
            border-color: #e7b7b0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .service-card.is-selected {
            border-color: #d6a39a;
            background: linear-gradient(180deg, #fff8f6 0%, #fff1ee 100%);
            box-shadow: 0 0 0 3px rgba(214, 163, 154, 0.20);
        }

        .service-card-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            padding: 0.25rem 0.625rem;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            background: #ffffff;
            transition: all 0.18s ease;
        }

        .service-card.is-selected .service-card-badge {
            border-color: #d6a39a;
            color: #9a5c52;
            background: #ffffff;
        }

        .service-card-muted {
            color: #64748b;
            transition: color 0.18s ease;
        }

        .service-card.is-selected .service-card-muted {
            color: #9a5c52;
        }

        .service-card-title {
            color: #0f172a;
            transition: color 0.18s ease;
        }

        .service-card.is-selected .service-card-title {
            color: #7c3f35;
        }
    </style>

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

    <div class="max-w-4xl">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-xl font-semibold text-slate-900">
                    {{ $mode === 'create' ? 'Create Staff' : 'Update Staff' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Roles should match your appointment engine, and services assigned here determine appointment eligibility.
                </p>
            </div>

            <form
                method="POST"
                action="{{ $mode === 'create' ? route('app.staff.store') : route('app.staff.update', $staff) }}"
                class="space-y-6 px-6 py-6"
            >
                @csrf
                @if($mode === 'edit')
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="full_name" class="mb-2 block text-sm font-semibold text-slate-800">
                            Full Name
                        </label>
                        <input
                            id="full_name"
                            name="full_name"
                            type="text"
                            value="{{ old('full_name', $staff->full_name) }}"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                            required
                        >
                    </div>

                    <div>
                        <label for="role" class="mb-2 block text-sm font-semibold text-slate-800">
                            Role
                        </label>
                        <select
                            id="role"
                            name="role"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                            required
                        >
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}" @selected(old('role', $staff->role) === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            class="h-4 w-4 rounded border-slate-300 text-rose-500 focus:ring-rose-300"
                            @checked(old('is_active', $staff->is_active) ? true : false)
                        >
                        <span class="text-sm font-medium text-slate-800">Active staff</span>
                    </label>
                    <p class="mt-2 text-xs text-slate-500">
                        Only active staff can be considered for appointment availability.
                    </p>
                </div>

                <div>
                    <div class="mb-2 block text-sm font-semibold text-slate-800">
                        Allowed Services
                    </div>
                    <p class="mb-4 text-sm text-slate-500">
                        Select which services this staff member is allowed to perform.
                    </p>

                    @if($services->count())
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @foreach($services as $service)
                                @php
                                    $isSelected = in_array((string) $service->id, $selectedServiceIds, true);
                                @endphp

                                <label class="block cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="service_ids[]"
                                        value="{{ $service->id }}"
                                        class="service-checkbox sr-only"
                                        {{ $isSelected ? 'checked' : '' }}
                                    >

                                    <div class="service-card {{ $isSelected ? 'is-selected' : '' }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="service-card-title text-sm font-semibold">
                                                    {{ $service->name }}
                                                </div>

                                                @if(!empty($service->description))
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        {{ $service->description }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="service-card-badge">
                                                {{ (int) ($service->duration_minutes ?? 60) }} mins
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center justify-between">
                                            <div class="service-card-muted text-xs">
                                                Click to assign service
                                            </div>

                                            <div class="service-card-badge service-state-badge">
                                                {{ $isSelected ? 'Selected' : 'Available' }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            No active services available for assignment.
                        </div>
                    @endif

                    @error('service_ids')
                        <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                    @enderror

                    @error('service_ids.*')
                        <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <a
                        href="{{ route('app.staff.index') }}"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        ← Back
                    </a>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                        style="background-color:#0f172a;color:#ffffff;border-color:#0f172a;"
                    >
                        {{ $mode === 'create' ? 'Create Staff' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.service-checkbox');

            const refreshCardState = (checkbox) => {
                const card = checkbox.closest('label')?.querySelector('.service-card');
                if (!card) return;

                card.classList.toggle('is-selected', checkbox.checked);

                const badge = card.querySelector('.service-state-badge');
                if (badge) {
                    badge.textContent = checkbox.checked ? 'Selected' : 'Available';
                }
            };

            checkboxes.forEach((checkbox) => {
                refreshCardState(checkbox);
                checkbox.addEventListener('change', function () {
                    refreshCardState(this);
                });
            });
        });
    </script>
</x-internal-layout>
