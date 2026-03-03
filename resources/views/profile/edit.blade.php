<x-internal-layout :title="'Profile'" :subtitle="'Account Settings'">
    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <div class="text-lg font-semibold">Profile</div>
        <div class="text-sm text-slate-500 mt-1">
            Demo placeholder page to prevent layout crashes (route: <code>profile.edit</code>).
        </div>

        <div class="mt-6 space-y-2 text-sm">
            <div><b>Email:</b> {{ auth()->user()->email ?? '—' }}</div>
            <div><b>Role:</b> {{ auth()->user()->role ?? '—' }}</div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <a href="/app/dashboard"
               class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm hover:opacity-90">
                Back to Dashboard
            </a>

            <a href="/app/appointments"
               class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50">
                Appointments
            </a>

            <a href="/app/calendar"
               class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50">
                Calendar
            </a>
        </div>
    </div>
</x-internal-layout>