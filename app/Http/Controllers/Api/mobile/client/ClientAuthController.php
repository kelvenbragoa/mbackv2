<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ClientAuthController extends Controller
{
    //
        public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou senha incorretos.',
            ], 401);
        }

        // Revogar tokens existentes do mesmo dispositivo
        $user->tokens()->where('name', $request->device_name)->delete();

        // Criar novo token
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login realizado com sucesso!',
            'token' => $token,
            'user' => $user->toApiArray(),
        ]);
    }

    /**
     * Registro do usuário
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'mobile' => 'nullable|string|max:20',
            'device_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'role_id' => 2,
            'password' => Hash::make($request->password),
        ]);

        // Criar token para o usuário
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuário criado com sucesso!',
            'token' => $token,
            'user' => $user->toApiArray(),
        ], 201);
    }

    /**
     * Logout do usuário
     */
    public function logout(Request $request)
    {
        // Revogar o token atual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso!',
        ]);
    }

    /**
     * Obter dados do usuário atual
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()->toApiArray(),
        ]);
    }

    /**
     * Actualizar perfil do cliente.
     * Email e telemóvel não podem ser alterados por esta rota.
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $user->name = trim($request->name);
        $user->address = $request->filled('address') ? trim($request->address) : null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado com sucesso!',
            'user' => $user->fresh()->toApiArray(),
        ]);
    }

    /**
     * Revogar todos os tokens do usuário
     */
    public function logoutAll(Request $request)
    {
        // Revogar todos os tokens do usuário
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Todos os dispositivos foram desconectados!',
        ]);
    }
}
