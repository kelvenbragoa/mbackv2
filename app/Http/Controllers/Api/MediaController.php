<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(Request $request, ?string $path = null)
    {
        $path = $path ?: (string) $request->query('path', '');
        $path = trim(str_replace('\\', '/', rawurldecode($path)), '/');

        abort_if($path === '' || str_contains($path, '..'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $fullPath = $disk->path($path);
        abort_unless(is_file($fullPath), 404);

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        if ($request->query('as') === 'data') {
            $bytes = file_get_contents($fullPath);
            abort_if($bytes === false, 404);

            return response()->json([
                'data_uri' => 'data:'.$mime.';base64,'.base64_encode($bytes),
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }
}
