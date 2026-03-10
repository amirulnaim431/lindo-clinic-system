<style>
    .appointment-service-card {
        border: 1px solid #e2e8f0;
        background: #ffffff;
        border-radius: 1rem;
        padding: 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        transition: all 0.18s ease;
    }

    .appointment-service-card:hover {
        border-color: #e7b7b0;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }

    .appointment-service-card.is-selected {
        border-color: #d6a39a;
        background: linear-gradient(180deg, #fff8f6 0%, #fff1ee 100%);
        box-shadow: 0 0 0 3px rgba(214, 163, 154, 0.20);
    }

    .appointment-service-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        border: 1px solid #e2e8f0;
        padding: 0.25rem 0.625rem;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        background: #ffffff;
    }

    .appointment-service-card.is-selected .appointment-service-badge {
        border-color: #d6a39a;
        color: #9a5c52;
        background: #ffffff;
    }

    .appointment-service-card.is-selected .appointment-service-title {
        color: #7c3f35;
    }

    .appointment-service-card.is-selected .appointment-service-muted {
        color: #9a5c52;
    }
</style>

<div class="grid grid-cols-1 gap-3 md:grid-cols-2">
    @foreach ($services as $service)
        @php
            $isSelected = in_array((string) $service->id, $selectedServiceIds, true);
        @endphp

        <label class="block cursor-pointer">
            <input
                type="checkbox"
                name="service_ids[]"
                value="{{ $service->id }}"
                class="appointment-service-checkbox sr-only"
                {{ $isSelected ? 'checked' : '' }}
            >

            <div class="appointment-service-card {{ $isSelected ? 'is-selected' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="appointment-service-title text-sm font-semibold text-slate-900">
                            {{ $service->name }}
                        </div>
                    </div>

                    <div class="appointment-service-badge">
                        Service
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <div class="appointment-service-muted text-xs text-slate-500">
                        Click to include in availability check
                    </div>

                    <div class="appointment-service-badge appointment-service-status">
                        {{ $isSelected ? 'Selected' : 'Available' }}
                    </div>
                </div>
            </div>
        </label>
    @endforeach
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.appointment-service-checkbox');

        const refreshCardState = (checkbox) => {
            const card = checkbox.closest('label')?.querySelector('.appointment-service-card');
            if (!card) return;

            card.classList.toggle('is-selected', checkbox.checked);

            const status = card.querySelector('.appointment-service-status');
            if (status) {
                status.textContent = checkbox.checked ? 'Selected' : 'Available';
            }
        };

        checkboxes.forEach((checkbox) => {
            refreshCardState(checkbox);

            checkbox.addEventListener('change', function () {
                refreshCardState(this);
            });
        });
    });
</script>