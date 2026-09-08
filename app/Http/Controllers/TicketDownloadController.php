<?php

namespace App\Http\Controllers;

use App\Support\TicketFile;

class TicketDownloadController extends Controller
{
    public function show(string $token)
    {
        $sellId = TicketFile::sellIdFromToken($token);
        if (! $sellId) {
            abort(404);
        }

        $path = TicketFile::path($sellId);
        if (! is_file($path)) {
            abort(404);
        }

        $contents = file_get_contents($path);
        if ($contents === false || ! str_starts_with($contents, '%PDF')) {
            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bilhete.pdf"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
