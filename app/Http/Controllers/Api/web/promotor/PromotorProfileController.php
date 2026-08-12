<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromotorProfileController extends Controller
{
    private const RESERVED_SLUGS = [
        'www',
        'backend',
        'api',
        'admin',
        'app',
        'mail',
        'cdn',
        'static',
        'promotores',
        'p',
        'ftp',
        'smtp',
        'ns1',
        'ns2',
        'webmail',
        'cpanel',
        'autoconfig',
        'autodiscover',
    ];

    /**
     * Display the authenticated promoter profile.
     */
    public function index()
    {
        $user = User::with('role')->find(Auth::user()->id);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update promoter public page settings (slug, bio, images).
     */
    public function updatePage(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user || ! $user->is_promotor) {
            return response()->json([
                'message' => 'Apenas promotores podem actualizar esta página.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('users', 'slug')->ignore($user->id),
                Rule::notIn(self::RESERVED_SLUGS),
            ],
            'image' => ['nullable', 'image', 'max:5120'],
            'banner' => ['nullable', 'image', 'max:8192'],
        ], [
            'slug.regex' => 'O slug só pode ter letras minúsculas, números e hífens.',
            'slug.unique' => 'Este slug já está em uso.',
            'slug.not_in' => 'Este slug está reservado. Escolhe outro.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('company_name', $data)) {
            $user->company_name = $data['company_name'] ?: null;
        }
        if (array_key_exists('company_location', $data)) {
            $user->company_location = $data['company_location'] ?: null;
        }
        if (array_key_exists('description', $data)) {
            $user->description = $data['description'] ?: null;
        }

        if (! empty($data['slug'])) {
            $user->slug = $data['slug'];
        } elseif (empty($user->slug)) {
            $base = Str::slug($user->company_name ?: $user->name) ?: 'promotor-'.$user->id;
            if (in_array($base, self::RESERVED_SLUGS, true)) {
                $base = 'promotor-'.$user->id;
            }
            $slug = $base;
            $counter = 1;
            while (
                User::where('slug', $slug)->where('id', '!=', $user->id)->exists()
                || in_array($slug, self::RESERVED_SLUGS, true)
            ) {
                $slug = $base.'-'.$counter;
                $counter++;
            }
            $user->slug = $slug;
        }

        if ($request->hasFile('image')) {
            $imageName = time().'-avatar.'.$request->file('image')->extension();
            $request->file('image')->storeAs('public/promotor', $imageName);
            $user->image = 'promotor/'.$imageName;
        }

        if ($request->hasFile('banner')) {
            $bannerName = time().'-banner.'.$request->file('banner')->extension();
            $request->file('banner')->storeAs('public/promotor', $bannerName);
            $user->banner = 'promotor/'.$bannerName;
        }

        $user->save();

        return response()->json([
            'message' => 'Página do promotor actualizada.',
            'user' => $user->fresh('role'),
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
