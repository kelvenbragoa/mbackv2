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
        // return $request->header();
        $tokenWithBearer = $request->header('token');

        // return $tokenWithBearer;


        if (!$tokenWithBearer) {
            return response()->json([
                'success' => false,
                'error' => 'Autorization required!',
            ],401);
        }

        // return $tokenWithBearer;
        // [$id, $token] = explode('|', $tokenWithBearer, 2);

        $url = explode("|", $tokenWithBearer);
        $id = $url[1];

        $instance = DB::table('personal_access_tokens')->where('token',$id)->first();

        return $instance;


        

        if (hash('sha256', $id) === $instance->token)
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
