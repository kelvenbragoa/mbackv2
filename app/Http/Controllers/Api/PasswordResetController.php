<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $url = rtrim((string) config('app.frontend_url'), '/')
                . '/auth/reset-password?token=' . urlencode($token)
                . '&email=' . urlencode($user->email);

            try {
                Mail::to($user->email)->send(new ResetPasswordMail($user, $url));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Se o email existir na Mticket, receberás um link para redefinir a palavra-passe no site.',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Palavra-passe redefinida com sucesso. Já podes entrar.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Este link é inválido ou já expirou. Pede um novo email de recuperação.',
        ], 422);
    }
}
