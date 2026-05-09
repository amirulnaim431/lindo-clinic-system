<x-internal-layout :title="'Membership Entry'" :subtitle="'Temporary workspace for Saturday physical-file membership updates.'">
    <div class="stack">
        <section class="hero-panel">
            <div class="panel-body">
                <div class="filter-bar__head">
                    <x-section-heading
                        kicker="Bulk data entry"
                        title="Membership Entry"
                        subtitle="Search one customer, choose Bronze/Silver/Black, enter package balance if available, then save." />

                    <div class="page-actions">
                        <a href="{{ route('app.customers.index') }}" class="btn btn-secondary">Customer Directory</a>
                    </div>
                </div>
            </div>
        </section>

        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash--error">
                Please fix the highlighted membership entry.
                <ul style="margin:8px 0 0 18px;padding:0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="panel">
            <div class="panel-body">
                <form method="GET" action="{{ route('app.customers.membership-entry') }}" class="filter-bar">
                    <div class="field-block" style="flex:1;min-width:280px;">
                        <label class="field-label" for="search">Search customer</label>
                        <input id="search" name="search" class="form-input" value="{{ $search }}" placeholder="Type customer name, phone, IC, or membership code" autofocus>
                    </div>
                    <div class="btn-row" style="align-self:end;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="{{ route('app.customers.membership-entry') }}" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="Results"
                    :title="$search ? 'Matching customers' : 'Start with a search'"
                    subtitle="Save one row at a time. Current package defaults to the selected membership tier if left blank." />
            </div>
            <div class="panel-body stack">
                @if (! $search)
                    <div class="empty-state empty-state--dashed">
                        <div class="empty-state__title">Ready for Saturday data entry</div>
                        <div class="empty-state__body">Search a customer from the physical file, update membership, save, then continue with the next file.</div>
                    </div>
                @elseif ($customers->isEmpty())
                    <div class="empty-state empty-state--dashed">
                        <div class="empty-state__title">No customers found</div>
                        <div class="empty-state__body">Try a shorter name, phone number, IC, or membership code.</div>
                    </div>
                @else
                    <div class="stack">
                        @foreach ($customers as $customer)
                            <form method="POST" action="{{ route('app.customers.membership-entry.update', $customer) }}" class="membership-entry-card">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="search" value="{{ $search }}">

                                <div class="membership-entry-card__identity">
                                    <div>
                                        <div class="selection-card__title">{{ $customer->full_name ?: 'Unnamed customer' }}</div>
                                        <div class="small-note">
                                            {{ $customer->phone ?: 'No phone' }}
                                            @if ($customer->ic_passport)
                                                &middot; IC {{ $customer->ic_passport }}
                                            @endif
                                            @if ($customer->membership_type || $customer->current_package)
                                                &middot; Current: {{ $customer->current_package ?: $customer->membership_type }}
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('app.customers.show', $customer) }}" class="btn btn-secondary">Profile</a>
                                </div>

                                <div class="membership-entry-grid">
                                    <div class="field-block">
                                        <label class="field-label" for="membership_type_{{ $customer->id }}">Membership</label>
                                        <select id="membership_type_{{ $customer->id }}" name="membership_type" class="form-select membership-tier-select">
                                            @foreach ($tiers as $value => $label)
                                                <option value="{{ $value }}" @selected(old('membership_type', $customer->membership_type) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-block">
                                        <label class="field-label" for="membership_code_{{ $customer->id }}">Membership code</label>
                                        <input id="membership_code_{{ $customer->id }}" name="membership_code" class="form-input" value="{{ old('membership_code', $customer->membership_code) }}" placeholder="Optional">
                                    </div>
                                    <div class="field-block">
                                        <label class="field-label" for="current_package_{{ $customer->id }}">Current package</label>
                                        <input id="current_package_{{ $customer->id }}" name="current_package" class="form-input membership-package-input" value="{{ old('current_package', $customer->current_package) }}" placeholder="Auto-fill from membership">
                                    </div>
                                    <div class="field-block">
                                        <label class="field-label" for="current_package_since_{{ $customer->id }}">Package since</label>
                                        <input id="current_package_since_{{ $customer->id }}" name="current_package_since" type="date" class="form-input" value="{{ old('current_package_since', $customer->current_package_since?->format('Y-m-d') ?? now()->toDateString()) }}">
                                    </div>
                                    <div class="field-block">
                                        <label class="field-label" for="membership_package_value_{{ $customer->id }}">Package value (RM)</label>
                                        <input id="membership_package_value_{{ $customer->id }}" name="membership_package_value" type="number" min="0" step="0.01" class="form-input package-value-input" value="{{ old('membership_package_value', $customer->membership_package_value !== null ? number_format($customer->membership_package_value, 2, '.', '') : '') }}" placeholder="e.g. 2500">
                                    </div>
                                    <div class="field-block">
                                        <label class="field-label" for="membership_balance_{{ $customer->id }}">Balance left (RM)</label>
                                        <input id="membership_balance_{{ $customer->id }}" name="membership_balance" type="number" min="0" step="0.01" class="form-input balance-input" value="{{ old('membership_balance', $customer->membership_balance !== null ? number_format($customer->membership_balance, 2, '.', '') : '') }}" placeholder="Leave blank if unknown">
                                    </div>
                                </div>

                                <div class="btn-row btn-row--end">
                                    <button type="submit" class="btn btn-primary">Save membership</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    <style>
        .membership-entry-card {
            border: 1px solid rgba(198, 124, 154, 0.2);
            border-radius: 28px;
            padding: 1.1rem;
            background:
                radial-gradient(circle at top left, rgba(198, 124, 154, 0.12), transparent 34%),
                #fff;
            box-shadow: 0 16px 38px rgba(72, 43, 55, 0.05);
        }

        .membership-entry-card__identity {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .membership-entry-grid {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }

        .membership-entry-grid .field-block {
            grid-column: span 2;
        }

        @media (max-width: 1100px) {
            .membership-entry-grid .field-block {
                grid-column: span 3;
            }
        }

        @media (max-width: 720px) {
            .membership-entry-card__identity {
                align-items: flex-start;
                flex-direction: column;
            }

            .membership-entry-grid {
                grid-template-columns: 1fr;
            }

            .membership-entry-grid .field-block {
                grid-column: span 1;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.membership-entry-card').forEach((card) => {
                const tier = card.querySelector('.membership-tier-select');
                const packageInput = card.querySelector('.membership-package-input');
                const valueInput = card.querySelector('.package-value-input');
                const balanceInput = card.querySelector('.balance-input');

                tier?.addEventListener('change', function () {
                    if (!packageInput?.value && tier.value) {
                        packageInput.value = tier.value;
                    }
                });

                valueInput?.addEventListener('input', function () {
                    if (!balanceInput?.value) {
                        balanceInput.value = valueInput.value;
                    }
                });
            });
        });
    </script>
</x-internal-layout>
