<?php

namespace App\Http\Controllers;

use App\Support\TicketFile;

class TicketDownloadController extends Controller
{
    public function show(int $sell)
    {
        $path = TicketFile::path($sell);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bilhete.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
