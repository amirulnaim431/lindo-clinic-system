@props([
    'kicker' => null,
    'title',
    'subtitle' => null,
])

<div>
    @if ($kicker)
        <div class="section-kicker">{{ $kicker }}</div>
    @endif
    <div class="panel-title-display">{{ $title }}</div>
    @if ($subtitle)
        <div class="panel-subtitle">{{ $subtitle }}</div>
    @endif
</div>
