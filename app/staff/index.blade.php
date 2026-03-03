<x-internal-layout :title="'Staff'" :subtitle="'Team & Roles'">

    @if (session('success'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <div class="text-sm text-slate-600">
            Manage staff roles so appointment creation can assign Doctor / Nurse / Beautician.
        </div>

        <a href="{{ route('app.staff.create') }}"
           class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm hover:opacity-90">
            + Add Staff
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left">
                    <th class="px-5 py-3 font-semibold">Name</th>
                    <th class="px-5 py-3 font-semibold">Role</th>
                    <th class="px-5 py-3 font-semibold">Active</th>
                    <th class="px-5 py-3 font-semibold">Services</th>
                    <th class="px-5 py-3 font-semibold text-right">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($staff as $s)
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-900">{{ $s->full_name }}</td>
                        <td class="px-5 py-3 text-slate-700">
                            {{ $roles[$s->role] ?? $s->role }}
                        </td>
                        <td class="px-5 py-3">
                            @if($s->is_active)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs border border-emerald-200 bg-emerald-50 text-emerald-800">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs border border-slate-200 bg-slate-50 text-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-700">
                            {{ $s->services()->count() ? $s->services()->pluck('name')->implode(', ') : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('app.staff.edit', $s) }}"
                               class="text-sm font-medium text-slate-900 hover:underline">
                                Edit →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-5 py-8 text-center text-slate-500" colspan="5">
                            No staff yet. Click “Add Staff”.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-slate-200">
            {{ $staff->links() }}
        </div>
    </div>

</x-internal-layout>