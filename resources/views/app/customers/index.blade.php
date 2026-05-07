<x-internal-layout :title="'Customer CRM'" :subtitle="'Search and review imported customer records, membership details, and patient profile information.'">
    <div class="stack">
        <section class="hero-panel">
            <div class="panel-body stack">
                <div class="filter-bar__head">
                    <x-section-heading
                        kicker="Customer directory"
                        title="Customers"
                        subtitle="Search by full name, phone, IC or passport, or membership code." />

                    <form method="GET" action="{{ route('app.customers.index') }}" class="split-grid" style="width:min(100%, 44rem);">
                        <div class="field-block">
                            <label for="search" class="field-label">Search</label>
                            <input
                                id="search"
                                name="search"
                                type="text"
                                value="{{ $search }}"
                                placeholder="e.g. Nur Aina, 0123456789, MBR-001"
                                class="form-input"
                            >
                        </div>

                        <div class="field-block" style="align-self:end;">
                            <div class="btn-row btn-row--end">
                                <button type="submit" class="btn btn-primary">Search</button>
                                @if($search !== '')
                                    <a href="{{ route('app.customers.index') }}" class="btn btn-secondary">Reset</a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="three-col">
            <x-stat-card label="Total records" :value="number_format($customers->total())" meta="Imported customer records available in CRM." />
            <x-stat-card label="Current page" :value="$customers->currentPage()" :meta="'Showing '.$customers->count().' records on this page.'" />
            <x-stat-card label="Search state" :value="$search !== '' ? 'Filtered' : 'All records'" :meta="$search !== '' ? 'Results narrowed by your keyword.' : 'No keyword applied yet.'" />
        </section>

        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="Membership report"
                    title="Membership counts"
                    subtitle="Click a membership tier to review and print the full customer list." />
            </div>
            <div class="panel-body">
                <div class="membership-report-grid">
                    @foreach (($membershipReport['groups'] ?? []) as $group)
                        <button
                            type="button"
                            class="membership-report-card membership-report-card--{{ $group['key'] }}"
                            data-membership-report='@json($group)'
                        >
                            <span class="membership-report-card__label">{{ $group['label'] }}</span>
                            <span class="membership-report-card__value">{{ number_format($group['count']) }}</span>
                            <span class="membership-report-card__meta">customers</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="Records"
                    title="Customer profiles"
                    subtitle="Open a profile to review clinic, membership, and appointment information." />
            </div>

            <div class="panel-body">
                <div class="table-shell">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Full name</th>
                                    <th>Phone</th>
                                    <th>IC / Passport</th>
                                    <th>Gender</th>
                                    <th>Membership type</th>
                                    <th>Current package</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customers as $customer)
                                    <tr>
                                        <td>
                                            <div class="selection-card__title">{{ $customer->full_name ?: '-' }}</div>
                                            @if($customer->membership_code)
                                                <div class="small-note">Membership code: {{ $customer->membership_code }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $customer->phone ?: '-' }}</td>
                                        <td>{{ $customer->ic_passport ?: '-' }}</td>
                                        <td>{{ $customer->gender ?: '-' }}</td>
                                        <td>{{ $customer->membership_type ?: '-' }}</td>
                                        <td>
                                            @if($customer->current_package)
                                                <div class="selection-card__title">{{ $customer->current_package }}</div>
                                                @if($customer->current_package_since)
                                                    <div class="small-note">Since {{ \Illuminate\Support\Carbon::parse($customer->current_package_since)->format('d M Y') }}</div>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('app.customers.show', $customer) }}" class="btn btn-secondary">View profile</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state empty-state--dashed">
                                                <div class="empty-state__title">No customers found</div>
                                                <div class="empty-state__body">Try another keyword using full name, phone number, IC or passport, or membership code.</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @if($customers->hasPages())
            <div class="panel">
                <div class="panel-body">
                    {{ $customers->links() }}
                </div>
            </div>
        @endif
    </div>

    <div id="membership-report-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card membership-report-modal">
                <div class="modal-header">
                    <div class="modal-header__row">
                        <div>
                            <div class="modal-kicker">Membership list</div>
                            <h3 id="membership-report-title" class="modal-title">-</h3>
                            <p id="membership-report-summary" class="modal-subtitle">-</p>
                        </div>
                        <button type="button" id="membership-report-close-top" class="modal-close" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="modal-body stack">
                    <div class="btn-row btn-row--end">
                        <button type="button" id="membership-report-print" class="btn btn-primary">Print list</button>
                    </div>

                    <div class="table-shell">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Code</th>
                                        <th>Package</th>
                                        <th>Since</th>
                                        <th>Value</th>
                                        <th>Balance</th>
                                        <th class="text-right">Profile</th>
                                    </tr>
                                </thead>
                                <tbody id="membership-report-rows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" id="membership-report-close-bottom" class="modal-btn modal-btn--secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .membership-report-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .membership-report-card {
            border: 1px solid rgba(198, 124, 154, 0.2);
            border-radius: 24px;
            background:
                radial-gradient(circle at top left, rgba(198, 124, 154, 0.14), transparent 42%),
                #fff;
            color: #36242d;
            cursor: pointer;
            padding: 1rem;
            text-align: left;
            transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
        }

        .membership-report-card:hover {
            border-color: rgba(198, 124, 154, 0.45);
            box-shadow: 0 18px 40px rgba(72, 43, 55, 0.08);
            transform: translateY(-2px);
        }

        .membership-report-card__label,
        .membership-report-card__meta {
            display: block;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .membership-report-card__value {
            display: block;
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            line-height: 1;
            margin: 0.55rem 0 0.25rem;
        }

        .membership-report-card__meta {
            color: #9a7987;
            font-size: 0.7rem;
        }

        .membership-report-card--bronze {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.18), rgba(255, 255, 255, 0.94));
        }

        .membership-report-card--silver {
            background: linear-gradient(135deg, rgba(176, 186, 196, 0.24), rgba(255, 255, 255, 0.94));
        }

        .membership-report-card--gold {
            background: linear-gradient(135deg, rgba(218, 165, 32, 0.24), rgba(255, 255, 255, 0.94));
        }

        .membership-report-card--platinum {
            background: linear-gradient(135deg, rgba(188, 205, 214, 0.32), rgba(255, 255, 255, 0.94));
        }

        .membership-report-card--black {
            background: linear-gradient(135deg, rgba(40, 32, 37, 0.18), rgba(255, 255, 255, 0.94));
        }

        .membership-report-modal {
            width: min(1180px, calc(100vw - 2rem));
        }

        @media (max-width: 1100px) {
            .membership-report-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .membership-report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        (() => {
            const modal = document.getElementById('membership-report-modal');
            const title = document.getElementById('membership-report-title');
            const summary = document.getElementById('membership-report-summary');
            const rows = document.getElementById('membership-report-rows');
            const printButton = document.getElementById('membership-report-print');
            const closeTop = document.getElementById('membership-report-close-top');
            const closeBottom = document.getElementById('membership-report-close-bottom');
            let activeReport = null;

            const generatedAt = @json($membershipReport['generated_at'] ?? now()->format('d M Y, h:i A'));

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const closeModal = () => {
                modal?.classList.add('hidden');
                modal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            const renderRows = (customers) => {
                if (!customers.length) {
                    rows.innerHTML = '<tr><td colspan="8" class="small-note" style="text-align:center;padding:1rem;">No customers recorded in this membership tier yet.</td></tr>';
                    return;
                }

                rows.innerHTML = customers.map((customer) => `
                    <tr>
                        <td>${escapeHtml(customer.name)}</td>
                        <td>${escapeHtml(customer.phone)}</td>
                        <td>${escapeHtml(customer.membership_code)}</td>
                        <td>${escapeHtml(customer.current_package)}</td>
                        <td>${escapeHtml(customer.package_since)}</td>
                        <td>${escapeHtml(customer.package_value)}</td>
                        <td>${escapeHtml(customer.balance)}</td>
                        <td class="text-right"><a href="${escapeHtml(customer.profile_url)}" class="btn btn-secondary">Open</a></td>
                    </tr>
                `).join('');
            };

            const openReport = (report) => {
                activeReport = report;
                const customers = Array.isArray(report.customers) ? report.customers : [];

                title.textContent = `${report.label || 'Membership'} customers`;
                summary.textContent = `${customers.length} customer${customers.length === 1 ? '' : 's'} | Generated ${generatedAt}`;
                renderRows(customers);

                modal?.classList.remove('hidden');
                modal?.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const printReport = () => {
                if (!activeReport) {
                    return;
                }

                const customers = Array.isArray(activeReport.customers) ? activeReport.customers : [];
                const printWindow = window.open('', '_blank', 'width=1100,height=760');

                if (!printWindow) {
                    return;
                }

                const tableRows = customers.length
                    ? customers.map((customer, index) => `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${escapeHtml(customer.name)}</td>
                            <td>${escapeHtml(customer.phone)}</td>
                            <td>${escapeHtml(customer.ic_passport)}</td>
                            <td>${escapeHtml(customer.membership_code)}</td>
                            <td>${escapeHtml(customer.current_package)}</td>
                            <td>${escapeHtml(customer.package_since)}</td>
                            <td>${escapeHtml(customer.package_value)}</td>
                            <td>${escapeHtml(customer.balance)}</td>
                        </tr>
                    `).join('')
                    : '<tr><td colspan="9" style="text-align:center;">No customers recorded in this membership tier yet.</td></tr>';

                printWindow.document.write(`
                    <html>
                        <head>
                            <title>${escapeHtml(activeReport.label)} Membership Customers</title>
                            <style>
                                @page { size: A4 landscape; margin: 10mm; }
                                * { box-sizing: border-box; }
                                body {
                                    color: #241820;
                                    font-family: "Inter", "Segoe UI", Arial, sans-serif;
                                    font-size: 11px;
                                    margin: 0;
                                }
                                h1 {
                                    font-family: "Plus Jakarta Sans", "Segoe UI", Arial, sans-serif;
                                    font-size: 22px;
                                    margin: 0 0 4px;
                                }
                                .meta {
                                    color: #67535d;
                                    margin: 0 0 12px;
                                }
                                table {
                                    border-collapse: collapse;
                                    table-layout: fixed;
                                    width: 100%;
                                }
                                th, td {
                                    border: 1px solid #d7c6ce;
                                    padding: 6px 7px;
                                    text-align: left;
                                    vertical-align: top;
                                    word-break: break-word;
                                }
                                th {
                                    background: #2f2028;
                                    color: #fff;
                                    font-size: 9px;
                                    letter-spacing: 0.08em;
                                    text-transform: uppercase;
                                }
                                tr:nth-child(even) td {
                                    background: #fbf4f7;
                                }
                                th:nth-child(1), td:nth-child(1) { width: 34px; text-align: center; }
                                th:nth-child(2), td:nth-child(2) { width: 23%; }
                                th:nth-child(3), td:nth-child(3) { width: 12%; }
                                th:nth-child(4), td:nth-child(4) { width: 12%; }
                                th:nth-child(5), td:nth-child(5) { width: 12%; }
                                th:nth-child(6), td:nth-child(6) { width: 12%; }
                                th:nth-child(7), td:nth-child(7) { width: 10%; }
                                th:nth-child(8), td:nth-child(8) { width: 9%; }
                                th:nth-child(9), td:nth-child(9) { width: 9%; }
                            </style>
                        </head>
                        <body>
                            <h1>${escapeHtml(activeReport.label)} Membership Customers</h1>
                            <p class="meta">${customers.length} customer${customers.length === 1 ? '' : 's'} | Generated ${escapeHtml(generatedAt)}</p>
                            <table>
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>IC / Passport</th>
                                        <th>Code</th>
                                        <th>Package</th>
                                        <th>Since</th>
                                        <th>Value</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>${tableRows}</tbody>
                            </table>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
            };

            document.querySelectorAll('[data-membership-report]').forEach((button) => {
                button.addEventListener('click', () => {
                    try {
                        openReport(JSON.parse(button.dataset.membershipReport || '{}'));
                    } catch (error) {
                        openReport({ label: 'Membership', customers: [] });
                    }
                });
            });

            printButton?.addEventListener('click', printReport);
            closeTop?.addEventListener('click', closeModal);
            closeBottom?.addEventListener('click', closeModal);

            modal?.addEventListener('click', (event) => {
                if (event.target === modal || event.target === modal.firstElementChild) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        })();
    </script>
</x-internal-layout>
