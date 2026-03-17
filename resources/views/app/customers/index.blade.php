<x-internal-layout>
    <x-slot name="title">Customer CRM</x-slot>
    <x-slot name="subtitle">
        Search and review imported customer records, membership details, and patient profile information.
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Customer directory</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">Customers</h2>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500">
                        Search by full name, phone, IC / passport, or membership code.
                    </p>
                </div>

                <form method="GET" action="{{ route('app.customers.index') }}" class="w-full lg:max-w-2xl">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <div class="flex-1">
                            <label for="search" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                Search
                            </label>
                            <input
                                id="search"
                                name="search"
                                type="text"
                                value="{{ $search }}"
                                placeholder="e.g. Nur Aina, 0123456789, 900101-10-1234, MBR-001"
                                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                            >
                        </div>

                        <div class="flex gap-3 sm:items-end">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                            >
                                Search
                            </button>

                            @if($search !== '')
                                <a
                                    href="{{ route('app.customers.index') }}"
                                    class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                >
                                    Reset
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Total records</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($customers->total()) }}</p>
                <p class="mt-2 text-sm text-slate-500">Imported customer records currently available in CRM.</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Current page</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $customers->currentPage() }}</p>
                <p class="mt-2 text-sm text-slate-500">Showing {{ $customers->count() }} records on this page.</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Search state</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                    {{ $search !== '' ? 'Filtered results' : 'Showing all customers' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $search !== '' ? 'Results narrowed by your keyword.' : 'No keyword applied yet.' }}
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-lg font-semibold text-slate-900">Customer records</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Open a profile to review clinic, membership, and appointment information.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Full name</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Phone</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">IC / Passport</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Gender</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Membership type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Current package</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($customers as $customer)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-6 py-5 align-top">
                                    <div class="font-semibold text-slate-900">
                                        {{ $customer->full_name ?: '—' }}
                                    </div>

                                    @if($customer->membership_code)
                                        <div class="mt-1 text-xs text-slate-500">
                                            Membership code: {{ $customer->membership_code }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-5 align-top text-sm text-slate-700">
                                    {{ $customer->phone ?: '—' }}
                                </td>

                                <td class="px-6 py-5 align-top text-sm text-slate-700">
                                    {{ $customer->ic_passport ?: '—' }}
                                </td>

                                <td class="px-6 py-5 align-top text-sm text-slate-700">
                                    {{ $customer->gender ?: '—' }}
                                </td>

                                <td class="px-6 py-5 align-top text-sm text-slate-700">
                                    {{ $customer->membership_type ?: '—' }}
                                </td>

                                <td class="px-6 py-5 align-top">
                                    @if($customer->current_package)
                                        <div class="text-sm font-medium text-slate-900">
                                            {{ $customer->current_package }}
                                        </div>

                                        @if($customer->current_package_since)
                                            <div class="mt-1 text-xs text-slate-500">
                                                Since {{ \Illuminate\Support\Carbon::parse($customer->current_package_since)->format('d M Y') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-sm text-slate-400">—</span>
                                    @endif
                                </td>

                                <td class="px-6 py-5 align-top text-right">
                                    <a
                                        href="{{ route('app.customers.show', $customer) }}"
                                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                    >
                                        View profile
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-14 text-center">
                                    <div class="mx-auto max-w-md">
                                        <h4 class="text-lg font-semibold text-slate-900">No customers found</h4>
                                        <p class="mt-2 text-sm text-slate-500">
                                            Try another keyword using full name, phone number, IC / passport, or membership code.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($customers->hasPages())
            <div class="rounded-3xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</x-internal-layout>