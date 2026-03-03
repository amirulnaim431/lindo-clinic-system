<x-internal-layout :title="'Appointments'" :subtitle="'Schedule & Operations'">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-lg font-semibold">Appointments</div>
                <div class="text-sm text-slate-500">
                    Demo page is live. Next step: wire this to the real controller/table once staging routes + DB are stable.
                </div>
            </div>

            <a href="/app/dashboard"
               class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm hover:opacity-90">
                Back to Dashboard
            </a>
        </div>

        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            <div><b>URL:</b> /app/appointments</div>
            <div class="mt-1"><b>Date filter:</b> use <code>?date=YYYY-MM-DD</code></div>
        </div>
    </div>
</x-internal-layout>