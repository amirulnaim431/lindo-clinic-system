<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;
use SimpleXMLElement;

class CustomerImportController extends Controller
{
    public function index(): View
    {
        return view('app.customers.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
        ]);

        $file = $request->file('import_file');

        try {
            $rows = match (strtolower($file->getClientOriginalExtension())) {
                'csv', 'txt' => $this->readCsv($file->getRealPath()),
                'xlsx'       => $this->readXlsx($file->getRealPath()),
                default      => collect(),
            };
        } catch (\Throwable $e) {
            return back()->withErrors([
                'import_file' => 'Import failed while reading the file: ' . $e->getMessage(),
            ]);
        }

        if ($rows->isEmpty()) {
            return back()->withErrors([
                'import_file' => 'No readable rows were found in the uploaded file.',
            ]);
        }

        $summary = [
            'total_rows' => $rows->count(),
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'issues' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $lineNumber = $index + 2;

                $normalized = $this->normalizeRow($row);

                if ($this->isCompletelyEmpty($normalized)) {
                    $summary['skipped']++;
                    $summary['issues'][] = "Row {$lineNumber}: skipped because the row is empty.";
                    continue;
                }

                if (blank($normalized['full_name'])) {
                    $summary['skipped']++;
                    $summary['issues'][] = "Row {$lineNumber}: skipped because full_name is empty.";
                    continue;
                }

                $customer = $this->findExistingCustomer($normalized);

                if (! $customer) {
                    $customer = new Customer();

                    // Defensive ULID assignment for staging/runtime consistency.
                    if (blank($customer->id)) {
                        $customer->id = (string) Str::ulid();
                    }

                    $summary['created']++;
                } else {
                    $summary['updated']++;
                }

                $payload = $this->buildPayload($normalized, $customer);

                $customer->fill($payload);
                $customer->save();

                $summary['processed']++;

                $rowIssues = $this->detectRowIssues($normalized, $lineNumber);
                foreach ($rowIssues as $issue) {
                    $summary['issues'][] = $issue;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors([
                'import_file' => 'Import failed while saving rows: ' . $e->getMessage(),
            ]);
        }

        $summary['issues'] = array_slice($summary['issues'], 0, 50);

        return redirect()
            ->route('app.customers.import.index')
            ->with('success', 'Customer import completed.')
            ->with('import_summary', $summary);
    }

    private function readCsv(string $path): Collection
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $data);
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $data[$i] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return collect($rows);
    }

    private function readXlsx(string $path): Collection
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to open XLSX archive.');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStringsXml !== false) {
            $xml = new SimpleXMLElement($sharedStringsXml);

            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                    continue;
                }

                $text = '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new \RuntimeException('The importer expected the first worksheet at xl/worksheets/sheet1.xml.');
        }

        $sheet = new SimpleXMLElement($sheetXml);
        $rows = [];
        $headerMap = [];

        foreach ($sheet->sheetData->row as $rowNode) {
            $cells = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                $columnLetters = preg_replace('/\d+/', '', $ref);
                $columnIndex = $this->columnLettersToIndex($columnLetters);

                $type = (string) $cell['t'];
                $value = '';

                if ($type === 's') {
                    $sharedIndex = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }

                $cells[$columnIndex] = $value;
            }

            if (empty($headerMap)) {
                ksort($cells);

                foreach ($cells as $index => $headerValue) {
                    $header = $this->normalizeHeader((string) $headerValue);
                    if ($header !== '') {
                        $headerMap[$index] = $header;
                    }
                }

                continue;
            }

            $row = [];
            foreach ($headerMap as $index => $header) {
                $row[$header] = $cells[$index] ?? null;
            }

            $rows[] = $row;
        }

        $zip->close();

        return collect($rows);
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);

        return match ($value) {
            'Patient' => 'full_name',
            'Code' => 'legacy_code',
            'DOB' => 'dob',
            'Contact Number' => 'phone',
            'IC/Passport' => 'ic_passport',
            'Sex' => 'gender',
            'Marital Status' => 'marital_status',
            'Nationality' => 'nationality',
            'Occupation' => 'occupation',
            'Address' => 'address',
            default => Str::of($value)
                ->lower()
                ->replace([' ', '-', '/'], '_')
                ->replace('__', '_')
                ->trim('_')
                ->value(),
        };
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$key] = is_string($value)
                ? $this->normalizeString($value)
                : $value;
        }

        $normalized['full_name'] = $this->normalizeName($normalized['full_name'] ?? null);
        $normalized['phone'] = $this->normalizePhone($normalized['phone'] ?? null);
        $normalized['ic_passport'] = $this->normalizeIcPassport($normalized['ic_passport'] ?? null);
        $normalized['gender'] = $this->normalizeGender($normalized['gender'] ?? null);
        $normalized['dob'] = $this->normalizeDate($normalized['dob'] ?? null);
        $normalized['current_package_since'] = $this->normalizeDate($normalized['current_package_since'] ?? null);
        $normalized['weight'] = $this->normalizeDecimal($normalized['weight'] ?? null);
        $normalized['height'] = $this->normalizeDecimal($normalized['height'] ?? null);

        return $normalized;
    }

    private function findExistingCustomer(array $row): ?Customer
    {
        if (filled($row['legacy_code'] ?? null)) {
            $customer = Customer::where('legacy_code', $row['legacy_code'])->first();
            if ($customer) {
                return $customer;
            }
        }

        if (filled($row['ic_passport'] ?? null)) {
            $customer = Customer::where('ic_passport', $row['ic_passport'])->first();
            if ($customer) {
                return $customer;
            }
        }

        if (filled($row['phone'] ?? null) && filled($row['full_name'] ?? null)) {
            $customer = Customer::where('phone', $row['phone'])
                ->where('full_name', $row['full_name'])
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        if (filled($row['phone'] ?? null)) {
            $customer = Customer::where('phone', $row['phone'])->first();
            if ($customer) {
                return $customer;
            }
        }

        if (filled($row['full_name'] ?? null) && filled($row['dob'] ?? null)) {
            $customer = Customer::where('full_name', $row['full_name'])
                ->whereDate('dob', $row['dob'])
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        return null;
    }

    private function buildPayload(array $row, Customer $customer): array
    {
        $fields = [
            'full_name',
            'phone',
            'email',
            'dob',
            'ic_passport',
            'gender',
            'marital_status',
            'nationality',
            'occupation',
            'address',
            'weight',
            'height',
            'allergies',
            'emergency_contact_name',
            'emergency_contact_phone',
            'membership_code',
            'membership_type',
            'current_package',
            'current_package_since',
            'notes',
            'legacy_code',
        ];

        $payload = [];

        foreach ($fields as $field) {
            $incoming = $row[$field] ?? null;
            $existing = $customer->{$field} ?? null;

            if ($this->hasMeaningfulValue($incoming)) {
                $payload[$field] = $incoming;
                continue;
            }

            if (! $customer->exists && in_array($field, ['full_name', 'phone', 'email', 'dob'], true)) {
                $payload[$field] = $incoming;
                continue;
            }

            $payload[$field] = $existing;
        }

        return $payload;
    }

    private function detectRowIssues(array $row, int $lineNumber): array
    {
        $issues = [];

        $phone = $row['phone'] ?? null;
        if (filled($phone) && strlen(preg_replace('/\D+/', '', $phone)) < 8) {
            $issues[] = "Row {$lineNumber}: phone looks suspicious ({$phone}).";
        }

        if (blank($row['phone'] ?? null) && blank($row['ic_passport'] ?? null)) {
            $issues[] = "Row {$lineNumber}: neither phone nor ic_passport is present.";
        }

        return $issues;
    }

    private function isCompletelyEmpty(array $row): bool
    {
        $keys = [
            'full_name',
            'phone',
            'email',
            'dob',
            'ic_passport',
            'gender',
            'marital_status',
            'nationality',
            'occupation',
            'address',
            'legacy_code',
        ];

        foreach ($keys as $key) {
            if ($this->hasMeaningfulValue($row[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strtolower($value) === 'null' || $value === '-') {
            return null;
        }

        return $value;
    }

    private function normalizeName(?string $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value);
    }

    private function normalizePhone(?string $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[^\d+]/', '', $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeIcPassport(?string $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        return strtoupper($value);
    }

    private function normalizeGender(?string $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        $lower = strtolower($value);

        return match ($lower) {
            'male', 'm', 'lelaki' => 'Male',
            'female', 'f', 'perempuan' => 'Female',
            default => $value,
        };
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }

        $value = $this->normalizeString((string) $value);

        if ($value === null) {
            return null;
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable $e) {
                // continue
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^\d.]/', '', (string) $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index;
    }
}