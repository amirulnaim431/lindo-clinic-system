@props([
    'label',
    'value',
    'meta' => null,
])

<div class="stat-card">
    <div class="metric-label">{{ $label }}</div>
    <div class="stat-value">{{ $value }}</div>
</div>
