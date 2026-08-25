<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use App\Services\Research\ThreadExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThreadExportController extends Controller
{
    /**
     * Download the thread as a report, a data dump, or a bibliography.
     */
    public function __invoke(Thread $thread, string $format, ThreadExporter $exporter): StreamedResponse
    {
        abort_unless(in_array($format, ThreadExporter::FORMATS, true), 404);

        $export = $exporter->export($thread, $format);

        return response()->streamDownload(
            fn () => print $export['body'],
            $export['filename'],
            ['Content-Type' => $export['mime']],
        );
    }
}
