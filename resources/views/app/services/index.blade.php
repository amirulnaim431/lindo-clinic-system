<x-internal-layout :title="'Service Catalog'" :subtitle="null">
    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @if (! ($catalogReady ?? false))
            <section class="panel">
                <div class="panel-body stack">
                    <div class="alert alert-error">
                        Service setup is almost ready. Run the latest migration first, then this page will unlock service categories, promo services, and manager editing.
                    </div>
                </div>
            </section>
        @endif

        <section class="panel">
            <div class="panel-body stack">
                <div class="filter-bar__head">
                    <div class="page-actions">
                        @if ($catalogReady ?? false)
                            <a href="{{ route('app.services.create') }}" class="btn btn-primary">Add Service</a>
                        @else
                            <button type="button" class="btn btn-primary" disabled>Add Service</button>
                        @endif
                    </div>
                </div>

                <form method="GET" action="{{ route('app.services.index') }}" class="form-grid">
                    <div class="col-5 field-block">
                        <label class="field-label" for="search">Search</label>
                        <input id="search" name="search" type="text" class="form-input" value="{{ $filters['search'] }}" placeholder="Search service name">
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="category">Category</label>
                        <select id="category" name="category" class="form-select" @disabled(! ($catalogReady ?? false))>
                            <option value="">All categories</option>
                            @foreach ($categoryOptions as $key => $label)
                                <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-2 field-block">
                        <label class="field-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="all" @selected($filters['status'] === 'all')>All</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>

                    <div class="col-2 field-block" style="align-self:end;">
                        <div class="btn-row btn-row--end btn-row--compact-mobile">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="{{ route('app.services.index') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h3 class="panel-title-display" style="font-size:24px;">Services</h3>
            </div>
            <div class="panel-body">
                <div class="table-shell">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Category Path</th>
                                    <th>Default role</th>
                                    <th>Assigned staff</th>
                                    <th>Options</th>
                                    <th>Duration</th>
                                    <th>Price</th>
                                    <th>Promo</th>
                                    <th>Status</th>
                                    <th style="width: 140px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($services as $service)
                                    <tr>
                                        <td>
                                            <div>{{ $service->name }}</div>
                                            @if ($service->description)
                                                <div class="small-note">{{ $service->description }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $service->displayCategoryPath() }}</div>
                                            @if ($service->category_key === 'consultations' && $service->consultation_category_label)
                                                <div class="small-note">Consultation department</div>
                                            @endif
                                        </td>
                                        <td>{{ $roleOptions[$service->default_staff_role] ?? '-' }}</td>
                                        <td>{{ $service->staff->pluck('full_name')->implode(', ') ?: '-' }}</td>
                                        <td>{{ $service->optionGroups->pluck('name')->implode(', ') ?: '-' }}</td>
                                        <td>{{ (int) $service->duration_minutes }} mins</td>
                                        <td>{{ $service->price !== null ? 'RM '.number_format($service->price, 0) : '-' }}</td>
                                        <td>{{ $service->is_promo ? ($service->promo_price !== null ? 'RM '.number_format($service->promo_price, 0) : 'Yes') : '-' }}</td>
                                        <td>{{ $service->is_active ? 'Active' : 'Inactive' }}</td>
                                        <td style="white-space:nowrap;">
                                            <a href="{{ route('app.services.edit', $service) }}" class="btn btn-secondary">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="small-note" style="text-align:center;padding:1rem;">No services found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if (method_exists($services, 'links'))
                    <div class="pagination-wrap">{{ $services->links() }}</div>
                @endif
            </div>
        </section>
    </div>
</x-internal-layout>
