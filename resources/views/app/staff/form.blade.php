<x-internal-layout
    :title="$mode === 'create' ? 'New Staff' : 'Edit Staff'"
    :subtitle="$mode === 'create' ? 'Create a staff member and assign allowed services.' : 'Update staff details and service assignments.'"
>
    @php
        $selectedServiceIds = collect(old('service_ids', $selectedServiceIds ?? []))
            ->map(fn ($id) => (string) $id)
            ->all();
    @endphp

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
                                        class="peer sr-only"
                                        {{ $isSelected ? 'checked' : '' }}
                                    >

                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition
                                                hover:border-rose-300 hover:shadow
                                                peer-checked:border-rose-300 peer-checked:bg-rose-50 peer-checked:ring-2 peer-checked:ring-rose-200">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold text-slate-900 peer-checked:text-rose-900">
                                                    {{ $service->name }}
                                                </div>

                                                @if(!empty($service->description))
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        {{ $service->description }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="rounded-xl bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition
                                                        peer-checked:bg-rose-100 peer-checked:text-rose-800">
                                                {{ (int) ($service->duration_minutes ?? 60) }} mins
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center justify-between">
                                            <div class="text-xs text-slate-500 peer-checked:text-rose-700">
                                                Click to assign service
                                            </div>

                                            <div class="rounded-full border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-500 transition
                                                        peer-checked:border-rose-300 peer-checked:bg-white peer-checked:text-rose-700">
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
</x-internal-layout>