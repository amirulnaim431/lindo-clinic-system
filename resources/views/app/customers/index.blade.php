<x-internal-layout :title="'Customer CRM'" :subtitle="'Search and review imported customer records, membership details, and patient profile information.'">
    <div class="stack">
        <section class="hero-panel">
            <div class="panel-body stack">
                <div class="filter-bar__head">
                    <x-section-heading
                        kicker="Customer directory"
                        title="Customers"
                        subtitle="Search by full name, phone, IC or passport, or membership code." />

                    <form method="GET" action="{{ route('app.customers.index') }}" class="split-grid" style="width:min(100%, 44rem);">
                        <div class="field-block">
                            <label for="search" class="field-label">Search</label>
                            <input
                                id="search"
                                name="search"
                                type="text"
                                value="{{ $search }}"
                                placeholder="e.g. Nur Aina, 0123456789, MBR-001"
                                class="form-input"
                            >
                        </div>

                        <div class="field-block" style="align-self:end;">
                            <div class="btn-row" style="justify-content:flex-end;">
                                <button type="submit" class="btn btn-primary">Search</button>
                                @if($search !== '')
                                    <a href="{{ route('app.customers.index') }}" class="btn btn-secondary">Reset</a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="three-col">
            <x-stat-card label="Total records" :value="number_format($customers->total())" meta="Imported customer records available in CRM." />
            <x-stat-card label="Current page" :value="$customers->currentPage()" :meta="'Showing '.$customers->count().' records on this page.'" />
            <x-stat-card label="Search state" :value="$search !== '' ? 'Filtered' : 'All records'" :meta="$search !== '' ? 'Results narrowed by your keyword.' : 'No keyword applied yet.'" />
        </section>

        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="Records"
                    title="Customer profiles"
                    subtitle="Open a profile to review clinic, membership, and appointment information." />
            </div>

            <div class="panel-body">
                <div class="table-shell">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Full name</th>
                                    <th>Phone</th>
                                    <th>IC / Passport</th>
                                    <th>Gender</th>
                                    <th>Membership type</th>
                                    <th>Current package</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customers as $customer)
                                    <tr>
                                        <td>
                                            <div class="selection-card__title">{{ $customer->full_name ?: '-' }}</div>
                                            @if($customer->membership_code)
                                                <div class="small-note">Membership code: {{ $customer->membership_code }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $customer->phone ?: '-' }}</td>
                                        <td>{{ $customer->ic_passport ?: '-' }}</td>
                                        <td>{{ $customer->gender ?: '-' }}</td>
                                        <td>{{ $customer->membership_type ?: '-' }}</td>
                                        <td>
                                            @if($customer->current_package)
                                                <div class="selection-card__title">{{ $customer->current_package }}</div>
                                                @if($customer->current_package_since)
                                                    <div class="small-note">Since {{ \Illuminate\Support\Carbon::parse($customer->current_package_since)->format('d M Y') }}</div>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('app.customers.show', $customer) }}" class="btn btn-secondary">View profile</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state empty-state--dashed">
                                                <div class="empty-state__title">No customers found</div>
                                                <div class="empty-state__body">Try another keyword using full name, phone number, IC or passport, or membership code.</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @if($customers->hasPages())
            <div class="panel">
                <div class="panel-body">
                    {{ $customers->links() }}
                </div>
            </div>
        @endif
    </div>
</x-internal-layout>
