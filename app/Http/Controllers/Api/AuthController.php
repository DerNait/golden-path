<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email','password'),$request->boolean('remember'))) throw ValidationException::withMessages(['email'=>'Las credenciales no son correctas.']);
        $request->session()->regenerate();
        return response()->json(['user'=>$request->user()->load('profile')]);
    }
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); Auth::forgetGuards();
        return response()->json(['message'=>'Sesion cerrada.']);
    }
    public function me(Request $request): JsonResponse { return response()->json(['user'=>$request->user()->load('profile')]); }
}
