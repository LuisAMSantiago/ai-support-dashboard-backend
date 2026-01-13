<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $data = (new UserResource($user))->response()->getData(true)['data'];
        return Response::apiSuccess($data, ['code' => 201]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($data)) {
            return Response::apiError('The provided credentials are incorrect.', 422);
        }

        $request->session()->regenerate();

        $data = (new UserResource($request->user()))->response()->getData(true)['data'];
        return Response::apiSuccess($data);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Response::apiSuccess(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $data = (new UserResource($request->user()))->response()->getData(true)['data'];
        return Response::apiSuccess($data);
    }
}
