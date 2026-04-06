<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LogViewerController extends Controller
{
    public function laravel(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $path = storage_path('logs/laravel.log');
        $entries = [];

        if (File::exists($path)) {
            $entries = $this->parseEntries($this->tailFile($path, 300));
        }

        return view('app.logs.laravel', [
            'logPath' => $path,
            'entries' => array_reverse($entries),
            'fileExists' => File::exists($path),
            'lastModified' => File::exists($path) ? File::lastModified($path) : null,
        ]);
    }

    /**
     * Read the last N lines from a text file without loading the whole file into memory.
     */
    protected function tailFile(string $path, int $lineLimit = 200): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $chunkSize = 4096;
        $lineCount = 0;

        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);

        while ($position > 0 && $lineCount <= $lineLimit) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;

            fseek($handle, $position);
            $buffer = fread($handle, $readSize).$buffer;
            $lineCount = substr_count($buffer, PHP_EOL);
        }

        fclose($handle);

        return array_slice(preg_split('/\r\n|\r|\n/', trim($buffer)) ?: [], -$lineLimit);
    }

    /**
     * Group a tailed Laravel log into top-level entries so stack traces stay attached to the triggering error.
     *
     * @param  array<int, string>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function parseEntries(array $lines): array
    {
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(?<timestamp>[^\]]+)\]\s+(?<environment>[a-zA-Z0-9_-]+)\.(?<level>[A-Z]+):\s*(?<message>.*)$/', $line, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'timestamp' => $matches['timestamp'],
                    'environment' => Str::lower($matches['environment']),
                    'level' => Str::upper($matches['level']),
                    'message' => trim($matches['message']),
                    'lines' => [$line],
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            $current['lines'][] = $line;
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return array_map(function (array $entry): array {
            $raw = implode(PHP_EOL, $entry['lines']);

            return $entry + [
                'raw' => $raw,
                'summary' => $this->summarizeEntry($entry['message'], $raw),
            ];
        }, $entries);
    }

    /**
     * Turn common production failures into plain-English hints so staging issues are triaged faster.
     */
    protected function summarizeEntry(string $message, string $raw): array
    {
        $normalized = Str::lower($message.PHP_EOL.$raw);

        if (Str::contains($normalized, 'peer certificate cn=') && Str::contains($normalized, 'did not match expected cn=')) {
            return [
                'title' => 'Mail host certificate mismatch',
                'detail' => 'Likely hosting mail configuration issue. The SMTP server is presenting a certificate for a different hostname, so Laravel refuses the secure connection.',
                'owner' => 'Webhost or SMTP hostname setting',
            ];
        }

        if (Str::contains($normalized, 'the "ssl" scheme is not supported')) {
            return [
                'title' => 'Legacy mail scheme mismatch',
                'detail' => 'Laravel reached the mailer, but the configured scheme did not match what Symfony expects. This is an application mail configuration issue.',
                'owner' => 'Laravel mail configuration',
            ];
        }

        if (Str::contains($normalized, 'access denied for user') && Str::contains($normalized, 'sqlstate')) {
            return [
                'title' => 'Database credentials rejected',
                'detail' => 'Laravel could not log into the database with the configured username or password, so the request failed before app logic finished.',
                'owner' => 'Database credentials or database grants',
            ];
        }

        if (Str::contains($normalized, 'open_basedir')) {
            return [
                'title' => 'PHP open_basedir restriction',
                'detail' => 'This looks like a hosting-level PHP restriction. The server blocked Laravel or PHP from reading or writing outside the directories allowed by the webhost.',
                'owner' => 'Webhost PHP restrictions',
            ];
        }

        if (Str::contains($normalized, 'permission denied') || Str::contains($normalized, 'failed to open stream')) {
            return [
                'title' => 'Filesystem permission problem',
                'detail' => 'Laravel tried to read or write a file the site account cannot access. On Plesk this is usually a writable-folder or file ownership issue.',
                'owner' => 'Webhost file permissions',
            ];
        }

        if (Str::contains($normalized, 'could not resolve') && Str::contains($normalized, 'vite.config.js')) {
            return [
                'title' => 'Node build blocked by hosting path permissions',
                'detail' => 'The server-side Vite build could not traverse the Windows hosting path to load the Vite config. The frontend build is failing because of hosting permissions, not app code.',
                'owner' => 'Webhost Node.js permissions',
            ];
        }

        if (Str::contains($normalized, 'connection could not be established with host')) {
            return [
                'title' => 'SMTP connection failed',
                'detail' => 'Laravel reached the outbound mail step, but the server connection failed before the email could be accepted. Check hostname, port, TLS mode, and mailbox credentials.',
                'owner' => 'SMTP server settings',
            ];
        }

        if (Str::contains($normalized, 'sqlstate')) {
            return [
                'title' => 'Database query failure',
                'detail' => 'Laravel hit a database error while handling the request. The exact SQLSTATE code in the raw log will point to the schema or data mismatch.',
                'owner' => 'Application database layer',
            ];
        }

        return [
            'title' => 'Application error needs review',
            'detail' => 'Laravel logged an exception, but it does not match one of the common staging patterns yet. Read the raw entry below for the exact stack trace and message.',
            'owner' => 'Needs manual review',
        ];
    }
}
