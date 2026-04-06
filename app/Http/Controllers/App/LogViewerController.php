<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    public function laravel(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $path = storage_path('logs/laravel.log');
        $lines = [];

        if (File::exists($path)) {
            $lines = $this->tailFile($path, 200);
        }

        return view('app.logs.laravel', [
            'logPath' => $path,
            'lines' => array_reverse($lines),
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
}
