<x-internal-layout :title="'Import Customers'" :subtitle="'Upload and import customer master records from Excel or CSV'">

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <div class="mb-2 font-semibold">Import failed</div>
            <ul class="ml-5 list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $summary = session('import_summary');
    @endphp

    @if ($summary)
        <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Total rows</div>
                <div class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['total_rows'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Processed</div>
                <div class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['processed'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Created</div>
                <div class="mt-2 text-2xl font-bold text-emerald-700">{{ $summary['created'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Updated</div>
                <div class="mt-2 text-2xl font-bold text-amber-700">{{ $summary['updated'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Skipped</div>
                <div class="mt-2 text-2xl font-bold text-rose-700">{{ $summary['skipped'] ?? 0 }}</div>
            </div>
        </div>

        @if (!empty($summary['issues']))
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="mb-3 text-sm font-semibold uppercase tracking-[0.14em] text-amber-800">Review notes</div>
                <div class="max-h-80 space-y-2 overflow-auto text-sm text-amber-950">
                    @foreach ($summary['issues'] as $issue)
                        <div class="rounded-xl border border-amber-200 bg-white/70 px-3 py-2">
                            {{ $issue }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-slate-900">Upload import file</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Use the cleaned master customer file. XLSX and CSV are supported.
                </p>
            </div>

            <form action="{{ route('app.customers.import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label for="import_file" class="mb-2 block text-sm font-medium text-slate-700">Import file</label>
                    <input
                        id="import_file"
                        name="import_file"
                        type="file"
                        accept=".xlsx,.csv,.txt"
                        required
                        class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800"
                    >
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Importer behavior</div>
                    <ul class="mt-3 ml-5 list-disc space-y-1 text-sm text-slate-700">
                        <li>Creates new customers when no match is found.</li>
                        <li>Updates existing customers when a match is found by legacy code, IC/passport, phone + name, phone, or name + DOB.</li>
                        <li>Does not overwrite existing values with empty cells.</li>
                        <li>Flags suspicious rows such as very short phone numbers.</li>
                    </ul>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Import customers
                    </button>

                    <span class="text-sm text-slate-500">
                        Recommended file: your cleaned import-ready Excel.
                    </span>
                </div>
            </form>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-slate-900">Expected columns</h2>
                <p class="mt-1 text-sm text-slate-600">
                    The importer is aligned to your prepared workbook structure.
                </p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Column</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Purpose</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach ([
                            ['full_name', 'Required identity anchor'],
                            ['phone', 'Primary operational contact'],
                            ['email', 'Optional contact'],
                            ['dob', 'Date of birth'],
                            ['ic_passport', 'Identity / dedupe support'],
                            ['gender', 'Patient profile'],
                            ['marital_status', 'Patient profile'],
                            ['nationality', 'Patient profile'],
                            ['occupation', 'Patient profile'],
                            ['address', 'Patient profile'],
                            ['weight', 'Clinical CRM placeholder'],
                            ['height', 'Clinical CRM placeholder'],
                            ['allergies', 'Clinical CRM placeholder'],
                            ['emergency_contact_name', 'Emergency details'],
                            ['emergency_contact_phone', 'Emergency details'],
                            ['membership_code', 'Future membership workflow'],
                            ['membership_type', 'Future membership workflow'],
                            ['current_package', 'Future package workflow'],
                            ['current_package_since', 'Future package workflow'],
                            ['notes', 'General CRM notes'],
                            ['legacy_code', 'Import traceability'],
                        ] as [$column, $purpose])
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $column }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $purpose }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</x-internal-layout>

//DOASOLUTIONS