<x-internal-layout :title="$mode === 'create' ? 'Create Service' : 'Edit Service'" :subtitle="null">
    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $mode === 'create' ? route('app.services.store') : route('app.services.update', $service) }}" class="stack">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <section class="panel">
                <div class="panel-header">
                    <h3 class="panel-title-display" style="font-size:24px;">Service Details</h3>
                </div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="col-6 field-block">
                            <label class="field-label" for="name">Service name</label>
                            <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $service->name) }}" required>
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="category_key">Category</label>
                            <select id="category_key" name="category_key" class="form-select" required>
                                @foreach ($categoryOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category_key', $service->category_key) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="display_order">Display order</label>
                            <input id="display_order" name="display_order" type="number" min="0" class="form-input" value="{{ old('display_order', $service->display_order ?? 0) }}">
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="duration_minutes">Duration (mins)</label>
                            <input id="duration_minutes" name="duration_minutes" type="number" min="15" max="480" class="form-input" value="{{ old('duration_minutes', $service->duration_minutes ?? 60) }}" required>
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="price">Standard price</label>
                            <input id="price" name="price" type="number" min="0" class="form-input" value="{{ old('price', $service->price) }}">
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="promo_price">Promo price</label>
                            <input id="promo_price" name="promo_price" type="number" min="0" class="form-input" value="{{ old('promo_price', $service->promo_price) }}">
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="is_promo">Promo service</label>
                            <select id="is_promo" name="is_promo" class="form-select">
                                <option value="0" @selected(! old('is_promo', $service->is_promo))>No</option>
                                <option value="1" @selected((bool) old('is_promo', $service->is_promo))>Yes</option>
                            </select>
                        </div>

                        <div class="col-12 field-block">
                            <label class="field-label" for="description">Service note</label>
                            <textarea id="description" name="description" class="form-input booking-textarea">{{ old('description', $service->description) }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $service->is_active ?? true) ? 'checked' : '' }}>
                                <span>Active service</span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Create Service' : 'Save Changes' }}</button>
                <a href="{{ route('app.services.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</x-internal-layout>
