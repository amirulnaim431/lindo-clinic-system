<x-internal-layout>
    <x-slot name="title">Edit Customer</x-slot>
    <x-slot name="subtitle">
        Update customer master data while keeping appointment and treatment history read-only.
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Customer maintenance</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                    {{ $customer->full_name ?: 'Unnamed Customer' }}
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Edit customer profile, clinic info, membership, and administrative details.
                </p>
            </div>

            <div class="flex gap-3">
                <a
                    href="{{ route('app.customers.show', $customer) }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-900"
                >
                    Cancel
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-rose-800">Please correct the highlighted fields.</h2>
                <ul class="mt-3 space-y-1 text-sm text-rose-700">
                    @foreach ($errors->all() as $error)
                        <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('app.customers.update', $customer) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-slate-900">Profile</h2>
                            <p class="mt-1 text-sm text-slate-500">Core customer identity and contact information.</p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="full_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Full name
                                </label>
                                <input
                                    id="full_name"
                                    name="full_name"
                                    type="text"
                                    value="{{ old('full_name', $customer->full_name) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                    required
                                >
                            </div>

                            <div>
                                <label for="phone" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Phone
                                </label>
                                <input
                                    id="phone"
                                    name="phone"
                                    type="text"
                                    value="{{ old('phone', $customer->phone) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="email" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Email
                                </label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email', $customer->email) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="dob" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Date of birth
                                </label>
                                <input
                                    id="dob"
                                    name="dob"
                                    type="date"
                                    value="{{ old('dob', $customer->dob?->format('Y-m-d')) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="ic_passport" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    IC / Passport
                                </label>
                                <input
                                    id="ic_passport"
                                    name="ic_passport"
                                    type="text"
                                    value="{{ old('ic_passport', $customer->ic_passport) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="gender" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Gender
                                </label>
                                <input
                                    id="gender"
                                    name="gender"
                                    type="text"
                                    value="{{ old('gender', $customer->gender) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="marital_status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Marital status
                                </label>
                                <input
                                    id="marital_status"
                                    name="marital_status"
                                    type="text"
                                    value="{{ old('marital_status', $customer->marital_status) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="nationality" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Nationality
                                </label>
                                <input
                                    id="nationality"
                                    name="nationality"
                                    type="text"
                                    value="{{ old('nationality', $customer->nationality) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="occupation" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Occupation
                                </label>
                                <input
                                    id="occupation"
                                    name="occupation"
                                    type="text"
                                    value="{{ old('occupation', $customer->occupation) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div class="md:col-span-2">
                                <label for="address" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Address
                                </label>
                                <textarea
                                    id="address"
                                    name="address"
                                    rows="4"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >{{ old('address', $customer->address) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-slate-900">Medical / Clinic Info</h2>
                            <p class="mt-1 text-sm text-slate-500">Editable customer clinic information stored in CRM.</p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="weight" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Weight (kg)
                                </label>
                                <input
                                    id="weight"
                                    name="weight"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value="{{ old('weight', $customer->weight) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="height" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Height (cm)
                                </label>
                                <input
                                    id="height"
                                    name="height"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value="{{ old('height', $customer->height) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div class="md:col-span-2">
                                <label for="allergies" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Allergies
                                </label>
                                <textarea
                                    id="allergies"
                                    name="allergies"
                                    rows="4"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >{{ old('allergies', $customer->allergies) }}</textarea>
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
                                <label for="emergency_contact_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Name
                                </label>
                                <input
                                    id="emergency_contact_name"
                                    name="emergency_contact_name"
                                    type="text"
                                    value="{{ old('emergency_contact_name', $customer->emergency_contact_name) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="emergency_contact_phone" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Phone
                                </label>
                                <input
                                    id="emergency_contact_phone"
                                    name="emergency_contact_phone"
                                    type="text"
                                    value="{{ old('emergency_contact_phone', $customer->emergency_contact_phone) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-slate-900">Membership</h2>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="membership_code" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Membership code
                                </label>
                                <input
                                    id="membership_code"
                                    name="membership_code"
                                    type="text"
                                    value="{{ old('membership_code', $customer->membership_code) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="membership_type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Membership type
                                </label>
                                <input
                                    id="membership_type"
                                    name="membership_type"
                                    type="text"
                                    value="{{ old('membership_type', $customer->membership_type) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="current_package" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Current package
                                </label>
                                <input
                                    id="current_package"
                                    name="current_package"
                                    type="text"
                                    value="{{ old('current_package', $customer->current_package) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>

                            <div>
                                <label for="current_package_since" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Current package since
                                </label>
                                <input
                                    id="current_package_since"
                                    name="current_package_since"
                                    type="date"
                                    value="{{ old('current_package_since', $customer->current_package_since?->format('Y-m-d')) }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-slate-900">Administrative</h2>
                        </div>

                        <div>
                            <label for="notes" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Notes
                            </label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="6"
                                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                            >{{ old('notes', $customer->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Read-only operational history</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Previous appointments, services performed, and appointment history remain read-only here to preserve audit integrity.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <a
                    href="{{ route('app.customers.show', $customer) }}"
                    class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-900"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold shadow-sm transition"
                    style="background: #0f172a; color: #ffffff; border: 1px solid #0f172a;"
                    onmouseover="this.style.background='#1e293b';this.style.borderColor='#1e293b';"
                    onmouseout="this.style.background='#0f172a';this.style.borderColor='#0f172a';"
                >
                    Save customer changes
                </button>
            </div>
        </form>
    </div>
</x-internal-layout>