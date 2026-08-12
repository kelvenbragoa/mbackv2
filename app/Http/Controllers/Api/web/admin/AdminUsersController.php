<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUsersController extends Controller
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

    public function index(Request $request)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $users = User::query()
            ->with(['role:id,name'])
            ->when($request->filled('query'), function ($query) use ($request) {
                $search = $request->query('query');
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->query('is_promotor') !== null && $request->query('is_promotor') !== '', function ($query) use ($request) {
                $query->where('is_promotor', (int) $request->query('is_promotor'));
            })
            ->when($request->filled('role_id'), function ($query) use ($request) {
                $query->where('role_id', (int) $request->query('role_id'));
            })
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return response()->json([
            'users' => $users,
            'summary' => [
                'total' => User::count(),
                'promotores' => User::where('is_promotor', 1)->count(),
                'admins' => User::where('role_id', 1)->count(),
            ],
        ]);
    }

    public function show(string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $user = User::with(['role', 'city', 'province', 'gender'])->find($id);

        if (! $user) {
            return response()->json(['message' => 'Utilizador não encontrado.'], 404);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    public function update(Request $request, string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'Utilizador não encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:30'],
            'is_promotor' => ['sometimes', 'boolean'],
            'role_id' => ['sometimes', 'integer', 'in:1,2'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('is_promotor', $data)) {
            $data['is_promotor'] = $data['is_promotor'] ? 1 : 0;
        }

        // Prevent demoting the last admin / self lockout soft rule: cannot remove own admin role.
        if (isset($data['role_id']) && (int) $user->id === (int) Auth::id() && (int) $data['role_id'] !== 1) {
            return response()->json([
                'message' => 'Não podes remover o teu próprio acesso de administrador.',
            ], 422);
        }

        $user->fill($data);
        $user->save();

        return response()->json([
            'message' => 'Utilizador actualizado.',
            'user' => $user->fresh(['role', 'city', 'province', 'gender']),
        ]);
    }

    public function resetPassword(Request $request, string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'Utilizador não encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'A confirmação da palavra-passe não coincide.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->password = $request->input('password');
        $user->save();

        return response()->json([
            'message' => 'Palavra-passe redefinida com sucesso.',
        ]);
    }

    public function updatePage(Request $request, string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'Utilizador não encontrado.'], 404);
        }

        if (! $user->is_promotor) {
            return response()->json([
                'message' => 'Este utilizador não é promotor.',
            ], 422);
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
            'user' => $user->fresh(['role', 'city', 'province', 'gender']),
        ]);
    }

    private function denyNonAdmin()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Sem permissão para aceder a esta área.'], 403);
        }

        return null;
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 20);

        return in_array($perPage, [10, 20, 50], true) ? $perPage : 20;
    }
}
