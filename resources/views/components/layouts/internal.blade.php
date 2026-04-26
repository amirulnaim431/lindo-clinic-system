@props([
    'title' => null,
    'subtitle' => null,
    'liveRefresh' => false,
])

@include('layouts.internal', [
    'title' => $title,
    'subtitle' => $subtitle,
    'liveRefresh' => $liveRefresh,
    'slot' => $slot,
])
