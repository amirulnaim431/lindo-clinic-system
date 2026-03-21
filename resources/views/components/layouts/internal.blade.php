@props([
    'title' => null,
    'subtitle' => null,
])

@include('layouts.internal', [
    'title' => $title,
    'subtitle' => $subtitle,
    'slot' => $slot,
])
