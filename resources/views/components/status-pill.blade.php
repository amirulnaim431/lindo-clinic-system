@props([
    'label',
    'tone' => 'neutral',
    'dot' => null,
])

<span {{ $attributes->class(['status-chip', 'status-chip--'.$tone]) }}>
    @if ($dot)
        <span class="status-dot" style="background: {{ $dot }};"></span>
    @endif
    <span>{{ $label }}</span>
</span>
