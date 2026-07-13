<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse { return response()->json(['profile'=>$request->user()->profile,'user'=>$request->user()->only(['id','name','email'])]); }
    public function update(ProfileUpdateRequest $request): JsonResponse { $request->user()->profile->update($request->validated()); return response()->json(['profile'=>$request->user()->profile->fresh()]); }
    public function password(PasswordUpdateRequest $request): JsonResponse { $request->user()->update(['password'=>$request->validated('password')]); return response()->json(['message'=>'Contrasena actualizada.']); }
}
