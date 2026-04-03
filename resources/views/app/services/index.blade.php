<x-internal-layout :title="'Service Catalog'" :subtitle="null">
    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <section class="panel">
            <div class="panel-body stack">
                <div class="filter-bar__head">
                    <div class="page-actions">
                        <a href="{{ route('app.services.create') }}" class="btn btn-primary">Add Service</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('app.services.index') }}" class="form-grid">
                    <div class="col-5 field-block">
                        <label class="field-label" for="search">Search</label>
                        <input id="search" name="search" type="text" class="form-input" value="{{ $filters['search'] }}" placeholder="Search service name">
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="category">Category</label>
                        <select id="category" name="category" class="form-select">
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
                                    <th>Category</th>
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
                                        <td>{{ $service->category_label }}</td>
                                        <td>{{ (int) $service->duration_minutes }} mins</td>
                                        <td>{{ $service->price !== null ? 'RM '.number_format($service->price, 0) : '-' }}</td>
                                        <td>{{ $service->is_promo ? ($service->promo_price !== null ? 'RM '.number_format($service->promo_price, 0) : 'Yes') : '-' }}</td>
                                        <td>{{ $service->is_active ? 'Active' : 'Inactive' }}</td>
                                        <td>
                                            <a href="{{ route('app.services.edit', $service) }}" class="btn btn-secondary">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="small-note" style="text-align:center;padding:1rem;">No services found.</td>
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
