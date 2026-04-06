<x-internal-layout
    title="Laravel Log"
    subtitle="Temporary admin-only viewer for the latest application log lines while staging diagnostics are in progress."
>
    <div class="stack">
        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="Diagnostics"
                    title="Laravel log viewer"
                    subtitle="Newest entries appear first. Refresh the page after reproducing an issue."
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
                @elseif (empty($lines))
                    <div class="alert alert-success">The Laravel log is currently empty.</div>
                @else
                    <div class="log-viewer">
                        @foreach ($lines as $line)
                            <div class="log-viewer__line">{{ $line }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-internal-layout>
