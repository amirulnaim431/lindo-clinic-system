@props([
    'label',
    'value',
    'meta' => null,
])

<div class="stat-card">
    <div class="metric-label">{{ $label }}</div>
    <div class="stat-value">{{ $value }}</div>
    @if ($meta)
        <div class="metric-meta">{{ $meta }}</div>
    @endif
</div>
