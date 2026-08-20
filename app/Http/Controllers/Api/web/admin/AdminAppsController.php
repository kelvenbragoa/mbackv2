<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Models\MobileApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAppsController extends Controller
{
    public function index()
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $apps = MobileApp::with(['latestRelease', 'releases'])->orderBy('id')->get();

        return response()->json([
            'apps' => $apps->map(function (MobileApp $app) {
                $payload = $app->toStoreArray();
                $payload['releases'] = $app->releases->map->toStoreArray()->values();

                return $payload;
            }),
        ]);
    }

    public function update(Request $request, string $slug)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $app = MobileApp::where('slug', $slug)->first();
        if (! $app) {
            return response()->json(['message' => 'Aplicação não encontrada.'], 404);
        }

        $data = $request->validate([
            'min_version_code' => ['required', 'integer', 'min:0'],
            'force_update' => ['required', 'boolean'],
            'play_store_url' => ['nullable', 'url', 'max:500'],
            'app_store_url' => ['nullable', 'url', 'max:500'],
        ]);

        $app->update($data);

        return response()->json([
            'message' => 'Configuração actualizada.',
            'app' => $app->fresh('latestRelease')->toStoreArray(),
        ]);
    }

    public function storeRelease(Request $request, string $slug)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $app = MobileApp::where('slug', $slug)->first();
        if (! $app) {
            return response()->json(['message' => 'Aplicação não encontrada.'], 404);
        }

        $latestCode = (int) ($app->releases()->max('version_code') ?? 0);

        $rules = [
            'version_name' => ['required', 'string', 'max:40'],
            'version_code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('app_releases', 'version_code')->where(fn ($q) => $q->where('app_id', $app->id)->where('platform', $request->input('platform', 'android'))),
            ],
            'platform' => ['nullable', 'in:android,ios'],
            'changelog' => ['nullable', 'string', 'max:2000'],
            'force_update' => ['nullable', 'boolean'],
            'min_version_code' => ['nullable', 'integer', 'min:0'],
        ];

        if ($app->isInternal()) {
            $rules['apk'] = ['required', 'file', 'max:153600'];
        }

        $data = $request->validate($rules);

        if ((int) $data['version_code'] <= $latestCode) {
            return response()->json([
                'message' => 'O version code tem de ser maior do que '.$latestCode.'.',
            ], 422);
        }

        $platform = $data['platform'] ?? 'android';
        $forceUpdate = $request->boolean('force_update');
        $filePath = null;
        $fileSize = null;

        if ($app->isInternal()) {
            $file = $request->file('apk');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'apk');
            if ($extension !== 'apk') {
                return response()->json([
                    'message' => 'O ficheiro tem de ser um APK Android.',
                ], 422);
            }

            $filename = $data['version_code'].'.apk';
            $filePath = $file->storeAs('apps/'.$app->slug, $filename, 'public');
            $fileSize = $file->getSize();
        }

        $release = DB::transaction(function () use ($app, $data, $platform, $forceUpdate, $filePath, $fileSize, $request) {
            $release = $app->releases()->create([
                'platform' => $platform,
                'version_name' => $data['version_name'],
                'version_code' => $data['version_code'],
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'changelog' => $data['changelog'] ?? null,
                'force_update' => $forceUpdate,
            ]);

            $app->force_update = $forceUpdate;
            if ($request->filled('min_version_code')) {
                $app->min_version_code = (int) $request->input('min_version_code');
            } elseif ($forceUpdate) {
                $app->min_version_code = (int) $data['version_code'];
            }
            $app->save();

            return $release;
        });

        return response()->json([
            'message' => 'Versão publicada.',
            'app' => $app->fresh(['latestRelease'])->toStoreArray(),
            'release' => $release->toStoreArray(),
        ], 201);
    }

    private function denyNonAdmin()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Sem permissão para aceder a esta área.'], 403);
        }

        return null;
    }
}
