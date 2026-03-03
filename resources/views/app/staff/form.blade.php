<x-internal-layout :title="($mode === 'create' ? 'Add Staff' : 'Edit Staff')" :subtitle="'Create or update staff details'">

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900 text-sm">
            <div class="font-semibold mb-1">Fix the following:</div>
            <ul class="list-disc ml-5">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200">
            <div class="text-sm font-semibold">
                {{ $mode === 'create' ? 'New staff' : 'Update staff' }}
            </div>
            <div class="text-xs text-slate-500 mt-1">
                Roles should match your appointment engine (doctor / nurse / beautician).
            </div>
        </div>

        <form class="p-5 space-y-4"
              method="POST"
              action="{{ $mode === 'create' ? route('app.staff.store') : route('app.staff.update', $staff) }}">
            @csrf
            @if($mode === 'edit')
                @method('PUT')
            @endif

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Full name</label>
                <input type="text" name="full_name"
                       value="{{ old('full_name', $staff->full_name) }}"
                       class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Role</label>
                <select name="role"
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm">
                    @foreach($roles as $key => $label)
                        <option value="{{ $key }}" @selected(old('role', $staff->role) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       class="rounded border-slate-300"
                       @checked(old('is_active', $staff->is_active) ? true : false)>
                <label for="is_active" class="text-sm text-slate-700">Active staff</label>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('app.staff.index') }}" class="text-sm text-slate-600 hover:underline">
                    ← Back
                </a>

                <button class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm hover:opacity-90">
                    {{ $mode === 'create' ? 'Create staff' : 'Save changes' }}
                </button>
            </div>
        </form>
    </div>

</x-internal-layout>