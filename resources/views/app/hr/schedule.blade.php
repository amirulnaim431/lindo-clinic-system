<x-internal-layout :title="$title" :subtitle="$subtitle">
    <div class="stack hr-schedule-page">
        <section class="panel hr-schedule-hero">
            <div class="panel-body">
                <div class="hr-schedule-hero__intro">
                    <div>
                        <div class="page-kicker">HR workspace</div>
                        <h2 class="panel-title-display">Staff Schedule</h2>
                        <p class="panel-subtitle">Mockup preview using current staff records. This gives HR and admin a clear weekly roster board now, while we wait for the final scheduling rules and controls.</p>
                    </div>

                    <div class="btn-row">
                        <a href="{{ route('app.hr.schedule', array_filter(['week' => $previousWeek, 'search' => $filters['search'], 'department' => $filters['department'], 'status' => $filters['status']])) }}" class="btn btn-secondary">&larr; Previous week</a>
                        <div class="topbar-badge">{{ $weekLabel }}</div>
                        <a href="{{ route('app.hr.schedule', array_filter(['week' => $nextWeek, 'search' => $filters['search'], 'department' => $filters['department'], 'status' => $filters['status']])) }}" class="btn btn-secondary">Next week &rarr;</a>
                    </div>
                </div>

                <div class="stats-grid hr-schedule-stats">
                    <x-stat-card label="Staff tracked" :value="$todaySummary['total_staff']" meta="Live staff records loaded into this mock board" />
                    <x-stat-card label="Working today" :value="$todaySummary['working']" meta="People scheduled for clinic or office coverage" />
                    <x-stat-card label="On leave today" :value="$todaySummary['leave']" meta="Easy visibility for leave and unavailable blocks" />
                    <x-stat-card label="HR owners" :value="$hrOwners->count()" meta="HR/admin users who can access this module now" />
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-body">
                <form method="GET" action="{{ route('app.hr.schedule') }}" class="form-grid">
                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">

                    <div class="col-5 field-block">
                        <label class="field-label" for="search">Search team member</label>
                        <input id="search" name="search" type="text" class="form-input" value="{{ $filters['search'] }}" placeholder="Search by name, title, or department">
                    </div>

                    <div class="col-4 field-block">
                        <label class="field-label" for="department">Department</label>
                        <select id="department" name="department" class="form-select">
                            <option value="">All departments</option>
                            @foreach ($departmentOptions as $department)
                                <option value="{{ $department }}" @selected($filters['department'] === $department)>{{ $department }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="active" @selected($filters['status'] === 'active')>Active staff</option>
                            <option value="all" @selected($filters['status'] === 'all')>All staff</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive only</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="filter-bar__head">
                            <div class="small-note">Designed as an easy weekly planning view for HR. Leave, off days, training, and working coverage are all visible at a glance.</div>
                            <div class="btn-row">
                                <button type="submit" class="btn btn-primary">Apply filters</button>
                                <a href="{{ route('app.hr.schedule') }}" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <div class="hr-schedule-layout">
            <section class="panel">
                <div class="panel-header">
                    <x-section-heading
                        kicker="Weekly roster"
                        title="Mock staff schedule board"
                        subtitle="A premium planning board mockup built from your current staff list. Final editing tools can plug into this layout later." />
                </div>
                <div class="panel-body">
                    @if ($scheduleRows->count())
                        <div class="hr-schedule-board">
                            <div class="hr-schedule-board__header hr-schedule-board__header--staff">Team member</div>
                            @foreach ($weekDays as $day)
                                <div class="hr-schedule-board__header">
                                    <div>{{ $day->format('D') }}</div>
                                    <div class="small-note">{{ $day->format('d M') }}</div>
                                </div>
                            @endforeach

                            @foreach ($scheduleRows as $row)
                                <div class="hr-schedule-board__staff">
                                    <div class="selection-card__title">{{ $row['staff']->full_name }}</div>
                                    <div class="small-note">{{ $row['staff']->job_title ?: 'No title set' }}</div>
                                    <div class="small-note">{{ $row['staff']->department ?: 'No department' }}</div>
                                    <div class="hr-schedule-board__meta">
                                        <span class="chip">{{ $row['staff']->operational_role_label }}</span>
                                        @if (! $row['staff']->is_active)
                                            <span class="chip">Inactive</span>
                                        @endif
                                    </div>
                                </div>

                                @foreach ($row['days'] as $shift)
                                    <div class="hr-schedule-cell hr-schedule-cell--{{ $shift['tone'] }}" data-day-label="{{ \Carbon\Carbon::parse($shift['date'])->format('D d M') }}">
                                        <div class="hr-schedule-cell__label">{{ $shift['label'] }}</div>
                                        <div class="hr-schedule-cell__time">{{ $shift['time'] }}</div>
                                        <div class="hr-schedule-cell__note">{{ $shift['note'] }}</div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state empty-state--dashed">
                            <div class="empty-state__title">No staff matched the current filters.</div>
                            <div class="empty-state__body">Clear the search or department filter to repopulate the mock roster board.</div>
                        </div>
                    @endif
                </div>
            </section>

            <div class="hr-schedule-side">
                <section class="panel">
                    <div class="panel-header">
                        <x-section-heading
                            kicker="Coverage snapshot"
                            title="Daily team balance"
                            subtitle="A quick check on how many people are working versus on leave across this week." />
                    </div>
                    <div class="panel-body">
                        <div class="hr-coverage-list">
                            @foreach ($coverageByDay as $coverage)
                                <div class="hr-coverage-card">
                                    <div>
                                        <div class="selection-card__title">{{ $coverage['label'] }}</div>
                                        <div class="small-note">{{ $coverage['display'] }}</div>
                                    </div>
                                    <div class="hr-coverage-card__stats">
                                        <span class="hr-mini-pill hr-mini-pill--working">{{ $coverage['working'] }} working</span>
                                        <span class="hr-mini-pill hr-mini-pill--leave">{{ $coverage['leave'] }} leave</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <x-section-heading
                            kicker="Leave visibility"
                            title="Upcoming leave blocks"
                            subtitle="Built to make leave obvious for HR before scheduling conflicts happen." />
                    </div>
                    <div class="panel-body">
                        @if ($leaveHighlights->count())
                            <div class="hr-leave-list">
                                @foreach ($leaveHighlights as $entry)
                                    <div class="hr-leave-item">
                                        <div class="selection-card__title">{{ $entry['staff']->full_name }}</div>
                                        <div class="small-note">{{ \Carbon\Carbon::parse($entry['shift']['date'])->format('D, d M') }} - {{ $entry['shift']['note'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="empty-state empty-state--dashed">
                                <div class="empty-state__title">No leave blocks in this preview week.</div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <x-section-heading
                            kicker="Module access"
                            title="HR controllers"
                            subtitle="Only admin and HR users can access this workspace." />
                    </div>
                    <div class="panel-body">
                        @if ($hrOwners->count())
                            <div class="inline-chip-row">
                                @foreach ($hrOwners as $owner)
                                    <span class="chip">{{ $owner->full_name }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="small-note">No dedicated HR staff found yet, so admin remains the fallback controller.</div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-internal-layout>
