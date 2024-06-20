<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAuthController extends Controller
{
    //

    public function login(Request $request){
        $loginUserData = $request->validate([
            'email'=>'required|string|email',
            'password'=>'required|min:8'
        ]);
        $user = User::where('email',$loginUserData['email'])->first();
        if(!$user || !Hash::check($loginUserData['password'],$user->password)){
            return response()->json([
                'message' => 'Usuario/Password incorrectos'
            ],401);
        }
            
        $token = $user->createToken($user->name.'-AuthToken')->plainTextToken;
        return response()->json([
            'user'=>$user,
            'access_token' => $token,
        ]);
    }

    public function register(Request $request){
        $registerUserData = $request->validate([
            'name'=>'required|string',
            'email'=>'required|string|email|unique:users',
            'mobile' => ['required', 'string', 'max:9','unique:users'],
            'is_promotor'=>'required',
            'password'=>'required|min:8'
        ]);
        $user = User::create([
            'name' => $registerUserData['name'],
            'email' => $registerUserData['email'],
            'mobile' => $registerUserData['mobile'],
            'role_id' => 2,
            'is_promotor' => $registerUserData['is_promotor'],
            'password' => Hash::make($registerUserData['password']),
        ]);

        $token = $user->createToken($user->name.'-AuthToken')->plainTextToken;
        return response()->json([
            'user'=>$user,
            'access_token' => $token,
        ]);
        // return response()->json([
        //     'message' => 'Usuario Criado ',
        // ]);
    }

    public function updatepassword(Request $request){
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);


        #Match The Old Password
        if(!Hash::check($request->old_password, auth()->user()->password)){
            return response()->json([
                "message"=>"Old Password Doesn't match!"
              ],404); 
        }


        #Update the new Password
        User::whereId(auth()->user()->id)->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            "message"=>"Sucesso"
          ]); 

    }

    public function logout(){
        auth()->user()->tokens()->delete();
        return response()->json([
          "message"=>"logged out"
        ]);
    }
}
