<x-internal-layout
    title="Laravel Log"
    subtitle="Temporary admin-only viewer for the latest application log entries while staging diagnostics are in progress."
>
    <div class="stack">
        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="Diagnostics"
                    title="Laravel log viewer"
                    subtitle="Newest entries appear first. Refresh the page after reproducing an issue. Each entry includes a plain-English hint for faster triage."
                />
            </div>
            <div class="panel-body stack">
                <div class="staff-access-status-card">
                    <div class="staff-access-facts">
                        <div class="staff-access-fact">
                            <span class="staff-access-fact__label">Log file</span>
                            <span class="staff-access-fact__value">{{ $logPath }}</span>
                        </div>
                        <div class="staff-access-fact">
                            <span class="staff-access-fact__label">Status</span>
                            <span class="staff-access-fact__value">{{ $fileExists ? 'Available' : 'File not found' }}</span>
                        </div>
                        <div class="staff-access-fact">
                            <span class="staff-access-fact__label">Last updated</span>
                            <span class="staff-access-fact__value">{{ $lastModified ? \Carbon\Carbon::createFromTimestamp($lastModified)->diffForHumans() : 'Not available' }}</span>
                        </div>
                    </div>
                </div>

                @if (! $fileExists)
                    <div class="alert alert-error">The Laravel log file does not exist yet.</div>
                @elseif (empty($entries))
                    <div class="alert alert-success">The Laravel log is currently empty.</div>
                @else
                    <div class="log-viewer">
                        @foreach ($entries as $entry)
                            <details class="log-entry" open>
                                <summary class="log-entry__summary">
                                    <div class="log-entry__headline">
                                        <span class="log-entry__level log-entry__level--{{ strtolower($entry['level']) }}">{{ $entry['level'] }}</span>
                                        <div class="log-entry__headline-copy">
                                            <strong>{{ $entry['summary']['title'] }}</strong>
                                            <span>{{ $entry['timestamp'] }} · {{ strtoupper($entry['environment']) }}</span>
                                        </div>
                                    </div>
                                    <span class="log-entry__owner">{{ $entry['summary']['owner'] }}</span>
                                </summary>

                                <div class="log-entry__body stack">
                                    <div class="staff-access-status-card">
                                        <div class="staff-access-facts">
                                            <div class="staff-access-fact">
                                                <span class="staff-access-fact__label">Logged message</span>
                                                <span class="staff-access-fact__value">{{ $entry['message'] }}</span>
                                            </div>
                                            <div class="staff-access-fact">
                                                <span class="staff-access-fact__label">Likely cause</span>
                                                <span class="staff-access-fact__value">{{ $entry['summary']['detail'] }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="log-viewer__line">{{ $entry['raw'] }}</div>
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-internal-layout>
