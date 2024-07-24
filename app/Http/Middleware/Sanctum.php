<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class Sanctum
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next) 
    {
        // $bearer = $request->bearerToken();
        $tokenWithBearer = $request->header('Authorization');
        $bearer = substr($tokenWithBearer, 7);

        return $tokenWithBearer;


        if (!$bearer) {
            return response()->json([
                'success' => false,
                'error' => 'Autorization required!',
            ],401);
        }
        [$id, $token] = explode('|', $bearer, 2);
        $instance = DB::table('personal_access_tokens')->find($id);

        if (hash('sha256', $token) === $instance->token)
        {

            if ($user = User::find($instance->tokenable_id))
            {
                Auth::login($user);
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'error' => 'Access denied.',
        ],401);

    }
}
