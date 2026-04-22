<x-internal-layout :title="'Customer Profile'" :subtitle="'Consolidated patient, membership, and appointment information for this customer record.'">
    @php
        $statusLabel = function ($status) {
            if (is_object($status) && property_exists($status, 'value')) {
                return (string) $status->value;
            }
            if (is_object($status) && method_exists($status, 'value')) {
                return (string) $status->value();
            }
            return $status ? (string) $status : null;
        };

        $badgeTone = function ($status) {
            $value = strtolower((string) $status);
            return match ($value) {
                'booked', 'pending' => 'warning',
                'confirmed', 'completed' => 'success',
                'checked_in' => 'info',
                'cancelled', 'canceled', 'no_show' => 'danger',
                default => 'neutral',
            };
        };

        $canEditCustomer = auth()->check() && method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin();
    @endphp

    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <section class="hero-panel">
            <div class="panel-body stack">
                <div class="filter-bar__head">
                    <div>
                        <a href="{{ route('app.customers.index') }}" class="btn btn-secondary">Back to customers</a>
                        <div class="page-kicker mt-4">Customer profile</div>
                        <div class="page-title" style="font-size:2.35rem;">{{ $customer->full_name ?: 'Unnamed Customer' }}</div>

                        <div class="inline-chip-row mt-3">
                            @if($customer->membership_type)
                                <span class="chip">{{ $customer->membership_type }}</span>
                            @endif
                            @if($customer->current_package)
                                <span class="status-chip status-chip--info">Package: {{ $customer->current_package }}</span>
                            @endif
                            @if($customer->membership_code)
                                <span class="status-chip status-chip--success">Code: {{ $customer->membership_code }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="stack" style="width:min(100%, 28rem);">
                        @if($canEditCustomer)
                            <div class="btn-row btn-row--end">
                                <a href="{{ route('app.customers.edit', $customer) }}" class="btn btn-primary">Edit customer</a>
                            </div>
                        @endif

                        <div class="two-col">
                            <x-stat-card label="Upcoming appointments" :value="$upcomingAppointments->count()" />
                            <x-stat-card label="Appointment history" :value="$appointmentHistory->count()" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-grid">
            <div class="stack">
                <div class="panel">
                    <div class="panel-header">
                        <x-section-heading kicker="Profile" title="Core details" subtitle="Customer identity and contact information." />
                    </div>
                    <div class="panel-body">
                        <div class="two-col">
                            <div class="summary-card"><div class="micro-label">Full name</div><div class="selection-card__title mt-2">{{ $customer->full_name ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Phone</div><div class="selection-card__title mt-2">{{ $customer->phone ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Email</div><div class="selection-card__title mt-2">{{ $customer->email ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Date of birth</div><div class="selection-card__title mt-2">{{ $customer->dob ? \Illuminate\Support\Carbon::parse($customer->dob)->format('d M Y') : '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">IC / Passport</div><div class="selection-card__title mt-2">{{ $customer->ic_passport ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Gender</div><div class="selection-card__title mt-2">{{ $customer->gender ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Marital status</div><div class="selection-card__title mt-2">{{ $customer->marital_status ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Nationality</div><div class="selection-card__title mt-2">{{ $customer->nationality ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Occupation</div><div class="selection-card__title mt-2">{{ $customer->occupation ?: '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Address</div><div class="small-note mt-2">{{ $customer->address ?: '-' }}</div></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <x-section-heading kicker="Clinic info" title="Medical / clinic data" subtitle="Basic clinical information currently stored in CRM." />
                    </div>
                    <div class="panel-body">
                        <div class="three-col">
                            <div class="summary-card"><div class="micro-label">Weight</div><div class="selection-card__title mt-2">{{ $customer->weight !== null ? rtrim(rtrim(number_format((float) $customer->weight, 2, '.', ''), '0'), '.') . ' kg' : '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Height</div><div class="selection-card__title mt-2">{{ $customer->height !== null ? rtrim(rtrim(number_format((float) $customer->height, 2, '.', ''), '0'), '.') . ' cm' : '-' }}</div></div>
                            <div class="summary-card"><div class="micro-label">Allergies</div><div class="small-note mt-2">{{ $customer->allergies ?: '-' }}</div></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <x-section-heading kicker="Operational history" title="Appointments" subtitle="Upcoming appointments and appointment history linked to this customer." />
                    </div>
                    <div class="panel-body">
                        <div class="split-grid">
                            <div class="stack">
                                <div class="filter-bar__head">
                                    <div class="selection-card__title">Upcoming appointments</div>
                                    <span class="chip">{{ $upcomingAppointments->count() }}</span>
                                </div>
                                @forelse($upcomingAppointments as $group)
                                    @php $status = $statusLabel($group->status); @endphp
                                    <div class="queue-card">
                                        <div class="filter-bar__head">
                                            <div>
                                                <div class="selection-card__title">{{ $group->starts_at ? $group->starts_at->format('d M Y, h:i A') : 'Date unavailable' }}</div>
                                                <div class="small-note">{{ $group->ends_at ? 'Until '.$group->ends_at->format('h:i A') : 'End time unavailable' }}</div>
                                            </div>
                                            @if($status)
                                                <x-status-pill :label="ucfirst($status)" :tone="$badgeTone($status)" />
                                            @endif
                                        </div>
                                        <div class="stack mt-3">
                                            @forelse($group->items as $item)
                                                <div class="summary-card">
                                                    <div class="filter-bar__head" style="align-items:flex-start;">
                                                        <div>
                                                            <div class="micro-label">{{ $item->displayCategoryLabel() }}</div>
                                                            <div class="selection-card__title mt-2">{{ $item->displayServiceName() }}</div>
                                                        </div>
                                                        @if($item->displayStaffRole())
                                                            <span class="chip">{{ \Illuminate\Support\Str::headline($item->displayStaffRole()) }}</span>
                                                        @endif
                                                    </div>
                                                    @if($item->optionSelections->isNotEmpty())
                                                        <div class="inline-chip-row mt-3">
                                                            @foreach($item->optionSelections as $selection)
                                                                <span class="status-chip status-chip--info">{{ $selection->option_group_name }}: {{ $selection->option_value_label }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    <div class="small-note mt-3">Staff: {{ $item->displayStaffName() }}</div>
                                                </div>
                                            @empty
                                                <div class="small-note">No appointment item details available.</div>
                                            @endforelse
                                        </div>
                                        @if($group->notes)
                                            <div class="small-note mt-3">{{ $group->notes }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="empty-state empty-state--dashed">
                                        <div class="empty-state__title">No upcoming appointments</div>
                                    </div>
                                @endforelse
                            </div>

                            <div class="stack">
                                <div class="filter-bar__head">
                                    <div class="selection-card__title">Appointment history</div>
                                    <span class="chip">{{ $appointmentHistory->count() }}</span>
                                </div>
                                @forelse($appointmentHistory as $group)
                                    @php $status = $statusLabel($group->status); @endphp
                                    <div class="queue-card">
                                        <div class="filter-bar__head">
                                            <div>
                                                <div class="selection-card__title">{{ $group->starts_at ? $group->starts_at->format('d M Y, h:i A') : 'Date unavailable' }}</div>
                                                <div class="small-note">{{ $group->ends_at ? 'Until '.$group->ends_at->format('h:i A') : 'End time unavailable' }}</div>
                                            </div>
                                            @if($status)
                                                <x-status-pill :label="ucfirst($status)" :tone="$badgeTone($status)" />
                                            @endif
                                        </div>
                                        <div class="stack mt-3">
                                            @forelse($group->items as $item)
                                                <div class="summary-card">
                                                    <div class="filter-bar__head" style="align-items:flex-start;">
                                                        <div>
                                                            <div class="micro-label">{{ $item->displayCategoryLabel() }}</div>
                                                            <div class="selection-card__title mt-2">{{ $item->displayServiceName() }}</div>
                                                        </div>
                                                        @if($item->displayStaffRole())
                                                            <span class="chip">{{ \Illuminate\Support\Str::headline($item->displayStaffRole()) }}</span>
                                                        @endif
                                                    </div>
                                                    @if($item->optionSelections->isNotEmpty())
                                                        <div class="inline-chip-row mt-3">
                                                            @foreach($item->optionSelections as $selection)
                                                                <span class="status-chip status-chip--info">{{ $selection->option_group_name }}: {{ $selection->option_value_label }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    <div class="small-note mt-3">Staff: {{ $item->displayStaffName() }}</div>
                                                </div>
                                            @empty
                                                <div class="small-note">No appointment item details available.</div>
                                            @endforelse
                                        </div>
                                        @if($group->notes)
                                            <div class="small-note mt-3">{{ $group->notes }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="empty-state empty-state--dashed">
                                        <div class="empty-state__title">No appointment history</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="panel">
                    <div class="panel-header"><div class="panel-title">Emergency contact</div></div>
                    <div class="panel-body stack">
                        <div class="summary-card"><div class="micro-label">Name</div><div class="selection-card__title mt-2">{{ $customer->emergency_contact_name ?: '-' }}</div></div>
                        <div class="summary-card"><div class="micro-label">Phone</div><div class="selection-card__title mt-2">{{ $customer->emergency_contact_phone ?: '-' }}</div></div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><div class="panel-title">Membership</div></div>
                    <div class="panel-body stack">
                        <div class="summary-card"><div class="micro-label">Membership code</div><div class="selection-card__title mt-2">{{ $customer->membership_code ?: '-' }}</div></div>
                        <div class="summary-card"><div class="micro-label">Membership type</div><div class="selection-card__title mt-2">{{ $customer->membership_type ?: '-' }}</div></div>
                        <div class="summary-card"><div class="micro-label">Current package</div><div class="selection-card__title mt-2">{{ $customer->current_package ?: '-' }}</div></div>
                        <div class="summary-card"><div class="micro-label">Current package since</div><div class="selection-card__title mt-2">{{ $customer->current_package_since ? \Illuminate\Support\Carbon::parse($customer->current_package_since)->format('d M Y') : '-' }}</div></div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><div class="panel-title">Administrative notes</div></div>
                    <div class="panel-body">
                        <div class="small-note">{{ $customer->notes ?: 'No administrative notes recorded.' }}</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-internal-layout>
