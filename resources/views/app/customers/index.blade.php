<x-internal-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Customer CRM</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">Customers</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Search and review customer records, membership details, and patient profile information from the imported clinic database.
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('app.customers.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_auto]">
                <div>
                    <label for="search" class="mb-2 block text-sm font-medium text-slate-700">
                        Search by full name, phone, IC/passport, or membership code
                    </label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ $search }}"
                        placeholder="e.g. Nur Aisyah / 6012... / 900101-01-1234 / MBR001"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <div class="flex items-end gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Search
                    </button>

                    @if($search !== '')
                        <a
                            href="{{ route('app.customers.index') }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Total records</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($customers->total()) }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Current page</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $customers->currentPage() }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Per page</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $customers->perPage() }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Search state</p>
                <p class="mt-3 text-sm font-semibold text-slate-900">
                    {{ $search !== '' ? 'Filtered results' : 'Showing all customers' }}
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Customer records</h2>
                <p class="mt-1 text-sm text-slate-500">Open a profile to review clinic, membership, and appointment information.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Full name</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">IC / Passport</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Gender</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Membership type</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Current package</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($customers as $customer)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-4 align-top">
                                    <div class="font-semibold text-slate-900">{{ $customer->full_name ?: '—' }}</div>
                                    @if($customer->membership_code)
                                        <div class="mt-1 text-xs text-slate-500">Code: {{ $customer->membership_code }}</div>
                                    @endif
                                </td>

                                <td class="px-5 py-4 align-top text-sm text-slate-700">
                                    {{ $customer->phone ?: '—' }}
                                </td>

                                <td class="px-5 py-4 align-top text-sm text-slate-700">
                                    {{ $customer->ic_passport ?: '—' }}
                                </td>

                                <td class="px-5 py-4 align-top text-sm text-slate-700">
                                    {{ $customer->gender ?: '—' }}
                                </td>

                                <td class="px-5 py-4 align-top text-sm text-slate-700">
                                    {{ $customer->membership_type ?: '—' }}
                                </td>

                                <td class="px-5 py-4 align-top text-sm text-slate-700">
                                    @if($customer->current_package)
                                        <div>{{ $customer->current_package }}</div>
                                        @if($customer->current_package_since)
                                            <div class="mt-1 text-xs text-slate-500">
                                                Since {{ \Illuminate\Support\Carbon::parse($customer->current_package_since)->format('d M Y') }}
                                            </div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-5 py-4 align-top text-right">
                                    <a
                                        href="{{ route('app.customers.show', $customer) }}"
                                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                                    >
                                        View profile
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <div class="mx-auto max-w-md">
                                        <h3 class="text-base font-semibold text-slate-900">No customers found</h3>
                                        <p class="mt-2 text-sm text-slate-500">
                                            Try another keyword using full name, phone number, IC/passport, or membership code.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    </div>
</x-internal-layout>