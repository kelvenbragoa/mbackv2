<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

trait PaginatesRequests
{
    protected function perPage(Request $request, int $default = 12): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return in_array($perPage, [9, 12, 24], true) ? $perPage : $default;
    }

    protected function perPageTable(Request $request, int $default = 20): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return in_array($perPage, [10, 20, 50], true) ? $perPage : $default;
    }
}
