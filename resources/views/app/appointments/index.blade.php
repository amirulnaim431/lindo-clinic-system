<x-internal-layout :title="'Appointments'" :subtitle="'Schedule & Operations'">

@php
use Illuminate\Support\Str;

$filters = $filters ?? [
    'date' => now()->format('Y-m-d'),
    'service_ids' => [],
];

$selectedServiceIds = collect($filters['service_ids'] ?? [])
    ->map(fn ($id) => (string)$id)
    ->values()
    ->all();

$selectedDate = $filters['date'] ?? now()->format('Y-m-d');

$services = $services ?? collect();
$availability = $availability ?? null;
$appointmentGroups = $appointmentGroups ?? collect();

$statusColors = [
    'booked' => 'bg-amber-100 text-amber-800 border-amber-200',
    'confirmed' => 'bg-sky-100 text-sky-800 border-sky-200',
    'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
];
@endphp


{{-- SERVICE + DATE FILTER --}}
<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">

<form method="GET">

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

<div>
<label class="block text-sm font-semibold mb-2">Select Services</label>

<div class="flex flex-wrap gap-2">

@foreach ($services as $service)

@php
$selected = in_array((string)$service->id,$selectedServiceIds);
@endphp

<label
class="cursor-pointer px-4 py-2 rounded-xl border
{{ $selected ? 'bg-rose-100 border-rose-400 text-rose-700' : 'bg-white border-slate-300 hover:bg-slate-50' }}">

<input
type="checkbox"
name="service_ids[]"
value="{{ $service->id }}"
class="hidden"
{{ $selected ? 'checked' : '' }}
>

{{ $service->name }}

</label>

@endforeach

</div>
</div>


<div>

<label class="block text-sm font-semibold mb-2">Date</label>

<input
type="date"
name="date"
value="{{ $selectedDate }}"
class="border rounded-xl px-3 py-2 w-full">

</div>

</div>


<div class="mt-4">

<button
class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-black">

Check Availability

</button>

</div>

</form>

</div>


{{-- AVAILABLE SLOTS --}}
@if($availability && count($availability['slots']))

<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">

<h3 class="font-semibold mb-4">Available Slots</h3>

<div class="flex flex-wrap gap-2">

@foreach($availability['slots'] as $slot)

<button
type="button"
onclick="selectSlot('{{ $slot['time'] }}','{{ $slot['staff_combo'] }}')"
class="slot-btn px-4 py-2 rounded-xl border border-slate-300 hover:bg-rose-50">

{{ $slot['time'] }}

</button>

@endforeach

</div>

</div>

@endif



{{-- BOOKING FORM --}}
<div id="bookingForm" class="hidden bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">

<h3 class="font-semibold mb-4">Create Appointment</h3>

<form method="POST" action="{{ route('app.appointments.store') }}">

@csrf

<input type="hidden" name="slot_time" id="slot_time">
<input type="hidden" name="staff_combo" id="staff_combo">

<input type="hidden" name="date" value="{{ $selectedDate }}">

@foreach($selectedServiceIds as $sid)
<input type="hidden" name="service_ids[]" value="{{ $sid }}">
@endforeach


<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

<div>
<label class="text-sm">Customer Name</label>
<input name="customer_name" required class="w-full border rounded-xl px-3 py-2">
</div>

<div>
<label class="text-sm">Phone</label>
<input name="customer_phone" required class="w-full border rounded-xl px-3 py-2">
</div>

</div>


<div class="mt-4">

<label class="text-sm">Notes</label>
<textarea name="notes" class="w-full border rounded-xl px-3 py-2"></textarea>

</div>


<div class="mt-4">

<button class="px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">

Create Appointment

</button>

</div>

</form>

</div>



{{-- EXISTING APPOINTMENTS --}}
<div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">

<h3 class="font-semibold mb-4">Appointments</h3>

<div class="space-y-3">

@foreach($appointmentGroups as $group)

@php
$status = $group->status instanceof \BackedEnum
? $group->status->value
: (string)$group->status;

$badge = $statusColors[$status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
@endphp


<div class="border rounded-xl p-3 flex justify-between items-center">

<div>

<div class="font-semibold">

{{ $group->customer?->full_name ?? 'Customer' }}

</div>

<div class="text-sm text-slate-500">

{{ $group->starts_at }}

</div>

</div>


<span class="px-3 py-1 rounded-xl border text-sm {{ $badge }}">

{{ Str::headline($status) }}

</span>

</div>

@endforeach

</div>

</div>



<script>

function selectSlot(time,combo)
{
document.getElementById('slot_time').value = time;
document.getElementById('staff_combo').value = combo;

document.getElementById('bookingForm').classList.remove('hidden');

document.querySelectorAll('.slot-btn').forEach(b=>b.classList.remove('bg-rose-100','border-rose-400'));

event.target.classList.add('bg-rose-100','border-rose-400');
}

</script>

</x-internal-layout>