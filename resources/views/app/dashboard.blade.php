@extends('layouts.internal')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-xl font-semibold">Dashboard</h2>
        <p class="text-sm text-slate-500 mt-1">
            Clinic overview and quick access to operations.
        </p>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <a href="{{ route('app.calendar') }}"
           class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-white text-sm">
            Open Calendar
        </a>
    </div>
</div>
@endsection