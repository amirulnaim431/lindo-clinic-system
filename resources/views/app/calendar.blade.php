<x-internal-layout :title="'Calendar'" :subtitle="'Schedule View'">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <div class="text-lg font-semibold">Calendar</div>
        <div class="text-sm text-slate-500 mt-1">
            Demo placeholder. Next step: connect this page to the real calendar module.
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
        </div>
    </div>
</x-internal-layout>