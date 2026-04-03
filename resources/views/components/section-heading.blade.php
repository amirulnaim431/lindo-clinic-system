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
</div>
