<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Models\MobileApp;
use Illuminate\Http\Request;

class StoreAppsController extends Controller
{
    public function index()
    {
        $apps = MobileApp::query()
            ->where('distribution', 'internal')
            ->with('latestRelease')
            ->orderBy('id')
            ->get();

        return response()->json([
            'apps' => $apps->map->toStoreArray()->values(),
        ]);
    }

    public function latest(Request $request, string $slug)
    {
        $app = MobileApp::where('slug', $slug)->with('latestRelease')->first();

        if (! $app) {
            return response()->json(['message' => 'Aplicação não encontrada.'], 404);
        }

        $payload = $app->toStoreArray();
        $release = $payload['latest_release'];

        return response()->json([
            'slug' => $app->slug,
            'name' => $app->name,
            'distribution' => $app->distribution,
            'package_name' => $app->package_name,
            'platform' => $release['platform'] ?? $request->query('platform', 'android'),
            'version_name' => $release['version_name'] ?? null,
            'version_code' => $release['version_code'] ?? 0,
            'min_version_code' => (int) $app->min_version_code,
            'force_update' => (bool) $app->force_update,
            'changelog' => $release['changelog'] ?? null,
            'download_url' => $release['download_url'] ?? null,
            'play_store_url' => $app->play_store_url,
            'app_store_url' => $app->app_store_url,
            'store_url' => $request->query('platform') === 'ios'
                ? $app->app_store_url
                : $app->play_store_url,
        ]);
    }
}
